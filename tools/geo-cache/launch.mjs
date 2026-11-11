import {spawn} from "node:child_process";
import {fileURLToPath} from "node:url";

const [mode, job] = process.argv.slice(2);
if (!job || !["launch", "supervise"].includes(mode)) process.exit(2);

const script = fileURLToPath(new URL(mode === "launch" ? "./launch.mjs" : "./worker.mjs", import.meta.url));
const child = spawn(process.execPath, mode === "launch" ? [script, "supervise", job] : [script, job], {
  detached: mode === "launch",
  windowsHide: true,
  stdio: "ignore",
});
child.on("error", () => { process.exitCode = 1; });
if (mode === "launch") {
  child.unref();
} else {
  const timer = setTimeout(() => child.kill("SIGKILL"), 90000);
  child.on("close", code => {
    clearTimeout(timer);
    process.exitCode = code ?? 1;
  });
}
