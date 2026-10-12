'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs'), path = require('node:path'), vm = require('node:vm');
const TapeRoomTransport = require('../plugins/reel-player/assets/transport.js');
let checks = 0;
function verify(condition, message) { assert.ok(condition, message); checks++; }
function close(actual, expected, tolerance, message) { verify(Math.abs(actual - expected) <= tolerance, `${message}: ${actual} vs ${expected}`); }
function advance(transport, seconds, fps = 60) {
    for (let frame = 0; frame < Math.round(seconds * fps); frame++) transport.step(1 / fps);
}
function state(transport) { return {running:transport.running, speed:transport.speed, slack:transport.slack, slackVelocity:transport.slackVelocity, moving:transport.moving}; }
function stopAndSettle(transport, fps = 60) {
    transport.setRunning(false);
    let seconds = 0, previous = transport.speed;
    while (transport.moving && seconds < 3) {
        transport.step(1 / fps); seconds += 1 / fps;
        verify(transport.speed <= previous + 1e-12 && transport.speed >= 0, 'Braking never reverses or accelerates the reels');
        previous = transport.speed;
        verify(Number.isFinite(transport.slack) && Number.isFinite(transport.slackVelocity), 'Tension remains finite during braking');
    }
    verify(seconds < 1.6, 'Reels and tension finish stopping within a finite short transition');
    verify(!transport.moving && transport.speed === 0 && transport.slack === 8 && transport.slackVelocity === 0, 'Settled pause permits the animation loop to stop entirely');
    const finalState = state(transport); advance(transport, 1, fps);
    assert.deepEqual(state(transport), finalState, 'Settled transport remains still without perpetual spring drift'); checks++;
    return seconds;
}

const transport = new TapeRoomTransport();
verify(!transport.running && !transport.moving && transport.speed === 0, 'Initial deck is stationary');
const initialState = state(transport);
for (const invalid of [0, -1, NaN, Infinity, undefined]) transport.step(invalid);
assert.deepEqual(state(transport), initialState, 'Invalid frame intervals do not corrupt or advance transport'); checks++;
transport.setRunning(true);
verify(transport.speed === 0 && transport.moving, 'Start schedules a ramp without snapping directly to full speed');
let previous = 0;
for (let frame = 0; frame < 30; frame++) {
    transport.step(1 / 60);
    verify(transport.speed >= previous && transport.speed <= 1, 'Reels accelerate monotonically without overspeed');
    previous = transport.speed;
}
verify(transport.speed === 1, 'Startup reaches normal transport speed');
advance(transport, 1);
verify(transport.slack === 0 && transport.slackVelocity === 0, 'Tape settles taut while playing');
const beforePause = transport.speed;
transport.setRunning(false);
verify(transport.speed === beforePause, 'Pause retains the current angle velocity at the transition boundary');
transport.step(.1);
verify(transport.speed > 0 && transport.speed < beforePause, 'Pause coasts before stopping');
stopAndSettle(transport);

const reversal = new TapeRoomTransport(); reversal.setRunning(true); advance(reversal, .2);
const partialSpeed = reversal.speed;
reversal.setRunning(false);
close(reversal.speed, partialSpeed, 1e-12, 'Pause during startup is continuous');
advance(reversal, .2);
const brakingSpeed = reversal.speed;
reversal.setRunning(true);
close(reversal.speed, brakingSpeed, 1e-12, 'Resume during braking is continuous');
advance(reversal, .4);
verify(reversal.speed === 1, 'Interrupted braking resumes to normal speed');
stopAndSettle(reversal);

const repeated = new TapeRoomTransport(); repeated.setRunning(true); advance(repeated, 1);
repeated.setRunning(false);
for (let frame = 0; frame < 60; frame++) { repeated.setRunning(false); repeated.step(1 / 60); }
verify(repeated.speed === 0, 'Repeated pause or buffering events do not restart the braking interval');
stopAndSettle(repeated);
repeated.setRunning(true); advance(repeated, .1); repeated.reset();
assert.deepEqual(state(repeated), initialState, 'Hidden document reset cancels residual speed and spring energy'); checks++;

