'use strict';
const assert = require('node:assert/strict');
const quality = require('../plugins/reel-player/assets/quality.js');
let checks = 0;
function check(actual, expected, message) { assert.equal(actual, expected, message); checks++; }
check(quality.summary({filename:'SONG.FLAC'}), 'FLAC', 'Filename provides useful format before optional header fetch');
check(quality.summary({filename:'unknown',mime:'application/x-flac'}), 'FLAC', 'MIME is a format fallback');
check(quality.summary({filename:'unknown',mime:'invalid'}), 'АУДИО', 'Unknown quality is never invented');
check(quality.summary({filename:'song.flac'}, {format:'FLAC',bitDepth:24,sampleRate:48000,channels:2,bitrate:950000,bitrateKind:'average'}), 'FLAC · 24 БИТ · 48 КГЦ · ≈ 950 КБИТ/С · СТЕРЕО', 'Lossless resolution and approximate actual file bitrate are distinct');
check(quality.summary({filename:'song.wav'}, {format:'WAV',bitDepth:16,sampleRate:44100,channels:2,bitrate:1411200,bitrateKind:'exact'}), 'WAV · 16 БИТ · 44,1 КГЦ · 1411 КБИТ/С · СТЕРЕО', 'PCM bitrate is exact and localized');
check(quality.summary({filename:'song.mp3'}, {format:'MP3',sampleRate:22050,channels:1,frameBitrate:64000}), 'MP3 · 22,05 КГЦ · МОНО', 'A single MPEG frame does not claim full-file CBR');
check(quality.summary({filename:'song.mp3',file_size:4000000,duration:100}, {format:'MP3',frameBitrate:128000}), 'MP3 · ≈ 320 КБИТ/С', 'VBR estimate is based on the entire known file');
check(quality.summary({filename:'song.flac',file_size:12000000,duration:90},{format:'FLAC',duration:120}), 'FLAC · ≈ 800 КБИТ/С', 'STREAMINFO duration takes precedence over stale library duration');
check(quality.summary({filename:'song.mp3',file_size:1000000},null,100), 'MP3 · ≈ 80 КБИТ/С', 'Native loaded duration enables average fallback');
check(quality.summary({filename:'song.m4a',file_size:1000000,duration:0}), 'M4A', 'Unknown container codec, precision and bitrate stay hidden');
check(quality.summary({filename:'song.ogg'}, {format:'<script>',sampleRate:Infinity,bitDepth:-1,channels:99,bitrate:NaN}), 'OGG', 'Invalid or arbitrary backend labels are not shown');
check(quality.summary({filename:'song.flac'}, {format:'FLAC',sampleRate:192000,bitDepth:32,channels:6}), 'FLAC · 32 БИТ · 192 КГЦ · 6 КАН.', 'Multichannel resolution remains accurate');
check(quality.summary({filename:'song.mp3',file_size:100,duration:Infinity}), 'MP3', 'Non-finite native metadata is ignored');
console.log(`Tape Room quality labels: ${checks} checks passed.`);
