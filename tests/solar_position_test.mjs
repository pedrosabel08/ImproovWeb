import assert from 'node:assert/strict';
import { solarAt, solarNow, solarLocalTime, solarDirection, solarIntensity, trueAzimuth, solarConfigError } from '../MapaPavimento/predio-3d/solar-position.mjs';
import { northDirection } from '../MapaPavimento/predio-3d/north-direction.js';
const config = { latitude: -27.0992256, longitude: -48.6179817, norte_graus: 83.9, timezone: 'America/Sao_Paulo' };
const close = (a, b, message) => assert.ok(Math.abs(a-b) < 1e-10, message);
for (const [raw, expected] of [[0,180],[-Math.PI/2,90],[Math.PI/2,270],[Math.PI,0],[-Math.PI,0]]) close(trueAzimuth(raw), expected, 'Conversão sul/oeste → norte/leste.');
for (const north of [0,32.5,83.9,90,180,270,359.9]) {
  const n = northDirection(north), east = { x: -n.z, z: n.x };
  for (const azimuth of [0,45,90,180,270,359.9]) {
    const v = solarDirection(azimuth, Math.PI/6, north);
    close(Math.hypot(v.x,v.y,v.z), 1, 'Direção solar não normalizada.');
    close(v.y, .5, 'Y vertical incorreto.');
    if (azimuth===0) { close(v.x, n.x*Math.cos(Math.PI/6)); close(v.z,n.z*Math.cos(Math.PI/6)); }
    if (azimuth===90) { close(v.x,east.x*Math.cos(Math.PI/6)); close(v.z,east.z*Math.cos(Math.PI/6)); }
  }
}
const morning = solarAt(config,'2026-10-13','09:00');
const noon = solarAt(config,'2026-10-13','12:00');
const afternoon = solarAt(config,'2026-10-13','16:00');
const night = solarAt(config,'2026-10-13','02:00');
assert.equal(morning.utcISO,'2026-10-13T12:00:00.000Z');
assert.ok(morning.azimuth>45 && morning.azimuth<100 && afternoon.azimuth>260 && afternoon.azimuth<310, 'Manhã deve vir de leste e tarde de oeste.');
assert.ok(noon.altitude>morning.altitude && noon.altitude>afternoon.altitude && noon.altitude>65, 'Meio-dia deve ser mais elevado.');
const north = northDirection(config.norte_graus), east = { x:-north.z, z:north.x };
assert.ok(morning.direction.x*east.x + morning.direction.z*east.z > 0, 'Sol da manhã ficou do lado oeste do GLB.');
assert.ok(afternoon.direction.x*east.x + afternoon.direction.z*east.z < 0, 'Sol da tarde ficou do lado leste do GLB.');
assert.ok(night.altitude<0);
assert.equal(night.intensity,0);
assert.equal(solarIntensity(0),0);
assert.equal(solarIntensity(-.1),0);
assert.ok(solarIntensity(.01)<solarIntensity(.3) && solarIntensity(.3)<solarIntensity(Math.PI/2));
close(solarIntensity(Math.PI/2),2.8);
const changedNorth = solarAt({...config,norte_graus:0},'2026-10-13','09:00');
assert.equal(changedNorth.azimuth,morning.azimuth);
assert.equal(changedNorth.altitude,morning.altitude);
assert.notEqual(changedNorth.direction.x,morning.direction.x);
assert.equal(solarConfigError({...config,norte_graus:0}), '');
for (const invalid of [{norte_graus:null},{latitude:null},{latitude:91},{longitude:-181},{timezone:'America/Invalid'}]) assert.ok(solarConfigError({...config,...invalid}));
assert.throws(()=>solarLocalTime('2026-02-30','12:00',config.timezone));
assert.throws(()=>solarLocalTime('2026-10-13','24:00',config.timezone));
assert.throws(()=>solarLocalTime('2026-03-08','02:30','America/New_York'),/não existe/);
const repeated = solarLocalTime('2026-11-01','01:30','America/New_York');
assert.equal(repeated.ambiguous,true);
assert.equal(repeated.utcISO,'2026-11-01T05:30:00.000Z');
assert.equal(solarLocalTime('2026-10-13','12:00','Europe/Lisbon').utcISO,'2026-10-13T11:00:00.000Z');
assert.deepEqual(solarNow('Asia/Tokyo', Date.parse('2026-10-13T23:00:00Z')), { date:'2026-10-14',time:'08:00' });
console.log('OK: azimute, Norte salvo, vetor unitário, leste/oeste, intensidade, noite, UTC, outros fusos e transições de horário de verão.');
console.log(JSON.stringify([morning,noon,afternoon,night].map(s=>({utc:s.utcISO,azimuth:s.azimuth,altitude:s.altitude,intensity:s.intensity}))));
