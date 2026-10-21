import assert from 'node:assert/strict';
import test from 'node:test';
import {
  isChunkLoadError,
  pageRecoveryUrl,
  reloadOnceForStaleChunks,
} from '../../resources/js/lib/pageRecovery.ts';

test('expired page chunks recover to the requested destination instead of the previous page', () => {
  const error = new TypeError(
    'Failed to fetch dynamically imported module: /build/assets/About-old.js'
  );
  assert.equal(
    pageRecoveryUrl(error, '/about?from=resources', 'http://localhost:8000/#features'),
    'http://localhost:8000/about?from=resources'
  );
});

test('ordinary errors and feature imports cannot reload an in-progress page', () => {
  assert.equal(
    pageRecoveryUrl(new Error('Render failed'), '/about', 'http://localhost:8000/'),
    null
  );
  assert.equal(
    pageRecoveryUrl(
      new TypeError('Failed to fetch dynamically imported module'),
      null,
      'http://localhost:8000/contact'
    ),
    null
  );
});

test('page recovery never navigates to another origin or a script URL', () => {
  const error = new TypeError('Importing a module script failed.');
  for (const destination of [
    'https://example.com/about',
    'javascript:alert(1)',
    '//example.com/about',
  ]) {
    assert.equal(pageRecoveryUrl(error, destination, 'http://localhost:8000/'), null);
  }
});

test('stale chunk and CSS preload failures are recognised across browsers', () => {
  for (const message of [
    'Failed to fetch dynamically imported module: https://example.com/build/assets/Index-old.js',
    'error loading dynamically imported module',
    'Importing a module script failed.',
    'Unable to preload CSS for /build/assets/Index-old.css',
  ]) {
    assert.equal(isChunkLoadError(new TypeError(message)), true, message);
  }
  assert.equal(isChunkLoadError(new Error("Cannot read properties of undefined (reading 'id')")), false);
  assert.equal(isChunkLoadError(null), false);
});

function memoryStorage() {
  const items = new Map();
  return { getItem: (k) => items.get(k) ?? null, setItem: (k, v) => items.set(k, String(v)) };
}

test('a stale chunk triggers exactly one automatic reload, never a loop', () => {
  const storage = memoryStorage();
  let reloads = 0;
  const reload = () => reloads++;
  assert.equal(reloadOnceForStaleChunks({ storage, now: 1_000, reload }), true);
  // The reloaded document fails again within a minute: show the error card instead.
  assert.equal(reloadOnceForStaleChunks({ storage, now: 20_000, reload }), false);
  assert.equal(reloads, 1);
  // A later deploy may recover once more.
  assert.equal(reloadOnceForStaleChunks({ storage, now: 120_000, reload }), true);
  assert.equal(reloads, 2);
});

test('without sessionStorage no automatic reload is attempted', () => {
  let reloads = 0;
  assert.equal(reloadOnceForStaleChunks({ storage: null, reload: () => reloads++ }), false);
  assert.equal(reloads, 0);
});