const dropped = new TapeRoomTransport(); dropped.setRunning(true); dropped.step(.5);
verify(dropped.speed === 1, 'A dropped frame does not stretch the startup ramp');
dropped.setRunning(false); dropped.step(.45);
close(dropped.speed, .5, 1e-10, 'A long display gap still advances braking by actual elapsed time');
dropped.step(.45);
verify(dropped.speed === 0, 'Two sparse frames still complete the 0.9-second brake');
dropped.step(.6);
verify(!dropped.moving && dropped.slack === 8, 'The tension spring settles safely after sparse frames');

const samples = [];
for (const fps of [30, 60, 120]) {
    const deck = new TapeRoomTransport(); deck.setRunning(true); advance(deck, 1, fps);
    deck.setRunning(false); advance(deck, .5, fps);
    samples.push({fps, speed:deck.speed, slack:deck.slack, settle:stopAndSettle(deck, fps)});
}
for (const sample of samples.slice(1)) {
    close(sample.speed, samples[0].speed, 1e-10, `Braking speed follows elapsed time at ${sample.fps} Hz`);
    close(sample.slack, samples[0].slack, .25, `Tension looks consistent at ${sample.fps} Hz`);
    close(sample.settle, samples[0].settle, 1 / 30, `Spring settling duration is consistent at ${sample.fps} Hz`);
}

function parsePath(path) {
    const commands = [...path.matchAll(/([MCAL])([^MCAL]*)/g)].map(match => ({type:match[1], values:(match[2].match(/-?\d+(?:\.\d+)?/g) || []).map(Number)}));
    assert.deepEqual(commands.map(command => command.type), ['M', 'C', 'C', 'C', 'A', 'L']); checks++;
    verify(commands.every(command => command.values.every(Number.isFinite)), 'Tape path contains only finite coordinates');
    verify(!/NaN|Infinity/.test(path), 'Tape path contains no invalid numeric text');
    return commands;
}
const subtract = (a, b) => [a[0] - b[0], a[1] - b[1]];
const cross = (a, b) => a[0] * b[1] - a[1] * b[0];
const dot = (a, b) => a[0] * b[0] + a[1] * b[1];
const length = vector => Math.hypot(...vector);
const point = (values, index) => values.slice(index * 2, index * 2 + 2);
const halfThickness = 1.75, guideRadius = 28 + halfThickness;
function cubicPoint(points, t) {
    const u = 1 - t;
    return [0, 1].map(axis => u ** 3 * points[0][axis] + 3 * u ** 2 * t * points[1][axis] + 3 * u * t ** 2 * points[2][axis] + t ** 3 * points[3][axis]);
}
function parallel(a, b, message) { close(cross(a, b) / length(b), 0, .02, message); verify(dot(a, b) > 0, `${message}: forward direction`); }
for (const [cx, direction, rollerX] of [[230, 1, 378], [970, -1, 822]]) {
    for (let index = 0; index <= 20; index++) {
        const radius = Math.sqrt(78 ** 2 + (174 ** 2 - 78 ** 2) * index / 20);
        for (const slack of [0, 4, 9, 12]) {
            const commands = parsePath(TapeRoomTransport.tapePath(cx, radius, direction, slack));
            const hiddenStart = commands[0].values, entry = commands[1].values, first = commands[2].values, second = commands[3].values;
            const start = point(entry, 2);
            const middle = point(first, 2), end = point(second, 2), span = subtract(end, start);
            const contact = TapeRoomTransport.tapeEntry(cx, radius, direction);
            close(contact.x, start[0], .0051, 'Fade contact follows the actual tape-path tangent x');
            close(contact.y, start[1], .0051, 'Fade contact follows the actual tape-path tangent y');
            close(Math.hypot(contact.tx,contact.ty), 1, 1e-12, 'Fade direction is a unit tangent');
            parallel([contact.tx,contact.ty], span, 'Fade follows the outgoing ribbon instead of the radial direction');
            verify(length(subtract(hiddenStart, [cx, 210])) + halfThickness < radius - .5, 'The entire leading cap is hidden inside the real tape pack');
            parallel(subtract(start, point(entry, 1)), span, 'Hidden lead-in joins visible tape on the outer tangent');
            close(length(subtract(start, [cx, 210])) - halfThickness, radius, .015, 'Inner tape edge contacts the changing pack without a floating gap');
            close(length(subtract(end, [rollerX, 425])) - halfThickness, 28, .015, 'Inner tape edge contacts the solid guide rim');
            const startDerivative = subtract(point(first, 0), start), endDerivative = subtract(end, point(second, 1));
            parallel(startDerivative, span, 'Tape leaves the reel along the original straight tangent');
            parallel(endDerivative, span, 'Tape enters the guide along the original straight tangent');
            close(dot(span, subtract(start, [cx, 210])) / (length(span) * (radius + halfThickness)), 0, .0001, 'Straight path is tangent to tape pack');
            close(dot(span, subtract(end, [rollerX, 425])) / (length(span) * guideRadius), 0, .0003, 'Straight path is tangent to guide rim');
            parallel(subtract(middle, point(first, 1)), subtract(point(second, 0), middle), 'Curved halves share a smooth tangent');
            close(middle[1] - (start[1] + end[1]) / 2, slack * .75, .015, 'Tension changes restrained sag while preserving endpoints');
            assert.deepEqual(commands[4].values, [guideRadius, guideRadius, 0, 0, direction === 1 ? 0 : 1, rollerX + direction * guideRadius, 425], 'Thin tape wraps around the outside of the guide at a fixed radius'); checks++;
            assert.deepEqual(commands[5].values, [rollerX, 425], 'Hidden head path remains fixed'); checks++;
            verify([...start, ...first, ...second].every(Number.isFinite), 'Multiple-hour position geometry remains finite');
            for (const segment of [[start, point(first, 0), point(first, 1), middle], [middle, point(second, 0), point(second, 1), end]]) {
                for (let sample = 0; sample <= 10; sample++) {
                    const at = cubicPoint(segment, sample / 10);
                    verify(length(subtract(at, [cx, 210])) >= radius + halfThickness - .015, 'Visible free tape never passes through the wound pack');
                }
            }
            if (slack === 0) {
                for (const control of [point(first, 0), point(first, 1), middle, point(second, 0), point(second, 1)]) {
                    close(cross(subtract(control, start), span) / length(span), 0, .02, 'Reduced motion and taut tape preserve the original straight path');
                }
            }
        }
        const taut = TapeRoomTransport.tapePath(cx, radius, direction, 0);
        verify(TapeRoomTransport.tapePath(cx, radius, direction, -10) === taut && TapeRoomTransport.tapePath(cx, radius, direction, NaN) === taut, 'Invalid slack cannot invert tape tension');
        verify(TapeRoomTransport.tapePath(cx, radius, direction, 100) === TapeRoomTransport.tapePath(cx, radius, direction, 12), 'Tension has a restrained maximum sag');
    }
}

