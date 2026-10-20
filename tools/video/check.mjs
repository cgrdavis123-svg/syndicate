#!/usr/bin/env node
// Checks a video brief against the house rules before rendering (CLAUDE.md §2 and §20, PLAYBOOK.md).
// Exits 1 and lists every problem it finds.
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const DIR = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(DIR, '../..');
const file = process.argv[2];
const brief = JSON.parse(fs.readFileSync(file, 'utf8'));
const shots = JSON.parse(fs.readFileSync(path.join(DIR, 'shots/shots.json'), 'utf8'));
const catalog = JSON.parse(fs.readFileSync(path.join(ROOT, 'crm/app/catalog.json'), 'utf8'));
const problems = [];
const bad = m => problems.push(m);

const THEMES = ['calm', 'hype', 'blocks'];
const TYPES = ['title', 'words', 'shot', 'phone', 'browsers', 'scroll', 'prices', 'montage', 'chat', 'list', 'world', 'end'];
if (!THEMES.includes(brief.theme)) bad(`theme must be one of ${THEMES.join(', ')}`);
if (!Array.isArray(brief.scenes) || brief.scenes.length < 3) bad('needs at least 3 scenes');
const bpm = brief.bpm || (brief.theme === 'calm' ? 96 : 150), B = 60 / bpm;

// timing
let total = 0;
(brief.scenes || []).forEach((s, i) => {
  if (!TYPES.includes(s.type)) bad(`scene ${i + 1}: unknown type "${s.type}"`);
  if (s.type === 'world' && brief.theme !== 'blocks') bad(`scene ${i + 1}: "world" scenes only work in the blocks theme`);
  const used = [s.shot, ...(s.shots || [])].filter(Boolean);
  used.forEach(id => { if (!shots[id]) bad(`scene ${i + 1}: unknown shot "${id}" (see shots/shots.json)`); });
  if (['shot', 'phone', 'scroll'].includes(s.type) && !s.shot) bad(`scene ${i + 1}: ${s.type} needs "shot"`);
  if (['browsers', 'montage'].includes(s.type) && !(s.shots || []).length) bad(`scene ${i + 1}: ${s.type} needs "shots"`);
  if (s.type === 'phone' && s.shot && !shots[s.shot]?.tall) bad(`scene ${i + 1}: phone scenes need a tall phone screenshot`);
  total += (s.beats || 8) * B;
});
const first = brief.scenes?.[0];
if (first && (first.beats || 8) * B > 4.5) bad(`the hook scene runs ${((first.beats || 8) * B).toFixed(1)}s; keep the opening under 4.5s so the hook lands fast`);
if (first && !['title', 'words', 'chat', 'world'].includes(first.type)) bad('open with a title, words, chat or world scene so there is a hook on screen in the first second');
if (brief.scenes?.at(-1)?.type !== 'end') bad('the last scene must be an "end" scene');
if (total < 12 || total > 60) bad(`video is ${total.toFixed(1)}s; keep it between 12 and 60 seconds`);

// all text the viewer or the caption will show
const texts = [];
const walk = (v, where) => { if (typeof v === 'string') texts.push([v, where]); else if (Array.isArray(v)) v.forEach((x, i) => walk(x, where)); else if (v && typeof v === 'object') Object.entries(v).forEach(([k, x]) => { if (!['type', 'shot', 'shots', 'spots', 'bg', 'border'].includes(k)) walk(x, where + '.' + k) }) };
(brief.scenes || []).forEach((s, i) => walk(s, `scene ${i + 1}`));
walk(brief.caption, 'caption'); walk(brief.firstComment, 'firstComment'); walk(brief.hashtags, 'hashtags');

// prices must exist in the catalog
const allowed = new Set();
const fmt = (n, iv) => `${n}|${iv}`;
catalog.products.forEach(p => allowed.add(fmt(p.price, p.interval === 'month' ? 'month' : '')));
allowed.add(fmt(150, 'hour')); // Creator Ops consultation
const offerOk = brief.offer === 'starter-500' && catalog.offers?.some(o => o.slug === 'starter-500' && o.active && new Date(o.ends_on + 'T23:59:59') >= new Date());
if (offerOk) allowed.add(fmt(500, ''));
const money = /\$\s?([\d,]+)(?:\.\d\d)?\s*(\/\s*(mo|month|hr|hour)\b|\s*(a|per)\s+(month|hour)\b|\s*\/MO\b|\s*\/HR\b)?/gi;
for (const [t, where] of texts) {
  for (const m of t.matchAll(money)) {
    const n = Number(m[1].replace(/,/g, '')); const unit = (m[3] || m[5] || '').toLowerCase();
    const iv = unit.startsWith('mo') ? 'month' : unit.startsWith('h') ? 'hour' : '';
    if (!allowed.has(fmt(n, iv))) bad(`${where}: "${m[0].trim()}" is not a price in crm/app/catalog.json`);
  }
}
// claims and voice (CLAUDE.md §2 Honesty, §20 Copy)
const banned = [/guarantee/i, /#1\b/, /\bnumber one\b/i, /\bsynergy\b/i, /revolutionary/i, /world[- ]class/i, /\bbest in (town|the)\b/i, /\branked? first\b/i, /\bonly today\b/i, /\blast chance\b/i, /\bhurry\b/i, /\bviral\b/i, /\d+(\.\d+)?\s?%/, /\b\d+x (more|faster|better)\b/i, /\bclients? (love|say)\b/i, /\btestimonial/i];
for (const [t, where] of texts) for (const r of banned) if (r.test(t)) bad(`${where}: "${t}" breaks the honesty/voice rules (${r})`);

// disclosure for demo data, concept sites and the fictional server
const kinds = new Set((brief.scenes || []).flatMap(s => [s.shot, ...(s.shots || [])]).filter(Boolean).map(id => shots[id]?.kind));
const cap = String(brief.caption || '');
if (!cap) bad('caption is missing');
if (cap.length > 2200) bad('caption is over TikTok\'s 2,200 characters');
if ((kinds.has('crm') || kinds.has('concept') || kinds.has('copperleaf')) && !/(demo|fictional|concept)/i.test(cap)) bad('caption must say the dashboards/sites shown are demo data or fictional concepts');
if (/minecraft/i.test(JSON.stringify(brief)) && !kinds.has('copperleaf') && !brief.scenes.at(-1)?.disclaimer) bad('mentions Minecraft: add end.disclaimer "Not affiliated with Mojang or Microsoft."');
const tags = brief.hashtags || [];
if (tags.length < 3 || tags.length > 8) bad('use 3 to 8 hashtags');
tags.forEach(h => { if (!/^#[A-Za-z0-9_]+$/.test(h)) bad(`hashtag "${h}" must be # followed by letters/numbers only`) });
if (!brief.firstComment) bad('firstComment is missing (pin it under the post)');
if (!brief.hook) bad('"hook" is missing: write the one-line promise the first second makes');

if (problems.length) { console.error(`Brief check failed (${problems.length}):\n- ` + problems.join('\n- ')); process.exit(1); }
console.log(`Brief OK: ${brief.theme}, ${brief.scenes.length} scenes, ${total.toFixed(1)}s`);
