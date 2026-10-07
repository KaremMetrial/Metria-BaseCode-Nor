const { createHmac, timingSafeEqual } = require('node:crypto');

function verifyToken(token, secret, now = Math.floor(Date.now() / 1000)) {
  if (typeof secret !== 'string' || Buffer.byteLength(secret) < 32 || typeof token !== 'string' || token.length > 2048) throw new Error('UNAUTHENTICATED');
  const parts = token.split('.');
  if (parts.length !== 2 || !/^[A-Za-z0-9_-]+$/.test(parts[0]) || !/^[a-f0-9]{64}$/.test(parts[1])) throw new Error('UNAUTHENTICATED');
  const expected = createHmac('sha256', secret).update(parts[0]).digest();
  if (!timingSafeEqual(expected, Buffer.from(parts[1], 'hex'))) throw new Error('UNAUTHENTICATED');
  const claims = JSON.parse(Buffer.from(parts[0], 'base64url').toString('utf8'));
  if (claims.aud !== 'metrial-socket' || !/^[1-9][0-9]*$/.test(claims.sub) || !['admin', 'client', 'vendor'].includes(claims.type)
      || !Number.isInteger(claims.exp) || !Number.isInteger(claims.iat) || claims.exp <= now || claims.iat > now + 5 || claims.exp - claims.iat > 300) throw new Error('UNAUTHENTICATED');
  return { ...claims, room: `private-user.${claims.sub}` };
}
module.exports = { verifyToken };
