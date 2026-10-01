/**
 * Build: bundles main, preloads and shell with esbuild and bakes the chosen
 * config/<env>.json into the main bundle. Usage: node scripts/build.mjs --env=production
 * Sandboxed preloads cannot require local files, so each preload is ONE bundled file.
 */
import { build } from 'esbuild';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const envArg = process.argv.find((a) => a.startsWith('--env='));
const env = envArg ? envArg.slice(6) : process.env.APP_ENV || 'development';
if (!['development', 'staging', 'production'].includes(env)) throw new Error(`unknown env ${env}`);

const cfg = JSON.parse(fs.readFileSync(path.join(root, 'config', `${env}.json`), 'utf8'));
if (cfg.appEnv !== env) throw new Error(`config/${env}.json has appEnv=${cfg.appEnv}`);
if (env === 'production' && cfg.allowInsecureLocalhost) throw new Error('production config must not allow insecure localhost');

const out = path.join(root, 'dist');
fs.rmSync(out, { recursive: true, force: true });

const common = { bundle: true, sourcemap: env === 'development' ? 'inline' : false, minify: env !== 'development', legalComments: 'none', logLevel: 'warning' };

await build({
  ...common,
  entryPoints: [path.join(root, 'src/main/main.ts')],
  outfile: path.join(out, 'main/main.js'),
  platform: 'node',
  target: 'node22',
  format: 'cjs',
  external: ['electron', 'electron-updater'],
  define: { __OPTIFLOW_CONFIG__: JSON.stringify(cfg) },
});

for (const name of ['shell-preload', 'optiflow-preload', 'medula-preload', 'cevrimdisi-preload']) {
  await build({
    ...common,
    entryPoints: [path.join(root, `src/preload/${name}.ts`)],
    outfile: path.join(out, `preload/${name}.js`),
    platform: 'browser',
    target: 'chrome130',
    format: 'cjs',
    external: ['electron'],
  });
}

await build({
  ...common,
  entryPoints: [path.join(root, 'src/shell/shell.ts')],
  outfile: path.join(out, 'shell/shell.js'),
  platform: 'browser',
  target: 'chrome130',
  format: 'iife',
});
await build({
  ...common,
  entryPoints: [path.join(root, 'src/shell/cevrimdisi.ts')],
  outfile: path.join(out, 'shell/cevrimdisi.js'),
  platform: 'browser',
  target: 'chrome130',
  format: 'iife',
});
for (const f of ['index.html', 'shell.css', 'cevrimdisi.html', 'cevrimdisi.css']) fs.copyFileSync(path.join(root, 'src/shell', f), path.join(out, 'shell', f));
fs.cpSync(path.join(root, 'src/shell/fonts'), path.join(out, 'shell/fonts'), { recursive: true });

console.log(`built OptiFlow Desktop (${env}) → dist/  server=${cfg.optiflowBaseUrl}`);
