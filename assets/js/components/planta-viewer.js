/* PDF.js + SVG. Geometria relativa à área útil, independente de zoom e resolução.
 * Extraído da abordagem do MapaCompatibilizacao, com viewport e render próprios. */
(() => {
  const ns = "http://www.w3.org/2000/svg";
  const svgElement = (tag, attributes = {}) => {
    const element = document.createElementNS(ns, tag);
    Object.entries(attributes).forEach(([key, value]) =>
      element.setAttribute(key, value),
    );
    return element;
  };
  class PlantaViewer extends EventTarget {
    constructor(container) {
      super();
      this.container = container;
      this.surface = container.querySelector(".pv-surface");
      this.canvas = container.querySelector("canvas");
      this.svg = container.querySelector("svg");
      this.regions = svgElement("g");
      this.draft = svgElement("g");
      this.svg.append(this.regions, this.draft);
      this.scale = 1;
      this.rotation = 0;
      this.x = 0;
      this.y = 0;
      this.width = 0;
      this.height = 0;
      this.pointers = new Map();
      this.environments = [];
      this.generation = 0;
      this.controller = new AbortController();
      const options = { signal: this.controller.signal };
      container.addEventListener(
        "wheel",
        (e) => {
          if (!this.page) return;
          e.preventDefault();
          this.zoomAt(e.clientX, e.clientY, Math.exp(-e.deltaY * 0.0015));
        },
        { ...options, passive: false },
      );
      container.addEventListener(
        "pointerdown",
        (e) => this.pointerDown(e),
        options,
      );
      container.addEventListener(
        "pointermove",
        (e) => this.pointerMove(e),
        options,
      );
      container.addEventListener(
        "pointerup",
        (e) => this.pointerUp(e),
        options,
      );
      container.addEventListener(
        "pointercancel",
        () => this.cancelPointers(),
        options,
      );
      container.addEventListener(
        "lostpointercapture",
        (e) => {
          if (this.pointers.has(e.pointerId)) this.cancelPointers();
        },
        options,
      );
      container.addEventListener(
        "keydown",
        (e) => {
          if (e.code === "Space") {
            e.preventDefault();
            this.space = true;
          }
          if (e.key === "+" || e.key === "=") {
            e.preventDefault();
            this.zoom(1.25);
          }
          if (e.key === "-") {
            e.preventDefault();
            this.zoom(0.8);
          }
          if (e.key === "0") {
            e.preventDefault();
            this.fit();
          }
        },
        options,
      );
      container.addEventListener(
        "keyup",
        (e) => {
          if (e.code === "Space") this.space = false;
        },
        options,
      );
      container.addEventListener(
        "blur",
        () => {
          this.space = false;
        },
        options,
      );
      container.addEventListener(
        "contextmenu",
        (e) => e.preventDefault(),
        options,
      );
      this.regions.addEventListener(
        "keydown",
        (e) => {
          if (e.key === "Enter" || e.key === " ") {
            const region = e.target.closest("[data-environment]");
            if (region) {
              e.preventDefault();
              e.stopPropagation();
              this.select(Number(region.dataset.environment));
            }
          }
        },
        options,
      );
      this.regions.addEventListener(
        "pointerover",
        (e) => this.hover(e),
        options,
      );
      this.regions.addEventListener(
        "pointermove",
        (e) => this.hover(e),
        options,
      );
      this.regions.addEventListener(
        "pointerleave",
        () =>
          this.dispatchEvent(
            new CustomEvent("environmenthover", { detail: null }),
          ),
        options,
      );
      this.resizeObserver = new ResizeObserver(() => {
        if (!this.page) return;
        if (this.fitted) this.fit();
        else this.transform();
      });
      this.resizeObserver.observe(container);
    }
    async load(url, configuration = {}, sourceTask = null) {
      this.clear();
      const generation = this.generation;
      this.loadingTask = sourceTask || window.pdfjsLib.getDocument({ url });
      const document = await this.loadingTask.promise;
      if (generation !== this.generation) {
        await document.destroy();
        return false;
      }
      this.document = document;
      const pageNumber = Number(configuration.pagina || 1);
      if (pageNumber < 1 || pageNumber > document.numPages) throw new Error('Página não encontrada no documento.');
      const page = await document.getPage(pageNumber);
      if (generation !== this.generation) return false;
      this.page = page;
      this.rotation = 0;
      const viewport = page.getViewport({ scale: 1 });
      this.pageWidth = viewport.width;
      this.pageHeight = viewport.height;
      const optionalContent = await document.getOptionalContentConfig();
      if (generation !== this.generation) return false;
      this.optionalContent = optionalContent;
      this.layerCatalog = Object.entries(optionalContent.getGroups() || {}).map(([id, group]) => ({ id, name: group.name || id }));
      this.setLayers(configuration.layers);
      this.setCrop(configuration.area_util);
      this.surface.hidden = false;
      this.fit();
      await this.renderPdf();
      return generation === this.generation;
    }
    clear() {
      this.generation++;
      clearTimeout(this.renderTimer);
      this.renderTask?.cancel();
      this.renderTask = null;
      // PDF.js destroy also cancels an unfinished download.
      const oldTask = this.loadingTask;
      this.loadingTask = null;
      if (oldTask) Promise.resolve(oldTask.destroy()).catch(() => {});
      this.document = null;
      this.page = null;
      this.rotation = 0;
      this.optionalContent = null;
      this.layerCatalog = [];
      this.crop = null;
      this.area = null;
      this.surface.style.clipPath = '';
      this.rectangleMode = false;
      this.environments = [];
      this.regions.replaceChildren();
      this.draft.replaceChildren();
      this.surface.hidden = true;
      this.selectedId = null;
      this.setDrawing(false);
      this.cancelPointers();
    }
    async renderPdf() {
      if (!this.page) return;
      clearTimeout(this.renderTimer);
      const generation = this.generation;
      this.renderTask?.cancel();
      const density = Math.min(
        4,
        Math.max(0.5, this.scale * Math.min(window.devicePixelRatio || 1, 2)),
        8192 / Math.max(this.width, this.height),
        Math.sqrt(16000000 / (this.width * this.height)),
      );
      const viewport = this.page.getViewport({
        scale: density,
        rotation: ((this.page.rotate || 0) + this.rotation) % 360,
      });
      const buffer = document.createElement("canvas");
      buffer.width = Math.ceil(this.width * density);
      buffer.height = Math.ceil(this.height * density);
      const crop = this.displayCrop || { x: 0, y: 0, width: 1, height: 1 };
      const task = this.page.render({
        canvasContext: buffer.getContext("2d"),
        viewport,
        transform: [1, 0, 0, 1, -crop.x * viewport.width, -crop.y * viewport.height],
        optionalContentConfigPromise: Promise.resolve(this.optionalContent),
      });
      this.renderTask = task;
      try {
        await task.promise;
        if (generation !== this.generation || this.renderTask !== task) return;
        this.canvas.width = buffer.width;
        this.canvas.height = buffer.height;
        this.canvas.getContext("2d").drawImage(buffer, 0, 0);
      } catch (error) {
        if (error.name !== "RenderingCancelledException") throw error;
      } finally {
        if (this.renderTask === task) this.renderTask = null;
      }
    }
    getLayers() {
      return { version: 1, groups: (this.layerCatalog || []).map(layer => ({ ...layer, visible: this.optionalContent.getGroup(layer.id).visible })) };
    }
    setLayers(configuration) {
      this.renderTask?.cancel();
      const visibility = new Map((configuration?.groups || []).map(group => [group.id, group.visible]));
      (this.layerCatalog || []).forEach(layer => this.optionalContent.setVisibility(layer.id, visibility.get(layer.id) ?? true));
    }
    setCrop(crop = null) {
      if (!this.page) return;
      this.renderTask?.cancel();
      this.area = crop?.points ? crop : (crop ? { points: [[crop.x,crop.y],[crop.x+crop.width,crop.y],[crop.x+crop.width,crop.y+crop.height],[crop.x,crop.y+crop.height]] } : null);
      const points = this.area?.points;
      const xs=points?.map(p=>p[0]), ys=points?.map(p=>p[1]);
      this.crop = points ? { x:Math.min(...xs), y:Math.min(...ys), width:Math.max(...xs)-Math.min(...xs), height:Math.max(...ys)-Math.min(...ys) } : null;
      this.updateDisplayGeometry();
      this.fit();
    }
    rotate(degrees = 90) {
      if (!this.page) return;
      this.rotation = ((this.rotation + degrees) % 360 + 360) % 360;
      this.updateDisplayGeometry();
      this.setEnvironments(this.environments);
      this.fit();
    }
    rotatePagePoint(point) {
      switch (this.rotation) {
        case 90: return [1 - point[1], point[0]];
        case 180: return [1 - point[0], 1 - point[1]];
        case 270: return [point[1], 1 - point[0]];
        default: return point;
      }
    }
    updateDisplayGeometry() {
      const source = this.crop || { x: 0, y: 0, width: 1, height: 1 };
      const corners = [
        [source.x, source.y],
        [source.x + source.width, source.y],
        [source.x + source.width, source.y + source.height],
        [source.x, source.y + source.height],
      ].map(point => this.rotatePagePoint(point));
      const xs = corners.map(point => point[0]), ys = corners.map(point => point[1]);
      this.displayCrop = {
        x: Math.min(...xs), y: Math.min(...ys),
        width: Math.max(...xs) - Math.min(...xs),
        height: Math.max(...ys) - Math.min(...ys),
      };
      const swapsAxes = this.rotation % 180 !== 0;
      const pageWidth = swapsAxes ? this.pageHeight : this.pageWidth;
      const pageHeight = swapsAxes ? this.pageWidth : this.pageHeight;
      this.width = pageWidth * this.displayCrop.width;
      this.height = pageHeight * this.displayCrop.height;
      this.surface.style.width = `${this.width}px`;
      this.surface.style.height = `${this.height}px`;
      this.svg.setAttribute('viewBox', `0 0 ${this.width} ${this.height}`);
      this.surface.style.clipPath = this.area
        ? 'polygon('+this.area.points.map(point=>{
          const rotated=this.rotatePagePoint(point);
          return ((rotated[0]-this.displayCrop.x)/this.displayCrop.width*100)+'% '+((rotated[1]-this.displayCrop.y)/this.displayCrop.height*100)+'%';
        }).join(',')+')'
        : '';
    }
    toDisplayPixels(point) {
      const crop = this.crop || { x: 0, y: 0, width: 1, height: 1 };
      const pagePoint = [crop.x + point[0] * crop.width, crop.y + point[1] * crop.height];
      const rotated = this.rotatePagePoint(pagePoint);
      return [
        ((rotated[0] - this.displayCrop.x) / this.displayCrop.width) * this.width,
        ((rotated[1] - this.displayCrop.y) / this.displayCrop.height) * this.height,
      ];
    }
    containsPoint(point) {
      if (!point.every(value=>value>=0 && value<=1)) return false;
      if (!this.area) return true;
      return window.plantaPointInPolygon([this.crop.x+point[0]*this.crop.width,this.crop.y+point[1]*this.crop.height],this.area.points);
    }
    transform() {
      this.surface.style.transform = `translate(${this.x}px,${this.y}px) scale(${this.scale})`;
      this.updateLabels();
      this.dispatchEvent(
        new CustomEvent("viewchange", { detail: { scale: this.scale } }),
      );
      clearTimeout(this.renderTimer);
      this.renderTimer = setTimeout(
        () =>
          this.renderPdf().catch((error) =>
            this.dispatchEvent(
              new CustomEvent("viewererror", { detail: error }),
            ),
          ),
        180,
      );
    }
    fit() {
      if (!this.page) return;
      this.scale = Math.min(
        (this.container.clientWidth - 40) / this.width,
        (this.container.clientHeight - 40) / this.height,
      );
      this.scale = Math.max(0.001, this.scale);
      this.x = (this.container.clientWidth - this.width * this.scale) / 2;
      this.y = (this.container.clientHeight - this.height * this.scale) / 2;
      this.fitted = true;
      this.transform();
    }
    reset() {
      this.fit();
    }
    zoom(factor) {
      const rect = this.container.getBoundingClientRect();
      this.zoomAt(
        rect.left + rect.width / 2,
        rect.top + rect.height / 2,
        factor,
      );
    }
    zoomAt(clientX, clientY, factor) {
      if (!this.page) return;
      const rect = this.container.getBoundingClientRect();
      const fitScale = Math.min(
        this.container.clientWidth / this.width,
        this.container.clientHeight / this.height,
      );
      const scale = Math.max(
        fitScale * 0.2,
        Math.min(Math.max(6, fitScale * 12), this.scale * factor),
      );
      const localX = clientX - rect.left,
        localY = clientY - rect.top;
      this.x = localX - ((localX - this.x) * scale) / this.scale;
      this.y = localY - ((localY - this.y) * scale) / this.scale;
      this.scale = scale;
      this.fitted = false;
      this.transform();
    }
    toPoint(clientX, clientY) {
      const rect = this.surface.getBoundingClientRect();
      const display = [
        (clientX - rect.left) / rect.width,
        (clientY - rect.top) / rect.height,
      ];
      const point = [
        this.displayCrop.x + display[0] * this.displayCrop.width,
        this.displayCrop.y + display[1] * this.displayCrop.height,
      ];
      let pagePoint;
      switch (this.rotation) {
        case 90: pagePoint = [point[1], 1 - point[0]]; break;
        case 180: pagePoint = [1 - point[0], 1 - point[1]]; break;
        case 270: pagePoint = [1 - point[1], point[0]]; break;
        default: pagePoint = point;
      }
      const crop = this.crop || { x: 0, y: 0, width: 1, height: 1 };
      return [(pagePoint[0] - crop.x) / crop.width, (pagePoint[1] - crop.y) / crop.height];
    }
    toScreen(point) {
      const rect = this.surface.getBoundingClientRect();
      const [x, y] = this.toDisplayPixels(point);
      return [
        rect.left + (x / this.width) * rect.width,
        rect.top + (y / this.height) * rect.height,
      ];
    }
    pointerDown(e) {
      if (!this.page || (e.button !== 0 && e.button !== 1)) return;
      e.preventDefault();
      this.container.focus({ preventScroll: true });
      this.container.setPointerCapture(e.pointerId);
      this.pointers.set(e.pointerId, [e.clientX, e.clientY]);
      if (this.pointers.size === 2) {
        this.dispatchEvent(new Event('rectanglecancel'));
        this.drag.moved = true;
        this.drag.pinched = true;
        this.pinchDistance = this.pointerDistance();
        return;
      }
      this.drag = {
        startX: e.clientX,
        startY: e.clientY,
        x: this.x,
        y: this.y,
        moved: false,
        pan: !this.drawing || e.button === 1 || this.space,
        target: e.target,
      };
      const vertex=e.target.closest?.('[data-vertex]');
      if (this.drawing && vertex && !this.drag.pan) {
        this.drag.vertex=Number(vertex.dataset.vertex);
        this.dispatchEvent(new CustomEvent('vertexstart',{detail:this.drag.vertex}));
      }
      if (this.rectangleMode && !this.drag.pan) {
        const point = this.toPoint(e.clientX, e.clientY);
        this.drag.rectangle = point.every(value => value >= 0 && value <= 1);
        if (this.drag.rectangle) this.dispatchEvent(new CustomEvent('rectanglestart', { detail: point }));
      }
    }
    pointerDistance() {
      const [a, b] = [...this.pointers.values()];
      return Math.hypot(a[0] - b[0], a[1] - b[1]);
    }
    pointerMove(e) {
      if (!this.pointers.has(e.pointerId) || !this.drag) return;
      this.pointers.set(e.pointerId, [e.clientX, e.clientY]);
      if (this.pointers.size >= 2) {
        const [a, b] = [...this.pointers.values()];
        const distance = this.pointerDistance();
        if (this.pinchDistance > 0)
          this.zoomAt(
            (a[0] + b[0]) / 2,
            (a[1] + b[1]) / 2,
            distance / this.pinchDistance,
          );
        this.pinchDistance = distance;
        return;
      }
      const dx = e.clientX - this.drag.startX,
        dy = e.clientY - this.drag.startY;
      if (Math.hypot(dx, dy) > 4) this.drag.moved = true;
      if (this.drag.pan && !this.drag.pinched && this.drag.moved) {
        this.x = this.drag.x + dx;
        this.y = this.drag.y + dy;
        this.fitted = false;
        this.transform();
      }
      if (this.drag.rectangle && !this.drag.pinched) this.dispatchEvent(new CustomEvent('rectanglemove', {
        detail: this.toPoint(e.clientX, e.clientY).map(value => Math.max(0, Math.min(1, value)))
      }));
      if (this.drag.vertex!==undefined && !this.drag.pinched) this.dispatchEvent(new CustomEvent('vertexmove',{detail:{index:this.drag.vertex,point:this.toPoint(e.clientX,e.clientY).map(v=>Math.max(0,Math.min(1,v)))}}));
    }
    pointerUp(e) {
      if (!this.pointers.has(e.pointerId)) return;
      this.pointers.delete(e.pointerId);
      if (this.drag?.rectangle && !this.drag.pinched) this.dispatchEvent(new Event('rectangleend'));
      if (
        this.drag &&
        this.drag.vertex===undefined &&
        !this.rectangleMode &&
        !this.drag.moved &&
        !this.drag.pinched &&
        e.button === 0
      ) {
        const point = this.toPoint(e.clientX, e.clientY);
        if (this.drawing && !this.space) {
          if (this.containsPoint(point))
            this.dispatchEvent(
              new CustomEvent("plantaclick", {
                detail: { point, clientX: e.clientX, clientY: e.clientY },
              }),
            );
        } else {
          const region = this.drag.target.closest?.("[data-environment]");
          if (region) this.select(Number(region.dataset.environment));
        }
      }
      if (!this.pointers.size) this.drag = null;
      if (this.container.hasPointerCapture(e.pointerId))
        this.container.releasePointerCapture(e.pointerId);
    }
    cancelPointers() {
      this.dispatchEvent(new Event('rectanglecancel'));
      this.pointers.clear();
      this.drag = null;
      this.pinchDistance = 0;
    }
    setDrawing(active) {
      this.drawing = active;
      this.container.classList.toggle("is-drawing", active);
      this.regions.querySelectorAll('[data-environment]').forEach(region => region.setAttribute('tabindex', active ? '-1' : '0'));
      this.dispatchEvent(new CustomEvent("environmenthover", { detail: null }));
    }
    setEnvironments(environments) {
      this.environments = environments;
      this.regions.replaceChildren();
      environments.forEach((environment) => {
        const points = environment.geometria.points;
        const group = svgElement("g", {
          "data-environment": environment.id,
          class: "pv-region",
          tabindex: 0,
          role: "button",
          "aria-label": `${environment.nome}, ${environment.imagem_ids.length} ${environment.imagem_ids.length === 1 ? 'imagem' : 'imagens'}`,
        });
        const displayPoints = points.map(point => this.toDisplayPixels(point));
        const polygon = svgElement("polygon", {
          points: displayPoints
            .map((p) => `${p[0]},${p[1]}`)
            .join(" "),
          "vector-effect": "non-scaling-stroke",
        });
        const title = svgElement("title");
        title.textContent = environment.nome;
        polygon.append(title);
        group.append(polygon);
        const center = displayPoints.reduce(
          (a, p) => [a[0] + p[0] / displayPoints.length, a[1] + p[1] / displayPoints.length],
          [0, 0],
        );
        const label = svgElement("text", {
          x: center[0],
          y: center[1],
          "text-anchor": "middle",
          "dominant-baseline": "middle",
          "font-size": 12 / this.scale,
        });
        label.textContent = environment.nome;
        group.append(label);
        this.regions.append(group);
      });
      this.updateLabels();
      this.highlight(this.selectedId);
    }
    updateLabels() {
      this.regions.querySelectorAll('text').forEach(label => {
        label.setAttribute('font-size', 12 / this.scale);
        label.style.display = '';
        const available = label.parentElement.querySelector('polygon').getBBox().width * this.scale - 12;
        // Nomes longos permanecem no tooltip, painel e aria-label das áreas pequenas.
        label.style.display = label.getComputedTextLength() * this.scale > available ? 'none' : '';
      });
    }
    select(id) {
      if (this.drawing) return;
      this.highlight(id);
      this.dispatchEvent(new CustomEvent("environmentselect", { detail: id }));
    }
    highlight(id) {
      this.selectedId = id;
      this.regions
        .querySelectorAll("[data-environment]")
        .forEach((g) =>
          g.classList.toggle(
            "is-selected",
            Number(g.dataset.environment) === id,
          ),
        );
    }
    hover(e) {
      if (this.drawing || this.drag) return;
      const region = e.target.closest("[data-environment]");
      const environment = this.environments.find(
        (item) => item.id === Number(region?.dataset.environment),
      );
      this.dispatchEvent(
        new CustomEvent("environmenthover", {
          detail: environment
            ? { environment, x: e.clientX, y: e.clientY }
            : null,
        }),
      );
    }
    destroy() {
      this.clear();
      this.controller.abort();
      this.resizeObserver.disconnect();
    }
  }
  window.PlantaViewer = PlantaViewer;
  window.plantaSvgElement = svgElement;
  window.plantaPointInPolygon = (p, points) => {
    let inside=false;
    for(let i=0,j=points.length-1;i<points.length;j=i++) {
      const a=points[j],b=points[i],cross=(b[0]-a[0])*(p[1]-a[1])-(b[1]-a[1])*(p[0]-a[0]);
      if(Math.abs(cross)<1e-8 && p[0]>=Math.min(a[0],b[0])-1e-8 && p[0]<=Math.max(a[0],b[0])+1e-8 && p[1]>=Math.min(a[1],b[1])-1e-8 && p[1]<=Math.max(a[1],b[1])+1e-8)return true;
      if((a[1]>p[1])!==(b[1]>p[1]) && p[0]<(b[0]-a[0])*(p[1]-a[1])/(b[1]-a[1])+a[0])inside=!inside;
    }
    return inside;
  };
})();
