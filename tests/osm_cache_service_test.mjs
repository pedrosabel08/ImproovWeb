import assert from 'node:assert/strict';
import {loadOSM, browserOSMCache, osmCacheKey} from '../MapaPavimento/predio-3d/geo/osm-service.js';

const config = {latitude:-27, longitude:-48, obra_id:1};
const values = new Map();
const storage = {get length(){return values.size;}, key:i=>[...values.keys()][i], getItem:k=>values.get(k), setItem:(k,v)=>values.set(k,v), removeItem:k=>values.delete(k)};
const now = Math.floor(Date.now()/1000), acquired = now-6*86400, expires = acquired+7*86400;
const fetcher = async () => ({ok:true, json:async()=>({sucesso:true, contexto_osm:{payload:{elements:[]}, origin:{latitude:-27,longitude:-48}, acquired_at:acquired, expires_at:expires, stale:false, cache_hit:true}})});
const cache = browserOSMCache(storage);
await loadOSM(config,{cache,fetcher});
const entry = JSON.parse(values.get(osmCacheKey(config)));
assert.equal(entry.savedAt, acquired*1000);
assert.equal(entry.expiresAt, expires*1000);
assert.equal((await loadOSM(config,{cache,fetcher})).cacheHit,true);
await assert.rejects(loadOSM({...config,obra_id:undefined},{fetcher}));
let writes = 0;
await loadOSM({...config,latitude:-26},{cache:{get:()=>null,set:()=>writes++},fetcher:async()=>({ok:true,json:async()=>({sucesso:true,contexto_osm:{payload:{elements:[]},origin:{latitude:-26,longitude:-48},acquired_at:acquired,expires_at:expires,stale:true}})})});
assert.equal(writes,0);
console.log('OSM browser cache: acquisition, expiry, API-only and stale checks passed');
