'use strict';
const fs = require('fs');
const vm = require('vm');
const assert = require('assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../plugins/toy-car-rental/assets/toy-rental.js'), 'utf8');
const audioSource = source.slice(source.indexOf('    const unlockAudio ='), source.indexOf('    const request ='));
let checks = 0;
const check = (truth, label) => { checks++; assert.ok(truth, label); };
const fixture = ({ enabled = true, webkit = false, available = true } = {}) => {
    const contexts = [];
    const listeners = {};
    class FakeAudioContext {
        constructor() { this.state = 'suspended'; this.currentTime = 40; this.destination = {}; this.oscillators = []; this.gains = []; this.resumes = 0; contexts.push(this); }
        resume() { this.resumes++; this.state = 'running'; return Promise.resolve(); }
        createOscillator() {
            if (this.failAt === this.oscillators.length + 1) throw new Error('Fixture audio failure');
            const osc = { frequency: {}, connections: [], starts: [], stops: [], disconnected: false, connect(target) { this.connections.push(target); }, disconnect() { this.disconnected = true; }, start(time) { this.starts.push(time); }, stop(time) { this.stops.push(time); } };
            this.oscillators.push(osc); return osc;
        }
        createGain() { const gain = { gain: { value: 1, events: [], setValueAtTime(value, time) { this.events.push(['set', value, time]); }, linearRampToValueAtTime(value, time) { this.events.push(['linear', value, time]); }, exponentialRampToValueAtTime(value, time) { this.events.push(['exponential', value, time]); } }, disconnected: false, connect(target) { this.destination = target; }, disconnect() { this.disconnected = true; } }; this.gains.push(gain); return gain; }
    }
    const settings = { soundEnabled: enabled };
    const window = available ? (webkit ? { webkitAudioContext: FakeAudioContext } : { AudioContext: FakeAudioContext }) : {};
    const api = vm.runInNewContext(`let audioContext; let activeAudioCue;\n${audioSource}\n({ unlockAudio, playSound, get active() { return activeAudioCue; } })`, { settings, window, document: { addEventListener(name, callback, options) { listeners[name] = { callback, options }; } } });
    return { api, settings, contexts, listeners };
};
const sample = fixture();
sample.api.playSound(); check(sample.contexts.length === 0, 'Expiry before a gesture does not unlock audio');
sample.api.unlockAudio({ isTrusted: false }); check(sample.contexts.length === 0, 'Synthetic interaction ignored');
sample.listeners.pointerdown.callback({ isTrusted: true });
const context = sample.contexts[0];
check(context.resumes === 1 && context.state === 'running', 'Real pointer gesture prepares running audio');
check(context.oscillators.length === 0, 'Gesture itself stays silent');
context.state = 'suspended'; sample.listeners.pointerup.callback({ isTrusted: true });
check(context.state === 'running' && sample.listeners.pointerup.options.passive, 'Touch release can silently resume audio');
sample.api.playSound();
check(context.oscillators.length === 14, 'Seven melodic notes with subtle overtones');
check(context.oscillators.every(osc => osc.type === 'sine'), 'Every voice is a smooth sine wave');
const duration = Math.max(...context.oscillators.map(osc => osc.stops[0])) - Math.min(...context.oscillators.map(osc => osc.starts[0]));
check(Math.abs(duration - 3.02) < 1e-9, 'Chime lasts 3.02 seconds');
check(context.gains.every(gain => gain.gain.events[0][0] === 'set' && gain.gain.events[0][1] === 0), 'Voices start at silence');
check(context.gains.every(gain => gain.gain.events[1][0] === 'linear' && Math.abs(gain.gain.events[1][2] - gain.gain.events[0][2] - .045) < 1e-9), '45 ms soft attacks');
check(context.gains.every(gain => gain.gain.events[2][0] === 'exponential' && gain.gain.events[2][1] === .0001), 'Voices decay gently');
check(Math.max(...context.gains.map(gain => gain.gain.events[1][1])) === .14, 'Moderate peak gain');
sample.api.playSound(); check(context.oscillators.length === 14, 'Simultaneous expired cards do not stack chimes');
context.oscillators[0].onended(); sample.api.playSound(); check(context.oscillators.length === 14, 'First note finishing does not unlock overlapping cue');
for (const osc of context.oscillators.slice(1)) osc.onended();
check(context.oscillators.every(osc => osc.disconnected) && context.gains.every(gain => gain.disconnected), 'Every ended oscillator and gain disconnects');
check(sample.api.active === null, 'Cue released after all notes');
sample.api.playSound(); check(context.oscillators.length === 28, 'A later expiry can play');
for (const osc of context.oscillators.slice(14)) osc.onended();
sample.settings.soundEnabled = false; sample.api.playSound(); check(context.oscillators.length === 28, 'Disabled setting prevents sound');
sample.settings.soundEnabled = true; context.state = 'suspended'; sample.api.playSound(); check(context.oscillators.length === 28, 'Suspended sound is not queued unexpectedly');
sample.listeners.keydown.callback({ isTrusted: true }); check(context.resumes === 3 && context.state === 'running', 'Later keyboard gesture resumes interrupted context');
context.state = 'closed'; sample.api.unlockAudio({ isTrusted: true }); check(sample.contexts.length === 2, 'Closed context recreated on interaction');
const disabled = fixture({ enabled: false }); disabled.api.unlockAudio({ isTrusted: true }); check(disabled.contexts.length === 0, 'Disabled setting prevents context creation');
const legacy = fixture({ webkit: true }); legacy.api.unlockAudio({ isTrusted: true }); legacy.api.playSound(); check(legacy.contexts[0].oscillators.length === 14, 'Safari AudioContext fallback');
const missing = fixture({ available: false }); missing.api.unlockAudio({ isTrusted: true }); missing.api.playSound(); check(missing.contexts.length === 0, 'Missing audio APIs are harmless');
const failed = fixture(); failed.api.unlockAudio({ isTrusted: true }); failed.contexts[0].failAt = 3; failed.api.playSound();
check(failed.api.active === null && failed.contexts[0].oscillators.every(osc => osc.disconnected) && failed.contexts[0].gains.every(gain => gain.disconnected), 'Partial scheduling failure cleans all nodes and releases cue');
failed.contexts[0].failAt = 0; failed.api.playSound(); check(failed.contexts[0].oscillators.length === 16, 'Audio can recover after temporary failure');
let ceiling = 0;
for (let time = 40; time < 44; time += .0001) {
    let envelope = 0;
    for (const gain of context.gains.slice(0, 14)) {
        const [start, attack, end] = gain.gain.events;
        if (time < start[2] || time >= end[2]) continue;
        envelope += time <= attack[2] ? attack[1] * (time - start[2]) / (attack[2] - start[2]) : attack[1] * Math.pow(end[1] / attack[1], (time - attack[2]) / (end[2] - attack[2]));
    }
    ceiling = Math.max(ceiling, envelope);
}
check(ceiling < .2, 'Combined gain envelopes stay comfortably below clipping');
console.log(`${checks} audio checks passed; ${duration.toFixed(2)} s; peak voice gain 0.14; summed envelope ceiling ${ceiling.toFixed(3)}`);
