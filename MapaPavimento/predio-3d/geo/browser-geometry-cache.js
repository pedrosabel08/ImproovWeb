const MAX_BYTES = 96 * 1024 * 1024;
const MAX_PACKAGES = 16;
let opening;

function database() {
  if (!globalThis.indexedDB) throw Error("IndexedDB unavailable");
  return opening ??= new Promise((resolve, reject) => {
    const request = indexedDB.open("flow-geometry", 1);
    let failed = false;
    request.onupgradeneeded = () => {
      const db = request.result;
      db.createObjectStore("packages");
      db.createObjectStore("entries", { keyPath: "sha" });
      db.createObjectStore("models").createIndex("sha", "");
    };
    request.onerror = request.onblocked = event => {
      failed = true;
      reject(event.type === "blocked" ? Error("IndexedDB blocked") : request.error);
    };
    request.onsuccess = () => {
      const db = request.result;
      if (failed) {
        db.close();
        return;
      }
      db.onversionchange = db.onclose = () => {
        db.close();
        opening = undefined;
      };
      resolve(db);
    };
  }).catch(error => {
    opening = undefined;
    throw error;
  });
}

function value(request) {
  return new Promise((resolve, reject) => {
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
  });
}

async function transaction(names, mode, run) {
  const db = await database();
  const tx = db.transaction(names, mode);
  const done = new Promise((resolve, reject) => {
    tx.oncomplete = resolve;
    tx.onabort = tx.onerror = () => reject(tx.error ?? Error("IndexedDB transaction failed"));
  });
  done.catch(() => {});
  try {
    const result = await run(...names.map(name => tx.objectStore(name)));
    await done;
    return result;
  } catch (error) {
    try { tx.abort(); } catch { /* already closed */ }
    throw error;
  }
}

export async function localGeometryCandidate(scope) {
  return transaction(["models", "entries"], "readonly", async (models, entries) => {
    const sha = await value(models.get(scope));
    return sha && await value(entries.getKey(sha)) ? sha : null;
  });
}

export function readLocalGeometry(sha) {
  return transaction(["packages"], "readonly", packages => value(packages.get(sha)));
}

export function touchLocalGeometry(sha) {
  return transaction(["entries"], "readwrite", async entries => {
    const entry = await value(entries.get(sha));
    if (entry) entries.put({ ...entry, used: Date.now() });
  });
}

async function remove(packages, entries, models, sha) {
  packages.delete(sha);
  entries.delete(sha);
  const scopes = await value(models.index("sha").getAllKeys(sha));
  for (const scope of scopes) models.delete(scope);
}

export function removeLocalGeometry(sha) {
  return transaction(["packages", "entries", "models"], "readwrite",
    (packages, entries, models) => remove(packages, entries, models, sha));
}

export function saveLocalGeometry(scope, sha, buffer) {
  if (buffer.byteLength > MAX_BYTES) return Promise.resolve();
  return transaction(["packages", "entries", "models"], "readwrite", async (packages, entries, models) => {
    const list = (await value(entries.getAll())).filter(entry => entry.sha !== sha);
    let bytes = list.reduce((sum, entry) => sum + entry.size, buffer.byteLength);
    list.sort((a, b) => a.used - b.used);
    while (bytes > MAX_BYTES || list.length >= MAX_PACKAGES) {
      const entry = list.shift();
      bytes -= entry.size;
      await remove(packages, entries, models, entry.sha);
    }
    if (!await value(entries.getKey(sha))) packages.put(buffer, sha);
    entries.put({ sha, size: buffer.byteLength, used: Date.now() });
    models.put(sha, scope);
  });
}

/** A slow/blocked browser database must not delay the conventional path. */
export async function boundedLocalGeometry(operation, fallback, timeoutMs = 100) {
  let timer;
  try {
    return await Promise.race([
      operation(),
      new Promise(resolve => { timer = setTimeout(() => resolve(fallback), timeoutMs); }),
    ]);
  } catch {
    return fallback;
  } finally {
    clearTimeout(timer);
  }
}
