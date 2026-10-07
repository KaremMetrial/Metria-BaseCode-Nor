const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');

test('cPanel archive excludes secrets and dev tools and boots Laravel with cached routes', { skip: !process.env.TEST_RELEASE_ARCHIVE }, t => {
  const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'cpanel-artifact-'));
  t.after(() => fs.rmSync(directory, { recursive: true, force: true }));
  const archive = path.resolve(process.env.TEST_RELEASE_ARCHIVE);
  const listing = spawnSync('tar', ['-tzf', archive], { encoding: 'utf8' });
  assert.equal(listing.status, 0, listing.stderr);
  assert.ok(!listing.stdout.split('\n').some(file => /(^|\/)\.env$/.test(file)), 'release must not contain runtime secrets');
  assert.equal(spawnSync('tar', ['-xzf', archive, '-C', directory]).status, 0);
  for (const absent of ['.git', 'tests', 'vendor/phpunit', 'vendor/laravel/boost', 'docker/socketio/node_modules/socket.io-client']) {
    assert.ok(!fs.existsSync(path.join(directory, absent)), `${absent} must not ship`);
  }
  for (const required of ['vendor/autoload.php', 'public/build/manifest.json', 'docker/socketio/app.js', 'docker/socketio/node_modules/dotenv', 'docker/socketio/public']) {
    assert.ok(fs.existsSync(path.join(directory, required)), `${required} is needed on cPanel`);
  }
  for (const writable of ['storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs']) {
    fs.mkdirSync(path.join(directory, writable), { recursive: true });
  }
  const env = { PATH: process.env.PATH, APP_ENV: 'production', APP_DEBUG: 'false', LOG_CHANNEL: 'stderr', APP_KEY: `base64:${Buffer.alloc(32, 1).toString('base64')}`, CACHE_STORE: 'array' };
  for (const command of ['config:cache', 'route:cache', 'event:cache']) {
    const result = spawnSync(process.env.PHP_BINARY || 'php', ['artisan', command, '--no-interaction'], { cwd: directory, env, encoding: 'utf8' });
    assert.equal(result.status, 0, result.stderr + result.stdout);
  }
  const routes = spawnSync(process.env.PHP_BINARY || 'php', ['artisan', 'route:list', '--path=api/v1/categories', '--json'], { cwd: directory, env, encoding: 'utf8' });
  assert.equal(routes.status, 0, routes.stderr);
  assert.ok(JSON.parse(routes.stdout).some(route => route.uri === 'api/v1/categories'));
});
