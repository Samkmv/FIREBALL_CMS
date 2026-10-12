<?php
declare(strict_types=1);

/**
 * Real PluginUpdateService + PluginManager + disposable MySQL integration test.
 * Only GitHub's HTTP transport is replaced with metadata/ZIP fixtures.
 * No production ROOT, STORAGE, plugin directory, Google account or database is used.
 * Run: php -d pdo_mysql.default_socket=/Applications/MAMP/tmp/mysql/mysql.sock tests/tape-room-update-safety.php
 */
namespace App\Services {
    final class TapeRoomUpdateHttpFixture
    {
        public array $options = [];
        public function __construct(public string $url) {}
    }

    function curl_init(string $url): TapeRoomUpdateHttpFixture { return new TapeRoomUpdateHttpFixture($url); }
    function curl_setopt_array(TapeRoomUpdateHttpFixture $handle, array $options): bool { $handle->options = $options; return true; }
    function curl_exec(TapeRoomUpdateHttpFixture $handle): bool
    {
        $fixture = $GLOBALS['tapeUpdateRemote'];
        $prefix = 'https://api.github.com/repos/Samkmv/FIREBALL_CMS/';
        if ($handle->url === $prefix . 'commits/main') {
            $body = json_encode(['sha' => $fixture['commit']], JSON_THROW_ON_ERROR);
        } elseif ($handle->url === $prefix . 'contents/plugins/reel-player/plugin.json?ref=' . $fixture['commit']) {
            $body = json_encode(['content' => base64_encode($fixture['metadata'])], JSON_THROW_ON_ERROR);
        } elseif ($handle->url === $prefix . 'zipball/' . $fixture['commit']) {
            $body = (string)file_get_contents($fixture['archive']);
        } else {
            throw new \RuntimeException('Unexpected network request in isolated update test.');
        }
        $GLOBALS['tapeUpdateHttpCalls'][] = $handle->url;
        $header = $handle->options[CURLOPT_HEADERFUNCTION];
        $header($handle, "HTTP/1.1 200 OK\r\n");
        $header($handle, 'Content-Length: ' . strlen($body) . "\r\n");
        $write = $handle->options[CURLOPT_WRITEFUNCTION];
        for ($offset = 0; $offset < strlen($body); $offset += 16384) {
            $chunk = substr($body, $offset, 16384);
            if ($write($handle, $chunk) !== strlen($chunk)) return false;
        }
        return true;
    }
    function curl_getinfo(TapeRoomUpdateHttpFixture $handle, int $option): int { return 200; }
    function curl_error(TapeRoomUpdateHttpFixture $handle): string { return ''; }
    function curl_close(TapeRoomUpdateHttpFixture $handle): void {}
}

