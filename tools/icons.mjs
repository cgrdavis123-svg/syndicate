// {{icon:name}} inlines icons/ui/name.svg or icons/brands/name.svg as a decorative icon.
// {{icon:name|Label}} makes it a labelled image. Until a brand file is added the label shows as
// text instead, or {{icon:name|Label|fallback}} / {{icon:name||fallback}} shows the fallback HTML,
// so pages build and read fine before the official logos arrive.
import { existsSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = fileURLToPath(new URL('../icons/', import.meta.url));

// Logos we expect in icons/brands/ (official files from each brand; see icons/README.md).
export const BRANDS = ['visa', 'mastercard', 'amex', 'discover', 'apple-pay', 'google-pay', 'stripe', 'tiktok', 'discord', 'google', 'meta', 'instagram', 'facebook', 'youtube', 'twitch'];

const cache = new Map();
const missing = new Set();

function load(name) {
  if (cache.has(name)) return cache.get(name);
  const kind = existsSync(join(ROOT, 'ui', name + '.svg')) ? 'ui' : existsSync(join(ROOT, 'brands', name + '.svg')) ? 'brands' : null;
  if (!kind && !BRANDS.includes(name)) throw new Error(`Unknown icon ${name}: add icons/ui/${name}.svg`);
  let svg = null;
  if (kind) {
    svg = readFileSync(join(ROOT, kind, name + '.svg'), 'utf8')
      .replace(/<\?xml[\s\S]*?\?>|<!DOCTYPE[\s\S]*?>|<!--[\s\S]*?-->/g, '')
      .replace(/<(script|metadata|title|desc)[\s\S]*?<\/\1>/g, '')
      .replace(/\s(on\w+)="[^"]*"/g, '')
      .trim();
    // Keep ids unique when several icons share a page.
    svg = svg.replace(/\bid="([^"]+)"/g, `id="${name}-$1"`).replace(/url\(#([^)]+)\)/g, `url(#${name}-$1)`).replace(/href="#([^"]+)"/g, `href="#${name}-$1"`);
    svg = svg.replace(/<svg\b([^>]*)>/, (m, a) => `<svg${a.replace(/\s(width|height|class)="[^"]*"/g, '')} class="ico ico-${kind === 'ui' ? 'ui' : 'brand'} ico-${name}" focusable="false">`);
  }
  cache.set(name, svg);
  return svg;
}

export function icon(name, label = '', fallback) {
  const svg = load(name);
  if (!svg) {
    missing.add(name);
    if (fallback !== undefined) return label ? fallback : `<span class="ico-fallback" aria-hidden="true">${fallback}</span>`;
    return label ? `<span class="ico-text">${label}</span>` : '';
  }
  return label ? svg.replace('<svg', `<svg role="img" aria-label="${label}"`) : svg.replace('<svg', '<svg aria-hidden="true"');
}

export const iconTags = html => html.replace(/\{\{icon:([a-z0-9-]+)(?:\|([^|}]*))?(?:\|([^}]*))?\}\}/g, (m, name, label, fallback) => icon(name, label || '', fallback));

export function reportMissing(site) {
  if (missing.size) console.warn(`${site}: brand icons not added yet (text shown instead): ${[...missing].sort().join(', ')}`);
}
