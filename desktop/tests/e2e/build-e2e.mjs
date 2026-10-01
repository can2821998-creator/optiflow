import { build } from 'esbuild';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const cfg = JSON.parse(fs.readFileSync(path.join(root, 'config/development.json'), 'utf8'));
if (process.env.OPTIFLOW_TEST_URL) cfg.optiflowBaseUrl = process.env.OPTIFLOW_TEST_URL;
await build({
  entryPoints: [path.join(root, 'tests/e2e/e2e-main.ts')],
  outfile: path.join(root, 'dist-e2e/e2e-main.js'),
  bundle: true,
  platform: 'node',
  target: 'node22',
  format: 'cjs',
  external: ['electron', 'electron-updater'],
  define: { __OPTIFLOW_CONFIG__: JSON.stringify(cfg) },
  logLevel: 'warning',
});
console.log('built dist-e2e/e2e-main.js →', cfg.optiflowBaseUrl);