namespace {
    if (PHP_SAPI !== 'cli') exit;
    if (!extension_loaded('pdo_mysql') || !class_exists(ZipArchive::class) || !extension_loaded('curl')) {
        throw new RuntimeException('This integration test requires PDO MySQL, ZIP and cURL.');
    }
    $project = dirname(__DIR__);
    $temporary = __DIR__ . '/.tmp';
    if (!is_dir($temporary)) mkdir($temporary, 0700, true);
    $testRoot = $temporary . '/tape-room-update-' . bin2hex(random_bytes(8));
    mkdir($testRoot, 0700);
    foreach (['storage', 'tmp/cache', 'tmp/sessions', 'public', 'config', 'plugins', 'remote'] as $directory) {
        mkdir($testRoot . '/' . $directory, 0700, true);
    }
    define('ROOT', $testRoot);
    define('STORAGE', ROOT . '/storage');
    define('CACHE', ROOT . '/tmp/cache');
    define('ERROR_LOGS', ROOT . '/tmp/error.log');
    ini_set('error_log', ROOT . '/tmp/php-error.log');
    ini_set('session.save_path', ROOT . '/tmp/sessions');
    ini_set('upload_tmp_dir', ROOT . '/tmp');
    // Prevent Git from discovering the actual workspace above the isolated root.
    putenv('GIT_CEILING_DIRECTORIES=' . $temporary);
    $admin = null;
    $schema = 'tape_update_test_' . bin2hex(random_bytes(8));
    $createdDatabase = false;
    register_shutdown_function(static function () use ($testRoot, $schema, &$admin, &$createdDatabase): void {
        if ($createdDatabase && $admin instanceof PDO) {
            try { $admin->exec('DROP DATABASE `' . $schema . '`'); }
            catch (Throwable $error) { fwrite(STDERR, "Could not remove this disposable update database.\n"); }
        }
        if (!is_dir($testRoot)) return;
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testRoot, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $entry) {
            if ($entry->isDir() && !$entry->isLink()) rmdir($entry->getPathname()); else unlink($entry->getPathname());
        }
        rmdir($testRoot);
    });
    // Read only connection settings; redefine the database name before any service boots.
    $local = require $project . '/config/config.local.php';
    $settings = $local['DB_SETTINGS'];
    $settings['database'] = $schema;
    define('DB_SETTINGS', $settings);
    unset($local);
    require $project . '/config/config.php';
    require $project . '/vendor/autoload.php';
    copy($project . '/config/version.php', CONFIG . '/version.php');

    function db(): \FBL\Database { return $GLOBALS['tapeUpdateDb']; }
    function cache(): \FBL\Cache { return $GLOBALS['tapeUpdateCache']; }
    function return_translation(string $key): string { return $key; }
    function log_error_details(string $message, array $context = [], ?Throwable $error = null): void {
        error_log($message . ': ' . ($error?->getMessage() ?? ''));
    }
    function base_href(string $path): string { return $path; }
    function base_url(string $path): string { return 'https://isolated-update.invalid' . $path; }
    function app(): object {
        static $application;
        return $application ??= new class {
            public mixed $db = null;
            public function get(string $name): mixed { return $name === 'lang' ? ['code' => 'ru'] : null; }
        };
    }
    \FBL\PerformanceProfiler::start();
    $GLOBALS['tapeUpdateCache'] = new \FBL\Cache();
    $dsn = 'mysql:host=' . DB_SETTINGS['host'] . ';charset=utf8mb4';
    if (!empty(DB_SETTINGS['port'])) $dsn .= ';port=' . (int)DB_SETTINGS['port'];
    $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
    $admin = new PDO($dsn, DB_SETTINGS['username'], DB_SETTINGS['password'], $options);
    $admin->exec('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $createdDatabase = true;
    $connection = new PDO($dsn . ';dbname=' . $schema, DB_SETTINGS['username'], DB_SETTINGS['password'], $options);
    $GLOBALS['tapeUpdateDb'] = new \FBL\Database($connection);
    db()->query('CREATE TABLE users (id INT UNSIGNED NOT NULL PRIMARY KEY) ENGINE=InnoDB');
    db()->query('INSERT INTO users(id) VALUES(1),(2)');
    db()->query('CREATE TABLE site_settings (setting_key VARCHAR(100) NOT NULL PRIMARY KEY, setting_value TEXT, updated_at DATETIME NOT NULL) ENGINE=InnoDB');
    db()->query('INSERT INTO site_settings VALUES(?,?,?)', ['site_title', 'Isolated update fixture', date('Y-m-d H:i:s')]);

    $checks = 0;
    $assert = static function (bool $value, string $message) use (&$checks): void {
        $checks++;
        if (!$value) throw new RuntimeException($message);
    };
    $copy = static function (string $source, string $destination) use (&$copy): void {
        if (!is_dir($destination)) mkdir($destination, 0700, true);
        foreach (scandir($source) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === 'tests' || str_starts_with($entry, '.')) continue;
            $from = $source . '/' . $entry;
            if (is_dir($from)) $copy($from, $destination . '/' . $entry); else copy($from, $destination . '/' . $entry);
        }
    };
    $source = $project . '/plugins/reel-player';
    $installed = PLUGINS . '/reel-player';
    $copy($source, $installed);
    $metadata = json_decode((string)file_get_contents($installed . '/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
    $releaseMetadata = $metadata;
    $assert($releaseMetadata['version'] === '1.4.0', 'Source plugin is the actual 1.4.0 release under review');
    $metadata['version'] = '1.2.14';
    file_put_contents($installed . '/plugin.json', json_encode($metadata, JSON_THROW_ON_ERROR));
    foreach (['004_chapters.sql','005_catalog.sql','006_catalog_listening_credits.sql'] as $migration) unlink($installed . '/migrations/' . $migration);
    file_put_contents($installed . '/obsolete-update-fixture.txt', 'Must disappear when the plugin is replaced.');
    $manager = new \FBL\Plugins\PluginManager(PLUGINS);
    $manager->install('reel-player');
    db()->query("UPDATE plugins SET status='active' WHERE slug='reel-player'");
    $manager->setSetting('reel-player', 'preserved-fixture-setting', ['value' => 'preserved']);
    $assert((int)db()->query('SELECT COUNT(*) FROM plugin_migrations WHERE plugin_slug=?', ['reel-player'])->getColumn() === 3, 'Old fixture has only the three original migrations');
    $assert((int)db()->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reel_chapters'")->getColumn() === 0, 'Chapters table is absent before the update');

    $private = STORAGE . '/reel-player';
    mkdir($private . '/1', 0700, true);
    $appCredentials = ['client_id' => 'fixture-update.apps.googleusercontent.com', 'client_secret' => 'fixture-secret-not-real'];
    $tokens = ['access_token' => 'fixture-access-not-real', 'refresh_token' => 'fixture-refresh-not-real', 'expires_at' => time() + 3600,
        'app_binding' => hash('sha256', $appCredentials['client_id'] . "\0" . $appCredentials['client_secret'])];
    $relativeAudio = '1/' . str_repeat('a', 40) . '.mp3';
    // Stable synthetic bytes test preservation, not MP3 decoding or audio playback.
    $protected = [
        'drive-app.json' => json_encode($appCredentials, JSON_THROW_ON_ERROR),
        '1/drive.json' => json_encode($tokens, JSON_THROW_ON_ERROR),
        $relativeAudio => "ID3\x04\0\0" . str_repeat("\x11\x22\x33\x44", 16384),
        '1/' . str_repeat('b', 40) . '.jpg' => 'fixture-cover-not-an-image',
    ];
    foreach ($protected as $relative => $bytes) { file_put_contents($private . '/' . $relative, $bytes); chmod($private . '/' . $relative, 0600); }
    $checkFiles = static function (string $phase) use ($assert, $protected, $private): void {
        foreach ($protected as $relative => $bytes) {
            $assert(is_file($private . '/' . $relative) && hash_file('sha256', $private . '/' . $relative) === hash('sha256', $bytes), $phase . ': private bytes survive: ' . $relative);
            $assert((fileperms($private . '/' . $relative) & 0777) === 0600, $phase . ': private permissions survive');
        }
    };
    db()->query('INSERT INTO reel_tracks(owner_id,title,artist,filename,file_path,mime,file_size,duration,cover_path,favorite,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)',
        [1, 'Existing local mix', 'Fixture artist', 'mix.mp3', $relativeAudio, 'audio/mpeg', strlen($protected[$relativeAudio]), 7200, '1/' . str_repeat('b', 40) . '.jpg', 1, date('Y-m-d H:i:s')]);
    $track = (int)db()->getInsertId();
    db()->query('INSERT INTO reel_tracks(owner_id,title,filename,mime,file_size,duration,source,source_id,created_at) VALUES(?,?,?,?,?,?,?,?,?)',
        [1, 'Existing Drive mix', 'drive.flac', 'audio/flac', 123456, 7200, 'drive', 'fixture-private-drive-id', date('Y-m-d H:i:s')]);
    $driveTrack = (int)db()->getInsertId();
    db()->query('INSERT INTO reel_playlists(owner_id,name,created_at) VALUES(?,?,?)', [1, 'Existing playlist', date('Y-m-d H:i:s')]);
    $playlist = (int)db()->getInsertId();
    foreach ([$track, $driveTrack] as $position => $id) db()->query('INSERT INTO reel_playlist_tracks(playlist_id,track_id,position) VALUES(?,?,?)', [$playlist, $id, $position]);
    $snapshot = static fn(): array => [
        db()->query('SELECT * FROM reel_tracks ORDER BY id')->get(),
        db()->query('SELECT * FROM reel_playlists ORDER BY id')->get(),
        db()->query('SELECT * FROM reel_playlist_tracks ORDER BY position')->get(),
    ];
    $before = $snapshot();
    $GLOBALS['tapeUpdateHttpCalls'] = [];
    $makeRemote = static function (string $version, bool $fail = false) use ($source, $metadata, $testRoot): void {
        $next = $metadata;
        $next['version'] = $version;
        $json = json_encode($next, JSON_THROW_ON_ERROR);
        $archive = $testRoot . '/remote/' . $version . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Cannot create fixture ZIP.');
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (!$file->isFile() || $file->isLink()) continue;
            $relative = substr($file->getPathname(), strlen($source) + 1);
            if (str_starts_with($relative, 'tests/') || preg_match('~(?:^|/)\.~', $relative)) continue;
            // The pre-catalog releases must contain only their historical
            // migrations, even though the code fixture comes from this tree.
            if (version_compare($version,'1.4.0','<') && in_array($relative,['migrations/005_catalog.sql','migrations/006_catalog_listening_credits.sql'],true)) continue;
            $name = 'fixture-repository/plugins/reel-player/' . $relative;
            if ($relative === 'plugin.json') $zip->addFromString($name, $json); else $zip->addFile($file->getPathname(), $name);
        }
        if ($fail) $zip->addFromString('fixture-repository/plugins/reel-player/migrations/999_failure_fixture.sql', 'THIS IS NOT VALID SQL;');
        $zip->close();
        $GLOBALS['tapeUpdateRemote'] = ['commit' => hash('sha1', 'fixture-' . $version), 'metadata' => $json, 'archive' => $archive];
    };
    $updater = new \App\Services\PluginUpdateService($manager, PLUGINS, STORAGE . '/plugin-updates');
    $makeRemote('1.3.0');
    $result = $updater->update('reel-player', ['id' => 1, 'name' => 'Fixture creator']);
    $assert($result['status'] === 'success' && $result['version'] === '1.3.0', 'Real updater finishes the first replacement');
    $assert($snapshot() === $before, 'Local and Drive tracks, favorites, playlist and order remain identical');
    $assert(!is_file($installed . '/obsolete-update-fixture.txt'), 'The old plugin directory was actually replaced');
    $assert((int)db()->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reel_chapters'")->getColumn() === 1, 'New chapters table is automatically migrated');
    $assert((int)db()->query('SELECT COUNT(*) FROM plugin_migrations WHERE plugin_slug=?', ['reel-player'])->getColumn() === 4, 'Migration ledger contains the new migration once');
    $assert(db()->query('SELECT status FROM plugins WHERE slug=?', ['reel-player'])->getColumn() === 'active', 'Activation state survives updating');
    $assert($manager->setting('reel-player', 'preserved-fixture-setting') === ['value' => 'preserved'], 'Existing plugin settings survive');
    $checkFiles('First update');
    $drive = new \Fireball\ReelPlayer\Services\GoogleDrive(1);
    $assert($drive->status()['configured'] && $drive->status()['connected'], 'Existing synthetic Google connection remains configured and connected');
    $media = new \Fireball\ReelPlayer\Services\MediaStorage();
    $assert($media->path($relativeAudio) === $private . '/' . $relativeAudio && is_file($media->path($relativeAudio)), 'MediaStorage resolves the unchanged private audio path');
    $assert(!is_file(STORAGE . '/update.maintenance'), 'Successful update leaves maintenance mode');
    $assert((glob(STORAGE . '/plugin-updates/work/*') ?: []) === [], 'Temporary extraction workspace is cleaned without runtime deletion');
    $backup = json_decode((string)file_get_contents(STORAGE . '/update-recovery.json'), true, 512, JSON_THROW_ON_ERROR);
    $assert(is_file($backup['database']) && is_file($backup['files']), 'Database and full file backups are created');
    $backupZip = new ZipArchive();
    $backupZip->open($backup['files']);
    foreach ($protected as $relative => $bytes) $assert($backupZip->getFromName('storage/reel-player/' . $relative) === $bytes, 'Full backup includes each private runtime file');
    $backupZip->close();

    $chapters = new \Fireball\ReelPlayer\Repositories\Chapters(1);
    $chapters->create($track, ['title' => 'Local chapter', 'start' => 0]);
    $chapters->create($track, ['title' => 'Later local chapter', 'start' => 3600]);
    $chapters->create($driveTrack, ['title' => 'Drive chapter', 'start' => 0]);
    $chapterSnapshot = db()->query('SELECT * FROM reel_chapters ORDER BY id')->get();
    $makeRemote('1.3.1'); // Fixture-only version; the real plugin manifest remains unchanged.
    $result = $updater->update('reel-player', ['id' => 1, 'name' => 'Fixture creator']);
    $assert($result['status'] === 'success' && $result['version'] === '1.3.1', 'Real updater completes another fixture replacement');
    $assert($snapshot() === $before, 'Second update preserves all original library records');
    $assert(db()->query('SELECT * FROM reel_chapters ORDER BY id')->get() === $chapterSnapshot, 'Existing chapter IDs, source relations, titles and times survive another update');
    $assert((int)db()->query('SELECT COUNT(*) FROM plugin_migrations WHERE plugin_slug=?', ['reel-player'])->getColumn() === 4, 'Existing migrations do not run again');
    $checkFiles('Second update');

    $makeRemote($releaseMetadata['version']);
    $result = $updater->update('reel-player', ['id' => 1, 'name' => 'Fixture creator']);
    $assert($result['status'] === 'success' && $result['version'] === '1.4.0', 'Real updater installs the actual catalog release from the 1.3.1 fixture');
    $assert($snapshot() === $before, 'Catalog migration preserves original local/Drive IDs, metadata, favorites and playlists exactly');
    $assert(db()->query('SELECT * FROM reel_chapters ORDER BY id')->get() === $chapterSnapshot, 'Catalog release preserves source chapter IDs, titles and timing exactly');
    $assert((int)db()->query('SELECT COUNT(*) FROM plugin_migrations WHERE plugin_slug=?', ['reel-player'])->getColumn() === 6, 'Catalog and credited migrations are journaled once after the four existing migrations');
    $catalogTables = ['reel_albums','reel_album_tracks','reel_album_saves','reel_catalog_favorites','reel_track_stats','reel_listening_sessions'];
    foreach ($catalogTables as $table) {
        $assert((int)db()->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->getColumn() === 1, 'Catalog migration creates '.$table);
        $assert((int)db()->query('SELECT COUNT(*) FROM '.$table)->getColumn() === 0, 'Catalog migration does not create or publish data automatically: '.$table);
    }
    $assert((int)db()->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reel_listening_sessions' AND COLUMN_NAME='credited'")->getColumn() === 1, 'Listening-credit migration adds the processed/credited distinction');
    $assert(db()->query('SELECT status FROM plugins WHERE slug=?', ['reel-player'])->getColumn() === 'active', 'Catalog release preserves activation state');
    $assert($manager->setting('reel-player', 'preserved-fixture-setting') === ['value' => 'preserved'], 'Catalog release preserves existing plugin settings');
    $assert($drive->status()['configured'] && $drive->status()['connected'], 'Catalog release keeps the existing Google account configured and connected');
    $checkFiles('Catalog release update');
    $assert(!is_file(STORAGE . '/update.maintenance'), 'Catalog release finishes maintenance mode');
    $assert((glob(STORAGE . '/plugin-updates/work/*') ?: []) === [], 'Catalog release removes its own temporary extraction workspace');

    // Seed all catalog structures through the actual updated repository. These
    // records are user actions after migration, rather than automatic imports.
    $catalog = new \Fireball\ReelPlayer\Repositories\Catalog(1,true);
    $viewerCatalog = new \Fireball\ReelPlayer\Repositories\Catalog(2,false);
    $album = $catalog->action('album.create',['title'=>'Explicit fixture album','artist'=>'Fixture artist','tracks'=>[$track,$driveTrack]]);
    $assert((int)$catalog->album($album)['published'] === 0, 'New album stays private until an explicit creator action');
    $catalog->action('album.publish',['id'=>$album,'published'=>1]);
    $viewerCatalog->action('album.save',['id'=>$album,'saved'=>1]);
    $viewerCatalog->action('track.favorite',['id'=>$track,'favorite'=>1]);
    $listen = $viewerCatalog->beginListening($driveTrack);
    db()->query('UPDATE reel_listening_sessions SET started_at=DATE_SUB(NOW(6),INTERVAL 31 SECOND) WHERE token_hash=?',[hash('sha256',$listen['token'])]);
    $assert($viewerCatalog->finishListening($listen['token'],30)['credited'] === true, 'Updated catalog can count a guarded, actually elapsed shared-source listen');
    $viewerState = $viewerCatalog->state();
    $assert(count($viewerState['albums']) === 1 && $viewerState['albums'][0]['saved'] === true, 'Updated catalog creates and saves shared albums without audio copies');
    $assert($snapshot() === $before, 'Post-upgrade album saves and viewer favorites preserve original private library rows');
    $catalogSnapshot = static fn(): array => [
        db()->query('SELECT * FROM reel_albums ORDER BY id')->get(),
        db()->query('SELECT * FROM reel_album_tracks ORDER BY album_id,position')->get(),
        db()->query('SELECT * FROM reel_album_saves ORDER BY owner_id,album_id')->get(),
        db()->query('SELECT * FROM reel_catalog_favorites ORDER BY owner_id,track_id')->get(),
        db()->query('SELECT * FROM reel_track_stats ORDER BY track_id')->get(),
        db()->query('SELECT * FROM reel_listening_sessions ORDER BY token_hash')->get(),
    ];
    $beforeFailedCatalog = $catalogSnapshot();

    $makeRemote('1.4.1', true);
    $failed = false;
    try { $updater->update('reel-player', ['id' => 1, 'name' => 'Fixture creator']); }
    catch (RuntimeException $error) { $failed = true; }
    $assert($failed, 'Invalid new migration rejects the update');
    $assert($manager->metadata('reel-player')['version'] === '1.4.0', 'Failed update restores the preceding catalog plugin directory');
    $assert($snapshot() === $before && db()->query('SELECT * FROM reel_chapters ORDER BY id')->get() === $chapterSnapshot, 'Rejected migration preserves all library and chapter data');
    $assert($catalogSnapshot() === $beforeFailedCatalog, 'Rejected later migration preserves album IDs, order, saves, per-viewer favorites, stats and credited listening tokens exactly');
    $assert((int)db()->query('SELECT COUNT(*) FROM plugin_migrations WHERE plugin_slug=?', ['reel-player'])->getColumn() === 6, 'Failed later migration does not reapply or erase the catalog migration ledger');
    $checkFiles('Failed update rollback');
    $assert(is_file(STORAGE . '/update.maintenance'), 'A migration failure retains recovery maintenance instead of exposing a partially migrated site');
    $assert((glob(PLUGINS . '/.reel-player.*') ?: []) === [], 'Successful replacement and rollback clean their own staging directories');
    $assert(count($GLOBALS['tapeUpdateHttpCalls']) === 16, 'Four update attempts use only the four expected fixture transfers each');

    $artifact = __DIR__ . '/artifacts/tape-room-1.4.0';
    if (!is_dir($artifact)) mkdir($artifact, 0700, true);
    $report = [
        'result' => 'passed', 'checks' => $checks, 'php' => PHP_VERSION,
        'pipeline' => 'Unmodified PluginUpdateService::update, PluginManager, MySQL migrations, private storage, database/file backups, rollback and cleanup',
        'transfers' => 'Only GitHub cURL responses replaced with local metadata and ZIP fixtures; no outbound requests',
        'http_fixture_transfers' => count($GLOBALS['tapeUpdateHttpCalls']),
        'migration_ledger' => (int)db()->query('SELECT COUNT(*) FROM plugin_migrations WHERE plugin_slug=?',['reel-player'])->getColumn(),
        'catalog_tables' => $catalogTables,
        'scenarios' => ['1.2.14 fixture to 1.3.0 with automatic chapter migration', '1.3.0 to synthetic fixture 1.3.1 with existing chapters', '1.3.1 fixture to actual 1.4.0 with six empty catalog tables and listening credits', 'Explicit album create/publication/save and actual elapsed listening after update', 'Rejected synthetic 1.4.1 migration with 1.4.0 file rollback and unchanged catalog rows'],
        'preserved' => ['OAuth app settings and per-owner tokens: byte equality and 0600 permissions', 'Local audio and cover bytes', 'Local and Google Drive DB tracks, favorites, playlists, order', 'Chapter IDs, associations and timestamps', 'Catalog album IDs, track order, user saves/favorites, counts and credited tokens', 'Plugin settings and active status'],
        'limitations' => ['Synthetic OAuth and audio bytes; no actual Google authentication or audio decoder playback', 'GitHub network transport mocked; live hosting and external API behavior not exercised', 'Failed migration fixture makes no preceding schema changes; this is not a test of database rollback after arbitrary DDL'],
    ];
    file_put_contents($artifact . '/update-safety.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    echo "Passed {$checks} real plugin update preservation checks. Only HTTP transport was fixture-backed; all files and the MySQL database were isolated.\n";
}
