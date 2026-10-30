/** Uma fila CPU fora da interface, compartilhada pelos presets deste renderer. */
export class ExrDecoder {
  constructor() {
    this.pending = new Map();
    this.sequence = 0;
  }
  decode(buffer, maxLuminance, sky) {
    if (this.disposed)
      return Promise.reject(new Error("Decodificador encerrado"));
    if (!this.worker) {
      this.worker = new Worker(
        new URL(
          "./exr-decoder-worker.js?v=20261030-horizon-final",
          import.meta.url,
        ),
        { type: "module" },
      );
      this.worker.onmessage = ({ data }) => {
        const job = this.pending.get(data.id);
        if (!job) return;
        clearTimeout(job.timer);
        this.pending.delete(data.id);
        if (data.error) job.reject(new Error(data.error));
        else job.resolve(data.result);
      };
      this.worker.onerror = () =>
        this.fail(
          new Error("Não foi possível decodificar o HDRI em segundo plano"),
        );
    }
    return new Promise((resolve, reject) => {
      const id = ++this.sequence;
      const timer = setTimeout(
        () => this.fail(new Error("Tempo excedido ao preparar HDRI")),
        30000,
      );
      this.pending.set(id, { resolve, reject, timer });
      try {
        this.worker.postMessage({ id, buffer, maxLuminance, sky }, [buffer]);
      } catch (error) {
        this.fail(error);
      }
    });
  }
  fail(error) {
    for (const job of this.pending.values()) {
      clearTimeout(job.timer);
      job.reject(error);
    }
    this.pending.clear();
    this.worker?.terminate();
    this.worker = null;
  }
  dispose() {
    this.disposed = true;
    this.fail(new Error("Decodificador encerrado"));
  }
}
