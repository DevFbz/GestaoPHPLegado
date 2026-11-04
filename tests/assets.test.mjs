import assert from 'node:assert/strict';
import { existsSync, readFileSync, statSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import test from 'node:test';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const assets = [
  'public/assets/vendor/bootstrap/bootstrap.min.css',
  'public/assets/vendor/bootstrap/bootstrap.bundle.min.js',
  'public/assets/vendor/bootstrap-icons/bootstrap-icons.min.css',
  'public/assets/vendor/bootstrap-icons/fonts/bootstrap-icons.woff2',
  'public/assets/vendor/alpinejs/alpine.min.js',
  'public/assets/vendor/chartjs/chart.umd.js',
  'public/assets/vendor/signature_pad/signature_pad.umd.min.js',
  'public/assets/vendor/jsqr/jsQR.js',
];

test('required browser vendor assets exist locally', () => {
  for (const asset of assets) {
    const path = resolve(root, asset);
    assert.ok(existsSync(path), `${asset} is missing`);
    assert.ok(statSync(path).isFile() && statSync(path).size > 0, `${asset} is empty`);
  }
});

test('Bootstrap and Bootstrap Icons contain the styles and font used by the UI', () => {
  const bootstrap = readFileSync(resolve(root, assets[0]), 'utf8');
  const icons = readFileSync(resolve(root, assets[2]), 'utf8');
  assert.match(bootstrap, /\.d-none/);
  assert.match(bootstrap, /\.offcanvas/);
  assert.match(icons, /bootstrap-icons\.woff2/);
});
