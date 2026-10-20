#!/usr/bin/env node
// Renders a brief to an mp4.
//   node tools/video/render.mjs briefs/x.json            -> out/<name>.mp4 (+ .jpg contact sheet)
//   node tools/video/render.mjs briefs/x.json --sheet    -> contact sheet only (fast check)
// Needs: Playwright (Chromium), ffmpeg, python3 with numpy + scipy.
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';

const DIR = path.dirname(fileURLToPath(import.meta.url));
const require = createRequire(import.meta.url);
let chromium;
try { ({ chromium } = require('playwright')); } catch { ({ chromium } = await import('playwright')); }

const args = process.argv.slice(2);
const briefPath = path.resolve(args.find(a => !a.startsWith('--')) || '');
if (!fs.existsSync(briefPath)) { console.error('Usage: node render.mjs <brief.json> [--sheet] [--fps=60]'); process.exit(1); }
const sheetOnly = args.includes('--sheet');
const fps = Number((args.find(a => a.startsWith('--fps=')) || '--fps=60').split('=')[1]);
const name = path.basename(briefPath, '.json');
const outDir = path.join(DIR, 'out'); fs.mkdirSync(outDir, { recursive: true });

// check the brief first
const chk = spawnSync(process.execPath, [path.join(DIR, 'check.mjs'), briefPath], { stdio: 'inherit' });
if (chk.status !== 0) process.exit(1);

// static server for the engine, shots, fonts and the brief
const TYPES = { '.html': 'text/html', '.js': 'text/javascript', '.mjs': 'text/javascript', '.css': 'text/css', '.json': 'application/json', '.jpg': 'image/jpeg', '.png': 'image/png', '.woff2': 'font/woff2' };
const server = http.createServer((req, res) => {
  const u = decodeURIComponent(new URL(req.url, 'http://x').pathname);
  let f = u === '/__brief.json' ? briefPath : path.join(DIR, u);
  if (!f.startsWith(DIR) && f !== briefPath) { res.writeHead(403); return res.end(); }
  fs.readFile(f, (err, buf) => { if (err) { res.writeHead(404); return res.end(); } res.writeHead(200, { 'Content-Type': TYPES[path.extname(f)] || 'application/octet-stream' }); res.end(buf); });
});
await new Promise(r => server.listen(0, '127.0.0.1', r));
const port = server.address().port;

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1080, height: 1920 } });
const errors = [];
page.on('pageerror', e => errors.push(e.message));
await page.goto(`http://127.0.0.1:${port}/engine.html?brief=/__brief.json`);
const info = (await page.evaluate(() => window.ready).catch(e => ({ error: String(e) }))) || { error: 'engine did not start' };
if (info.error || errors.length) { console.error('Engine error:', info.error || errors.join('\n')); await browser.close(); server.close(); process.exit(1); }
const END = info.END;
console.log(`${name}: ${END.toFixed(1)}s, ${info.CUES.cuts.length} scenes, theme ${info.CUES.theme}`);

// contact sheet: one frame from the middle of each scene + the last frame
const sheet = path.join(outDir, name + '-sheet.jpg');
{
  const cuts = info.CUES.cuts, times = cuts.map((c, i) => Math.min(END - .05, (c + (cuts[i + 1] ?? END)) / 2 + .3)).concat([END - .05]);
  const tmp = fs.mkdtempSync(path.join(outDir, '.sheet-'));
  for (let i = 0; i < times.length; i++) { await page.evaluate(t => seek(t), times[i]); await page.screenshot({ path: path.join(tmp, `s${String(i).padStart(2, '0')}.jpg`), type: 'jpeg', quality: 70 }); }
  spawnSync('ffmpeg', ['-v', 'error', '-y', '-pattern_type', 'glob', '-i', path.join(tmp, 's*.jpg'), '-vf', `scale=270:480,tile=${Math.min(6, times.length)}x${Math.ceil(times.length / 6)}`, '-frames:v', '1', sheet]);
  fs.rmSync(tmp, { recursive: true });
  console.log('Contact sheet:', sheet);
}
if (sheetOnly) { await browser.close(); server.close(); process.exit(0); }

// soundtrack from the engine's cues
const cuesFile = path.join(outDir, name + '-cues.json'), wav = path.join(outDir, name + '.wav');
fs.writeFileSync(cuesFile, JSON.stringify(info.CUES));
const mus = spawnSync('python3', [path.join(DIR, 'music.py'), cuesFile, wav], { stdio: 'inherit' });
if (mus.status !== 0) { console.error('Music failed'); process.exit(1); }

// frames -> ffmpeg (60 fps blended down to 30 for smooth motion)
const mp4 = path.join(outDir, name + '.mp4');
const ff = spawn('ffmpeg', ['-y', '-v', 'error', '-f', 'image2pipe', '-framerate', String(fps), '-c:v', 'mjpeg', '-i', '-', '-i', wav,
  '-vf', fps > 30 ? 'tmix=frames=2,fps=30' : 'fps=30', '-c:v', 'libx264', '-preset', 'medium', '-crf', '21', '-pix_fmt', 'yuv420p', '-c:a', 'aac', '-b:a', '192k', '-shortest', '-movflags', '+faststart', mp4], { stdio: ['pipe', 'inherit', 'inherit'] });
const N = Math.ceil(END * fps);
for (let f = 0; f < N; f++) {
  await page.evaluate(t => seek(t), f / fps);
  const buf = await page.screenshot({ type: 'jpeg', quality: 92 });
  if (!ff.stdin.write(buf)) await new Promise(r => ff.stdin.once('drain', r));
  if (f % (fps * 5) === 0) console.log(`frame ${f}/${N}`);
}
ff.stdin.end();
await new Promise(r => ff.on('close', r));
await browser.close(); server.close();
fs.rmSync(cuesFile); fs.rmSync(wav);
console.log('Video:', mp4, (fs.statSync(mp4).size / 1e6).toFixed(1) + ' MB');