const playerSource = fs.readFileSync(path.join(__dirname, '../plugins/reel-player/assets/player.js'), 'utf8');
const radiusCalculation = playerSource.match(/const inner = 78, outer = 174;[\s\S]+?packRadii = radii;/);
verify(Boolean(radiusCalculation), 'Exercise the actual player pack-radius calculation and mask update');
const viewSource = fs.readFileSync(path.join(__dirname, '../plugins/reel-player/views/player.php'), 'utf8');
for (const [side, cx, radius, direction] of [['left', 230, 174, 1], ['right', 970, 78, -1]]) {
    const initialPath = viewSource.match(new RegExp(`<path data-tape-${side} d="([^"]+)"`));
    verify(Boolean(initialPath) && initialPath[1] === TapeRoomTransport.tapePath(cx, radius, direction, 8), 'Initial visible ribbon matches the animated geometry before player startup');
    const gradient = viewSource.match(new RegExp(`<linearGradient id="rp-tape-feed-${side}"[^>]+>[\\s\\S]+?<\\/linearGradient>`))?.[0];
    verify(Boolean(gradient) && gradient.includes(`data-tape-fade="${side}"`) && gradient.includes('gradientUnits="userSpaceOnUse"'), 'Feed fade is linked to its physical reel in absolute deck coordinates');
    const contact = TapeRoomTransport.tapeEntry(cx, radius, direction);
    for (const [attribute, expected] of [['x1',contact.x - 2 * contact.tx],['y1',contact.y - 2 * contact.ty],['x2',contact.x + 16 * contact.tx],['y2',contact.y + 16 * contact.ty]]) {
        const value = gradient.match(new RegExp(`${attribute}="([^"]+)"`))?.[1];
        close(Number(value), expected, .0051, 'Initial fade coordinates match the dynamic tangent transition');
    }
    const stops = [...gradient.matchAll(/<stop\b[^>]+>/g)].map(stop => Number(stop[0].match(/stop-opacity="([^"]+)"/)?.[1]));
    assert.deepEqual(stops, [0,.75,1], 'Opaque ribbon emerges gradually from a fully transparent leading join'); checks++;
    verify(viewSource.includes(`style="--rp-tape-stroke:url(#rp-tape-feed-${side})"`), 'Ribbon stroke uses the matching feed fade');
}
const rotatingSpoke = viewSource.split('\n').find(line => line.includes('<path class="rp-reel-spoke"'));
verify(Boolean(rotatingSpoke) && !/stroke=/.test(rotatingSpoke) && !/fill="url/.test(rotatingSpoke), 'Clear rotor reinforcement adds no bright seam or gradient band across the windings');
verify(!viewSource.includes('M76 109 A166'), 'Clear flange has no decorative arc glare across the tape');
const annulus = viewSource.match(/<mask id="rp-pack-annulus-[\s\S]+?<\/mask>/)?.[0];
verify(Boolean(annulus) && /<circle[^>]+r="78"[^>]+fill="black"/.test(annulus), 'Wound-tape rendering cuts out the full inner hub rather than painting a dark disk');
verify(viewSource.includes('<g mask="url(#rp-pack-annulus-'), 'Inner hub cutout applies to both the tape fill and visible windings');
const circle = radius => ({radius:String(radius), getAttribute(name) { assert.equal(name, 'r'); return this.radius; }, setAttribute(name, value) { assert.equal(name, 'r'); this.radius = value; }});
const reels = ['left', 'right'].map((side, index) => {
    const initialMask = viewSource.match(new RegExp(`<circle[^>]+r="(\\d+)"[^>]+data-tape-mask="${side}"`));
    verify(Boolean(initialMask), 'Visible tape mask is attached separately to the supply and take-up pack');
    return {pack:circle(index ? 78 : 174), clip:circle(index ? 78 : 174), tapeMask:circle(initialMask[1])};
});
for (const progress of [0, .5, 1]) {
    const radii = vm.runInNewContext(`${radiusCalculation[0]}\nradii`, {progress, visuals:{reels}, packRadii:null});
    const annularAreas = radii.map(radius => Math.PI * (radius ** 2 - 78 ** 2));
    close(annularAreas[0] + annularAreas[1], Math.PI * (174 ** 2 - 78 ** 2), 1e-8, 'Physical tape amount is conserved during transfer');
    close(annularAreas[1] / (annularAreas[0] + annularAreas[1]), progress, 1e-12, 'Take-up tape amount follows track progress');
    verify(radii.every(radius => radius >= 78 && radius <= 174), 'Tape pack stays outside the hub and inside the flange');
    reels.forEach((reel, index) => {
        close(Number(reel.pack.radius), radii[index], .0051, 'Visible tape pack follows the actual conserved radius');
        close(Number(reel.tapeMask.radius), radii[index], .0051, 'Free tape becomes visible immediately outside the real pack, through the transparent flange');
        verify(reel.clip.radius === reel.pack.radius && reel.tapeMask.radius === reel.pack.radius, 'Fill, windings and free-tape masking agree during the whole transfer');
    });
    if (progress === .5) close(radii[0], radii[1], 1e-12, 'Half of the recording gives equal supply and take-up packs');
    if (progress === 0 || progress === 1) {
        const emptySide = progress === 0 ? 1 : 0;
        close(annularAreas[emptySide], 0, 1e-10, 'Empty reel has no phantom tape inside the transparent flange');
        close(radii[emptySide], 78, 1e-12, 'Empty reel reaches the clear hub boundary');
    }
}
console.log(`Tape Room transport checks passed: ${checks}; startup, coast-down, interruption, finite settling, 30/60/120 Hz and tape tangency.`);
