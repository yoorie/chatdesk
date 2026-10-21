import { readFileSync } from 'node:fs';
import { gzipSync } from 'node:zlib';

// Static imports required for a cold visit, excluding optional dynamic feature chunks.
const manifest = JSON.parse(readFileSync(new URL('../public/build/manifest.json', import.meta.url)));
const pages = [
  ['Landing', 'resources/js/Pages/Landing.tsx', 175_000],
  ['Login', 'resources/js/Pages/Auth/Login.tsx', 160_000],
  ['Workspace dashboard', 'resources/js/Pages/App/Dashboard.tsx', 200_000],
  ['Platform dashboard', 'resources/js/Pages/Platform/Dashboard.tsx', 190_000],
  ['Platform settings', 'resources/js/Pages/Platform/Settings.tsx', 185_000],
  ['Platform users', 'resources/js/Pages/Platform/Users/Index.tsx', 185_000],
];
let failed = false;
for (const [page, entry, budget] of pages) {
  const keys = new Set();
  function visit(key) {
    if (keys.has(key)) return;
    if (!manifest[key]) throw new Error(`Missing manifest entry: ${key}. Run npm run build first.`);
    keys.add(key);
    for (const dependency of manifest[key].imports || []) visit(dependency);
  }
  visit('resources/js/app.tsx');
  visit(entry);
  let rawBytes = 0;
  let gzipBytes = 0;
  for (const file of new Set([...keys].map((key) => manifest[key].file))) {
    const bytes = readFileSync(new URL(`../public/build/${file}`, import.meta.url));
    rawBytes += bytes.length;
    gzipBytes += gzipSync(bytes).length;
  }
  const passed = gzipBytes <= budget;
  console.log(`${passed ? 'PASS' : 'FAIL'} ${page}: ${(rawBytes / 1000).toFixed(1)} kB JS, ${(gzipBytes / 1000).toFixed(1)} kB gzip (budget ${budget / 1000} kB)`);
  failed ||= !passed;
}
if (failed) process.exitCode = 1;
