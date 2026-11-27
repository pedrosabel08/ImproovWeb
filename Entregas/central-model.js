/* Shared, read-only presentation rules for the three delivery views. */
(function (root, factory) {
  const model = factory();
  if (typeof module === "object" && module.exports) module.exports = model;
  else root.FlowDeliveryModel = model;
})(typeof window !== "undefined" ? window : globalThis, function () {
  "use strict";
  const normalize = (value) =>
    String(value ?? "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase();
  const date = (value) => {
    const key = String(value || "").slice(0, 10);
    if (!/^\d{4}-\d{2}-\d{2}$/.test(key)) return "";
    const parsed = new Date(key + "T12:00:00Z");
    return !Number.isNaN(+parsed) && parsed.toISOString().slice(0, 10) === key
      ? key
      : "";
  };
  const addDays = (key, days) => {
    const d = new Date(key + "T12:00:00Z");
    d.setUTCDate(d.getUTCDate() + days);
    return d.toISOString().slice(0, 10);
  };
  const daysBetween = (from, to) =>
    Math.round(
      (Date.parse(to + "T12:00:00Z") - Date.parse(from + "T12:00:00Z")) /
        86400000,
    );
  const hold = (e) => Boolean(e.em_hold) || e.kanban_status === "hold";
  const open = (e) => e.kanban_status !== "concluida" && !hold(e);
  const readyCount = (e) =>
    open(e) ? Math.max(0, Math.floor(Number(e.ready_count) || 0)) : 0;
  const ready = (e) => readyCount(e) > 0;
  const pendingCount = (e) =>
    Math.max(0, Number(e.total_itens || 0) - Number(e.entregues || 0));
  // Presentation partitions only: eligibility still comes from ready_count.
  // Clamp inconsistent totals so a segment cannot count an already delivered item.
  const progress = (e) => {
    const count = value => Number.isFinite(Number(value)) ? Math.max(0, Math.floor(Number(value))) : 0;
    const total = count(e.total_itens);
    const delivered = Math.min(total, count(e.entregues));
    const pending = total - delivered;
    const ready = Math.min(pending, readyCount(e));
    const other = pending - ready;
    const percent = value => total ? value / total * 100 : 0;
    return {
      total, delivered, ready, other, pending,
      deliveredPercent: percent(delivered),
      readyPercent: percent(ready),
      otherPercent: percent(other),
      pct: Math.round(percent(delivered) * 10) / 10,
    };
  };
  const readySummary = (list) =>
    list.reduce(
      (summary, e) => {
        const count = readyCount(e);
        summary.images += count;
        if (count > 0) summary.deliveries++;
        return summary;
      },
      { images: 0, deliveries: 0 },
    );
  const attention = (e) =>
    e.kanban_status === "atrasada" ||
    (!hold(e) && Number(e.review_batches_overdue) > 0);
  const group = (e) =>
    e.kanban_status === "concluida"
      ? "sent"
      : attention(e)
        ? "attention"
        : "pending";
  const quick = (e, key, today) => {
    const due = date(e.data_prevista);
    switch (key) {
      case "late":
        return e.kanban_status === "atrasada";
      case "today":
        return open(e) && due === today;
      case "tomorrow":
        return open(e) && due === addDays(today, 1);
      case "week":
        return open(e) && due >= today && due <= addDays(today, 6);
      case "ready":
        return ready(e);
      default:
        return true;
    }
  };
  const baseFilter = (list, state) =>
    list.filter(
      (e) =>
        (!state.obra ||
          String(e.obra_id || e.obraId || e.id_obra || "") === state.obra) &&
        (!state.stage || normalize(e.nome_etapa) === normalize(state.stage)) &&
        (!state.search ||
          normalize(`${e.nomenclatura} ${e.nome_etapa} ${e.id}`).includes(
            normalize(state.search).trim(),
          )),
    );
  const filter = (list, state, today) =>
    baseFilter(list, state).filter(
      (e) =>
        quick(e, state.quick, today) &&
        (!state.day || (open(e) && date(e.data_prevista) === state.day)),
    );
  const sort = (list, key) =>
    [...list].sort((a, b) => {
      const due = () =>
        (date(a.data_prevista) || "9999").localeCompare(
          date(b.data_prevista) || "9999",
        );
      const name = () =>
        String(a.nomenclatura || "").localeCompare(
          String(b.nomenclatura || ""),
          "pt-BR",
          { numeric: true },
        );
      if (key === "progress")
        return (
          Number(b.pct_entregue || 0) - Number(a.pct_entregue || 0) ||
          due() ||
          name()
        );
      if (key === "obra") return name() || due();
      return due() || name() || Number(a.id) - Number(b.id);
    });
  // Only dates supplied by the API may produce a temporal span.
  const span = (e, start, end) => {
    const due = date(e.data_prevista);
    if (!due) return null;
    const received = date(e.data_recebimento);
    const from = received && received <= due ? received : due;
    if (due < start || from > end) return null;
    return {
      from: from < start ? start : from,
      to: due > end ? end : due,
      start: from,
      due,
    };
  };
  return {
    normalize,
    date,
    addDays,
    daysBetween,
    hold,
    open,
    readyCount,
    ready,
    pendingCount,
    progress,
    readySummary,
    attention,
    group,
    quick,
    baseFilter,
    filter,
    sort,
    span,
  };
});
