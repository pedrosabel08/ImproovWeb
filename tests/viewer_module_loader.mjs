import { readFile } from "node:fs/promises";
const base = new URL("../MapaPavimento/predio-3d/", import.meta.url);

/** Carrega os módulos de produção com a MESMA instância local do Three.js usada pelo teste.
 * O navegador resolve o import "three" pelo import map; Node recebe a URL equivalente. */
export async function loadViewerModule(filename, threeURL) {
  const cache = new Map();
  async function moduleURL(url) {
    if (url.pathname.endsWith(".mjs")) return url.href;
    if (cache.has(url.href)) return cache.get(url.href);
    const pending = (async () => {
      let source = await readFile(url, "utf8");
      const imports = [...source.matchAll(/^import\b[\s\S]*?from\s+(["'])([^"']+)\1\s*;/gm)];
      for (const match of imports) {
        const specifier = match[2];
        let resolved;
        if (specifier === "three") resolved = threeURL;
        else if (specifier.startsWith(".")) resolved = await moduleURL(new URL(specifier, url));
        else throw new Error("Import não suportado pelo teste: " + specifier);
        source = source.replace(match[0], match[0].replace(match[1] + specifier + match[1], JSON.stringify(resolved)));
      }
      return "data:text/javascript;base64," + Buffer.from(source).toString("base64");
    })();
    cache.set(url.href, pending);
    return pending;
  }
  return import(await moduleURL(new URL(filename, base)));
}
