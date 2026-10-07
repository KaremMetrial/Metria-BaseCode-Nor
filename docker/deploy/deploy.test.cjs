const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const { createHash } = require('node:crypto');
const { gunzipSync } = require('node:zlib');
const script = path.join(__dirname, 'deploy.sh');
const releaseId = `${'a'.repeat(40)}-123-1`;
const previousId = `${'b'.repeat(40)}-122-1`;
function fixture(t, failure = '') {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'cpanel-deploy-'));
  t.after(() => fs.rmSync(root, { recursive: true, force: true }));
  for (const directory of ['shared', 'bin', `incoming/${releaseId}`, `releases/${previousId}/docker/socketio`, `releases/${previousId}/public`, 'payload/docker/socketio', 'payload/public']) {
    fs.mkdirSync(path.join(root, directory), { recursive: true });
  }
  fs.writeFileSync(path.join(root, 'payload/artisan'), 'fixture');
  fs.writeFileSync(path.join(root, `releases/${previousId}/artisan`), 'fixture');
  fs.symlinkSync(path.join(root, `releases/${previousId}`), path.join(root, 'current'));
  for (const file of ['.env', 'socket.env', 'mysql-backup.cnf']) fs.writeFileSync(path.join(root, 'shared', file), 'fixture');
  fs.writeFileSync(path.join(root, 'shared/deploy.conf'), `PHP_BIN=${root}/bin/php\nMYSQLDUMP_BIN=${root}/bin/mysqldump\nDB_DATABASE=fixture\nAPP_HEALTH_URL=https://app.invalid/up\nSOCKET_HEALTH_URL=https://socket.invalid/health\n`);
  fs.writeFileSync(path.join(root, 'bin/php'), `#!/usr/bin/env bash\nprintf '%s\\n' "$2" >> "$TEST_ROOT/calls"\nif [[ "$2" == "$FAILURE" ]]; then exit 42; fi\n`, { mode: 0o755 });
  fs.writeFileSync(path.join(root, 'bin/mysqldump'), '#!/usr/bin/env bash\necho backup >> "$TEST_ROOT/calls"\n[[ "$FAILURE" != backup ]] || exit 42\necho "CREATE TABLE fixture(id INT);"\n', { mode: 0o755 });
  fs.writeFileSync(path.join(root, 'bin/curl'), '#!/usr/bin/env bash\n[[ "$FAILURE" != health ]]\n', { mode: 0o755 });
  const archive = path.join(root, `incoming/${releaseId}/release.tar.gz`);
  assert.equal(spawnSync('tar', ['-czf', archive, '-C', path.join(root, 'payload'), '.']).status, 0);
  const checksum = createHash('sha256').update(fs.readFileSync(archive)).digest('hex');
  fs.writeFileSync(`${archive}.sha256`, `${checksum}  release.tar.gz\n`);
  return { root, run: (mode = 'deploy', id = releaseId) => spawnSync('bash', [script, root, id, mode], {
    encoding: 'utf8', env: { ...process.env, TEST_ROOT: root, FAILURE: failure, PATH: `${root}/bin:${process.env.PATH}` },
  }), calls: () => fs.readFileSync(path.join(root, 'calls'), 'utf8').trim().split('\n') };
}
test('deployment backs up before migration, activates code and preserves shared state', t => {
  const fixtureData = fixture(t);
  const { root, run, calls } = fixtureData;
  const result = run();
  assert.equal(result.status, 0, result.stderr);
  assert.equal(fs.readlinkSync(path.join(root, 'current')), path.join(root, `releases/${releaseId}`));
  assert.equal(fs.readlinkSync(path.join(root, 'previous')), path.join(root, `releases/${previousId}`));
  assert.equal(fs.readlinkSync(path.join(root, `releases/${releaseId}/.env`)), path.join(root, 'shared/.env'));
  assert.equal(fs.readlinkSync(path.join(root, `releases/${releaseId}/storage`)), path.join(root, 'shared/storage'));
  assert.match(gunzipSync(fs.readFileSync(path.join(root, `backups/${releaseId}.sql.gz`))).toString(), /CREATE TABLE fixture/);
  assert.ok(calls().indexOf('backup') < calls().indexOf('migrate'));
  assert.ok(fs.existsSync(path.join(root, 'shared/socket-tmp/restart.txt')));
});
for (const failure of ['backup', 'migrate']) test(`${failure} failure leaves old release selected and maintenance enabled`, t => {
  const { root, run, calls } = fixture(t, failure);
  assert.notEqual(run().status, 0);
  assert.equal(fs.readlinkSync(path.join(root, 'current')), path.join(root, `releases/${previousId}`));
  assert.ok(!calls().includes('up'));
  assert.ok(!calls().includes('migrate:rollback'));
  if (failure === 'backup') assert.ok(!calls().includes('migrate'));
});
test('failed public health check retains the recovery pointer and re-enters maintenance', t => {
  const { root, run, calls } = fixture(t, 'health');
  assert.notEqual(run().status, 0);
  assert.equal(fs.readlinkSync(path.join(root, 'previous')), path.join(root, `releases/${previousId}`));
  assert.equal(calls().at(-1), 'down');
});
test('manual rollback activates existing code without reversing the database', t => {
  const { root, run, calls } = fixture(t);
  const result = run('rollback', previousId);
  assert.equal(result.status, 0, result.stderr);
  assert.equal(fs.readlinkSync(path.join(root, 'current')), path.join(root, `releases/${previousId}`));
  assert.ok(!calls().includes('backup'));
  assert.ok(!calls().includes('migrate'));
  assert.ok(!calls().includes('migrate:rollback'));
});
test('a corrupted release fails before maintenance or migration', t => {
  const { root, run } = fixture(t);
  fs.appendFileSync(path.join(root, `incoming/${releaseId}/release.tar.gz`), 'corrupt');
  assert.notEqual(run().status, 0);
  assert.ok(!fs.existsSync(path.join(root, 'calls')));
  assert.equal(fs.readlinkSync(path.join(root, 'current')), path.join(root, `releases/${previousId}`));
});
