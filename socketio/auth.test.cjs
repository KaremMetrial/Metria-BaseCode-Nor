const { test } = require('node:test');
const assert = require('node:assert/strict');
const { createHmac } = require('node:crypto');
const { verifyToken } = require('./auth.cjs');
const secret = 'test-only-secret-of-at-least-32-bytes';
function token(claims) {
  const data = Buffer.from(JSON.stringify(claims)).toString('base64url');
  return `${data}.${createHmac('sha256', secret).update(data).digest('hex')}`;
}
const claims = { sub: '12', type: 'vendor', aud: 'metrial-socket', iat: 1000, exp: 1120 };
test('valid token scopes room to verified subject', () => assert.equal(verifyToken(token({ ...claims, room: 'private-user.99' }), secret, 1001).room, 'private-user.12'));
test('expired token rejected at boundary', () => assert.throws(() => verifyToken(token(claims), secret, 1120)));
test('tampered token rejected', () => assert.throws(() => verifyToken(token(claims).replace(/.$/, 'z'), secret, 1001)));
test('wrong audience rejected', () => assert.throws(() => verifyToken(token({ ...claims, aud: 'other' }), secret, 1001)));
test('missing and malformed tokens rejected', () => [null, '', 'abc', 'a.b', 22].forEach(t => assert.throws(() => verifyToken(t, secret, 1001))));
test('oversized lifespan rejected', () => assert.throws(() => verifyToken(token({ ...claims, exp: 1400 }), secret, 1001)));
