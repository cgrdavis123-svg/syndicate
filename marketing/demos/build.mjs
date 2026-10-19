// Writes the portfolio demo sites into marketing/work/<slug>/. Run: node marketing/demos/build.mjs
// (build-marketing.mjs then copies marketing/work/ into the site). Edit the demo modules, not the output.
import { writeFileSync, mkdirSync, readdirSync, rmSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { baseCss, baseJs } from './common.mjs';

const here = dirname(fileURLToPath(import.meta.url));
const only = process.argv[2];
for (const f of readdirSync(here).filter(f => f.endsWith('.mjs') && !['build.mjs', 'common.mjs'].includes(f))) {
  const d = (await import(join(here, f))).default;
  if (only && d.slug !== only) continue;
  const out = join(here, '..', 'work', d.slug);
  for (const old of readdirSync(out, { withFileTypes: true })) if (old.isFile() && /\.(html|css|js)$/.test(old.name)) rmSync(join(out, old.name));
  mkdirSync(out, { recursive: true });
  writeFileSync(join(out, 'style.css'), baseCss + d.css);
  writeFileSync(join(out, 'site.js'), baseJs + (d.js || ''));
  for (const p of d.pages) {
    const html = d.shell(p);
    if (/\$\{|undefined/.test(html)) throw new Error(`${d.slug}/${p.file}: unfilled template value`);
    mkdirSync(dirname(join(out, p.file)), { recursive: true });
    writeFileSync(join(out, p.file), html);
  }
  console.log(`demo ${d.slug}: ${d.pages.length} pages`);
}
