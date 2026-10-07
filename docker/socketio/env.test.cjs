const { test } = require('node:test');
const assert = require('node:assert/strict');
const { mkdtempSync, writeFileSync, copyFileSync, rmSync } = require('node:fs');
const { tmpdir } = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');

function fixture(t) {
  const directory = mkdtempSync(path.join(tmpdir(), 'socket-env-'));
  t.after(() => rmSync(directory, { recursive: true, force: true }));
  copyFileSync(path.join(__dirname, 'env.cjs'), path.join(directory, 'env.cjs'));
  writeFileSync(path.join(directory, '.env'), 'SOCKET_TOKEN_SECRET=file-secret\nREDIS_HOST=file-host\n');
  return directory;
}
function load(directory, env = {}) {
  return spawnSync(process.execPath, ['-e', `require(${JSON.stringify(path.join(directory, 'env.cjs'))}); process.stdout.write(JSON.stringify([process.env.SOCKET_TOKEN_SECRET, process.env.REDIS_HOST]));`], {
    cwd: tmpdir(), encoding: 'utf8', env: { NODE_PATH: path.join(__dirname, 'node_modules'), ...env },
  });
}
test('loads the Socket.IO .env beside the app when Passenger uses a different cwd', t => {
  const result = load(fixture(t));
  assert.equal(result.status, 0, result.stderr);
  assert.deepEqual(JSON.parse(result.stdout), ['file-secret', 'file-host']);
});
test('cPanel environment values take precedence over the dotenv file', t => {
  const result = load(fixture(t), { SOCKET_TOKEN_SECRET: 'panel-secret' });
  assert.equal(result.status, 0, result.stderr);
  assert.deepEqual(JSON.parse(result.stdout), ['panel-secret', 'file-host']);
});
test('an explicit shared dotenv path loads outside the release directory', t => {
  const directory = fixture(t);
  const external = path.join(directory, 'socket.env');
  writeFileSync(external, 'SOCKET_TOKEN_SECRET=shared-secret\nREDIS_HOST=shared-host\n');
  const result = load(directory, { DOTENV_CONFIG_PATH: external });
  assert.equal(result.status, 0, result.stderr);
  assert.deepEqual(JSON.parse(result.stdout), ['shared-secret', 'shared-host']);
});
