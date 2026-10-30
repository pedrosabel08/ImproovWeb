/** CPU apenas no worker/primeiro uso. Cores lineares, ponderadas por ângulo sólido. */
export function processHdri(data, settings, DataUtils) {
  const { width, height } = data;
  const rgb = data.data;
  const mask = new Uint8Array(width * height);
  const luminance = (r, g, b) => r * 0.2126 + g * 0.7152 + b * 0.0722;
  let removedHotspots = 0;
  for (let pixel = 0; pixel < mask.length; pixel++) {
    const i = pixel * 4;
    for (let c = 0; c < 3; c++)
      if (!Number.isFinite(rgb[i + c]) || rgb[i + c] < 0) rgb[i + c] = 0;
    if (
      luminance(rgb[i], rgb[i + 1], rgb[i + 2]) > settings.sky.hotspotThreshold
    ) {
      mask[pixel] = 1;
      removedHotspots++;
    }
  }
  // Reconstrói o campo solar capturado a partir do céu da mesma latitude.
  // O crescimento por contraste inclui raios e halo; evita recortes circulares
  // e preserva o horizonte. Tudo permanece em RGB linear no worker.
  if (removedHotspots) {
    const backgrounds = [];
    for (let y = 0; y < height; y++) {
      const samples = [];
      for (let x = 0; x < width; x += 4) {
        const pixel = y * width + x,
          i = pixel * 4;
        if (!mask[pixel])
          samples.push([
            luminance(rgb[i], rgb[i + 1], rgb[i + 2]),
            rgb[i],
            rgb[i + 1],
            rgb[i + 2],
          ]);
      }
      samples.sort((a, b) => a[0] - b[0]);
      const color = [0, 0, 0];
      let count = 0;
      for (
        let i = Math.floor(samples.length * 0.25);
        i < Math.ceil(samples.length * 0.75);
        i++
      ) {
        for (let c = 0; c < 3; c++) color[c] += samples[i][c + 1];
        count++;
      }
      for (let c = 0; c < 3; c++) color[c] /= Math.max(1, count);
      backgrounds.push(color);
    }
    const queue = new Int32Array(mask.length);
    const neighbors = [
      [-1, 0],
      [1, 0],
      [0, -1],
      [0, 1],
    ];
    const backgroundLuminance = backgrounds.map((color) =>
      Math.max(settings.sky.luminanceFloor, luminance(...color)),
    );
    let head = 0,
      tail = 0;
    for (let pixel = 0; pixel < mask.length; pixel++)
      if (mask[pixel]) {
        queue[tail++] = pixel;
      }
    while (head < tail) {
      const pixel = queue[head++],
        x = pixel % width,
        y = Math.floor(pixel / width);
      for (const [dx, dy] of neighbors) {
        const ny = y + dy,
          nx = (x + dx + width) % width;
        if (ny < 0 || ny >= height) continue;
        const next = ny * width + nx;
        if (mask[next]) continue;
        const i = next * 4,
          base = backgroundLuminance[ny];
        if (luminance(rgb[i], rgb[i + 1], rgb[i + 2]) <= base) continue;
        mask[next] = 1;
        queue[tail++] = next;
      }
    }
    for (let pixel = 0; pixel < mask.length; pixel++) {
      if (!mask[pixel]) continue;
      const i = pixel * 4,
        color = backgrounds[Math.floor(pixel / width)];
      const base = backgroundLuminance[Math.floor(pixel / width)];
      const excess = Math.max(
        0,
        luminance(rgb[i], rgb[i + 1], rgb[i + 2]) - base,
      );
      // Residual de até 5% mantém continuidade no limite do halo sem disco.
      const residual = (base * 0.05) / (base * 0.05 + excess);
      for (let c = 0; c < 3; c++)
        rgb[i + c] = Math.max(0, color[c] + (rgb[i + c] - color[c]) * residual);
    }
  }
  const pixels = new Uint16Array(rgb.length);
  const sums = { sky: [0, 0, 0], ground: [0, 0, 0] },
    weights = { sky: 0, ground: 0 };
  for (let row = 0; row < height; row++) {
    // EXRLoader r180 já inverte scanlines; flipY=false, uv.y alto = hemisfério superior.
    const hemisphere = row >= height / 2 ? "sky" : "ground";
    const weight = Math.sin((Math.PI * (row + 0.5)) / height);
    for (let x = 0; x < width; x++) {
      const i = (row * width + x) * 4;
      const lum = luminance(rgb[i], rgb[i + 1], rgb[i + 2]);
      const scale =
        lum > settings.maxLuminance ? settings.maxLuminance / lum : 1;
      for (let c = 0; c < 3; c++) {
        const value = rgb[i + c] * scale;
        pixels[i + c] = DataUtils.toHalfFloat(value);
        sums[hemisphere][c] += value * weight;
      }
      pixels[i + 3] = DataUtils.toHalfFloat(1);
      weights[hemisphere] += weight;
    }
  }
  for (const key of ["sky", "ground"])
    sums[key] = sums[key].map((value) => value / Math.max(weights[key], 1e-8));
  return {
    pixels,
    hemisphere: sums,
    removedHotspots,
    skyAverage: Math.max(settings.sky.luminanceFloor, luminance(...sums.sky)),
  };
}
