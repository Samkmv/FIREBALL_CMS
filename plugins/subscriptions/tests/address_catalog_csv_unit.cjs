const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const { batches, parseRecord } = require('../assets/address-catalog-import.js');
let checks = 0;
function check(actual, expected, label) { assert.deepEqual(actual, expected, label); checks++; }
async function collect(text, limit = 262144) {
    const result = [];
    for await (const batch of batches(new Blob([text]), limit)) result.push(batch);
    return result;
}
(async () => {
    check(parseRecord('"А, Б","Центр ""Юг""",,41,001234', ','), ['А, Б', 'Центр "Юг"', '', '41', '001234'], 'CSV quoting and leading zero preserved');
    for (const separator of [',', ';', '\t']) {
        const result = await collect('\uFEFF' + ['region', 'city', 'street', 'house', 'postal_code'].join(separator) + '\r\n' + ['Край', 'Город', 'Улица', '41', '001234'].join(separator) + '\r\n');
        check(result[0].header, ['region', 'city', 'street', 'house', 'postal_code'], 'Header and BOM');
        check(result[0].rows, [['Край', 'Город', 'Улица', '41', '001234']], 'Delimiter ' + JSON.stringify(separator));
    }
    const header = 'region,city,street,house,postal_code\n';
    const many = header + 'Ставропольский край,Железноводск,"ул. Октябрьская",41,357400\n'.repeat(6000);
    const chunks = await collect(many, 16384);
    check(chunks.reduce((sum, chunk) => sum + chunk.rows.length, 0), 6000, 'All rows cross file/HTTP chunk boundaries without loss');
    for (const chunk of chunks) {
        check(Buffer.byteLength(JSON.stringify({ action: 'batch', id: 'a'.repeat(32), sequence: 100, rows: chunk.rows })) <= 16384, true, 'Whole HTTP body below limit');
        check(chunk.rows.length <= 1500, true, 'Bounded database work per request');
    }
    const multiline = await collect(header + 'Регион,"Город\nПосёлок","улица ""Победы""",41,001234\n');
    check(multiline[0].rows[0], ['Регион', 'Город\nПосёлок', 'улица "Победы"', '41', '001234'], 'Quoted multiline field preserved');
    await assert.rejects(() => collect(header + 'Регион,"Незакрыто'), /кавычки/); checks++;
    await assert.rejects(() => collect(header + 'Регион,"Город"неверно,улица,,123456'), /кавычки/); checks++;
    await assert.rejects(() => collect(''), /пуст/); checks++;
    const largeQuoted = 'Я'.repeat(40000) + '""Юг';
    check((await collect(header + 'Край,"' + largeQuoted + '",улица,,123456\n', 200000))[0].rows[0][1], largeQuoted.replace('""', '"'), 'UTF-8 and quotes crossing 64KB read boundary preserved');
    if (process.env.SUBSCRIPTIONS_IMPORT_CSV) {
        const filename = process.env.SUBSCRIPTIONS_IMPORT_CSV;
        const handle = await fs.open(filename, 'r');
        const stat = await handle.stat();
        // File-backed Blob-like reader: verify the actual large artifact without loading it all into RAM.
        const file = { size: stat.size, slice(start, end) { return { async arrayBuffer() {
            const bytes = Buffer.alloc(Math.min(end, stat.size) - start);
            await handle.read(bytes, 0, bytes.length, start);
            return bytes;
        } }; } };
        let rows = 0, requests = 0;
        for await (const chunk of batches(file, 262144)) {
            assert(Buffer.byteLength(JSON.stringify({ action: 'batch', id: 'a'.repeat(32), sequence: requests, rows: chunk.rows })) <= 262144);
            assert(chunk.rows.every(row => row.length === 5 && row[0] && row[1] && /^\d{6}$/.test(row[4])));
            rows += chunk.rows.length; requests++;
        }
        await handle.close();
        const report = JSON.parse(await fs.readFile(filename.replace('subscription-addresses.csv', 'merge-report.json'), 'utf8'));
        check(rows, report.counts.output_rows, 'Actual merged artifact count matches source reconciliation');
        console.log(`Merged CSV verified: ${rows} rows, ${requests} bounded requests, ${stat.size} bytes.`);
    }
    console.log(`CSV streaming tests passed: ${checks} checks.`);
})().catch(error => { console.error(error); process.exitCode = 1; });
