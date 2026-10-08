/* Apenas contexto de navegação; nenhum item/valor do legado cruza para o novo fluxo. */
document
  .getElementById("fechamento-v2-link")
  ?.addEventListener("click", (event) => {
    const b = document.getElementById("colaborador")?.value;
    const month = document.getElementById("mes")?.value;
    const year = document.getElementById("ano")?.value;
    const url = new URL("fechamento.php", location.href);
    if (month && year)
      url.searchParams.set("competencia", year + "-" + month.padStart(2, "0"));
    event.currentTarget.href = url.href;
  });

const remuneration = {
  FIXO: "Fixo",
  VARIAVEL: "Variável",
  FIXO_VARIAVEL: "Fixo + variável",
};
let remunerationSequence = 0;
document
  .getElementById("colaborador")
  ?.addEventListener("change", async (event) => {
    const b = event.target.value,
      sequence = ++remunerationSequence;
    const info = document.getElementById("remuneracao-info");
    info.hidden = !b;
    if (!b) return;
    info.querySelector("a").href =
      "../Colaborador/?colaborador_id=" + encodeURIComponent(b);
    try {
      const response = await fetch(
        "tipo_remuneracao.php?colaborador_id=" + encodeURIComponent(b),
      );
      const data = await response.json();
      if (sequence === remunerationSequence)
        document.getElementById("remuneracao-badge").textContent =
          remuneration[data.tipo_remuneracao] || "Não definido";
    } catch {
      if (sequence === remunerationSequence)
        document.getElementById("remuneracao-badge").textContent =
          "Cadastro indisponível";
    }
  });
