const { test } = require('node:test');
const assert = require('node:assert/strict');
const { spawn } = require('node:child_process');
const { createHmac } = require('node:crypto');
const { io } = require('socket.io-client');
const { createClient } = require('redis');
const path = require('node:path');
const secret = 'integration-only-secret-at-least-32-bytes';
const port = Number(process.env.TEST_SOCKET_PORT || 16091);
function token(sub, expires = Math.floor(Date.now()/1000)+60) {
 const payload=Buffer.from(JSON.stringify({sub,type:'vendor',aud:'metrial-socket',iat:Math.floor(Date.now()/1000),exp:expires})).toString('base64url');
 return `${payload}.${createHmac('sha256',secret).update(payload).digest('hex')}`;
}
function connect(auth) { return new Promise((resolve,reject) => {
 const socket=io(`http://127.0.0.1:${port}`,{transports:['websocket'],reconnection:false,auth,timeout:3000});
 socket.once('connect',()=>resolve(socket));socket.once('connect_error',error=>{socket.close();reject(error);});
});}
test('live socket credentials, room isolation, Redis relay and expiry', {skip:!process.env.TEST_REDIS_PORT,timeout:20000}, async()=>{
 const server=spawn(process.execPath,[path.join(__dirname,'socket.io.config.cjs')],{env:{...process.env,REDIS_HOST:'127.0.0.1',REDIS_PORT:process.env.TEST_REDIS_PORT,REDIS_PREFIX:'metrial_integration_',SOCKET_IO_PORT:String(port),SOCKET_TOKEN_SECRET:secret},stdio:'pipe'});
 let diagnostics='';server.stderr.on('data',b=>diagnostics+=b);
 const redis=createClient({socket:{host:'127.0.0.1',port:Number(process.env.TEST_REDIS_PORT)}});redis.on('error',()=>{});
 const sockets=[];
 try {
  let ready=false;
  for(let i=0;i<50;i++) {try{const r=await fetch(`http://127.0.0.1:${port}/health`);if(r.ok){ready=true;break;}}catch{}await new Promise(r=>setTimeout(r,100));}
  assert.ok(ready,diagnostics);
  await assert.rejects(connect({token:'tampered'}));await assert.rejects(connect({token:token('1',Math.floor(Date.now()/1000)-1)}));
  const own=await connect({token:token('1')});sockets.push(own);const other=await connect({token:token('2')});sockets.push(other);
  const denied=await new Promise(resolve=>own.emit('join:room','private-user.2',resolve));assert.equal(denied.success,false);
  let leaked=false;own.on('notification.created',()=>leaked=true);
  const received=new Promise((resolve,reject)=>{other.once('notification.created',resolve);setTimeout(()=>reject(new Error('relay timed out')),2000).unref();});
  await redis.connect();await redis.publish('metrial_integration_private-user.2',JSON.stringify({event:'notification.created',data:{id:'notification-one',body:'مرحبا'}}));
  assert.equal((await received).id,'notification-one');await new Promise(r=>setTimeout(r,100));assert.equal(leaked,false);
  const expiring=await connect({token:token('3',Math.floor(Date.now()/1000)+2)});sockets.push(expiring);
  await new Promise((resolve,reject)=>{expiring.once('disconnect',resolve);setTimeout(()=>reject(new Error('token did not expire')),4000).unref();});
 } finally {sockets.forEach(s=>s.close());if(redis.isOpen)await redis.quit();server.kill('SIGTERM');await new Promise(resolve=>server.once('exit',resolve));}
});
