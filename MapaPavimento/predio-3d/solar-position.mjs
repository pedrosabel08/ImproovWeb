import SunCalc from "../../assets/libs/solar/suncalc-1.9.0.mjs";
import { DateTime, IANAZone } from "../../assets/libs/solar/luxon-3.7.2.mjs";
import { northDirection } from "./north-direction.js";

export function solarConfigError(config) {
  if (!config) return "Carregando configurações espaciais…";
  if (
    !Number.isFinite(config.latitude) ||
    Math.abs(config.latitude) > 90 ||
    !Number.isFinite(config.longitude) ||
    Math.abs(config.longitude) > 180
  )
    return "Defina latitude e longitude em Configurações espaciais.";
  if (
    !Number.isFinite(config.norte_graus) ||
    config.norte_graus < 0 ||
    config.norte_graus >= 360
  )
    return "Defina o Norte em Configurações espaciais para ativar o Sol.";
  if (!IANAZone.isValidZone(config.timezone))
    return "Configure um fuso horário válido para o empreendimento.";
  return "";
}

/** Interpreta relógio local na zona da obra, nunca na zona do computador. */
export function solarLocalTime(date, time, zone) {
  if (
    !/^\d{4}-\d{2}-\d{2}$/.test(date) ||
    !/^\d{2}:\d{2}$/.test(time) ||
    !IANAZone.isValidZone(zone)
  )
    throw new Error("Informe data, horário e fuso válidos.");
  let local = DateTime.fromISO(date + "T" + time, { zone });
  if (
    !local.isValid ||
    local.toISODate() !== date ||
    local.toFormat("HH:mm") !== time
  )
    throw new Error(
      "Este horário não existe nessa data e nesse fuso. Escolha outro horário.",
    );
  // Na repetição do relógio ao fim do horário de verão, escolhe a primeira ocorrência.
  // Não deixa a escolha depender do offset atual do computador/estação do ano.
  const options = local
    .getPossibleOffsets()
    .sort((a, b) => a.toMillis() - b.toMillis());
  local = options[0];
  return {
    date: local.toJSDate(),
    utcISO: local.toUTC().toISO(),
    offset: local.toFormat("ZZ"),
    ambiguous: options.length > 1,
    label: local.setLocale("pt-BR").toFormat("dd LLL yyyy") + " · " + time,
  };
}

export function solarNow(zone, millis = Date.now()) {
  const local = DateTime.fromMillis(millis, { zone });
  if (!local.isValid) throw new Error("Fuso horário inválido.");
  return { date: local.toISODate(), time: local.toFormat("HH:mm") };
}

/** SunCalc 1.9.0: radianos a partir do SUL, positivos em direção ao OESTE.
 * Bússola: graus a partir do NORTE, positivos em direção ao LESTE.
 * Portanto azimuteVerdadeiro = (azimuth * 180/PI + 180) mod 360.
 * Não usar aqui a convenção de outras versões do SunCalc. */
export function trueAzimuth(azimuthRadians) {
  return ((((azimuthRadians * 180) / Math.PI + 180) % 360) + 360) % 360;
}

/** Direção normalizada empreendimento → Sol. Reutiliza exatamente northDirection.
 * Leste é o Norte girado 90° no plano XZ: (-north.z, 0, north.x).
 * horizontal = north*cos(azimute) + east*sin(azimute); +Y = sin(elevação). */
export function solarDirection(azimuthDegrees, altitudeRadians, northDegrees) {
  const north = northDirection(northDegrees),
    azimuth = (azimuthDegrees * Math.PI) / 180;
  const horizontal = Math.cos(altitudeRadians);
  return {
    x: (north.x * Math.cos(azimuth) - north.z * Math.sin(azimuth)) * horizontal,
    y: Math.sin(altitudeRadians),
    z: (north.z * Math.cos(azimuth) + north.x * Math.sin(azimuth)) * horizontal,
  };
}

export function solarIntensity(altitudeRadians) {
  return altitudeRadians <= 0
    ? 0
    : 2.8 * Math.sqrt(Math.max(0, Math.sin(altitudeRadians)));
}

function positionAtInstant(config, date) {
  const position = SunCalc.getPosition(date, config.latitude, config.longitude);
  const azimuth = trueAzimuth(position.azimuth);
  // Derivada astronômica: independe de fuso, relógio civil e rotação do Norte.
  // Também funciona onde o Sol não nasce/se põe em determinadas estações.
  const before = SunCalc.getPosition(
    new Date(date.getTime() - 60000),
    config.latitude,
    config.longitude,
  );
  const after = SunCalc.getPosition(
    new Date(date.getTime() + 60000),
    config.latitude,
    config.longitude,
  );
  return {
    azimuth,
    rising: after.altitude >= before.altitude,
    altitude: (position.altitude * 180) / Math.PI,
    direction: solarDirection(azimuth, position.altitude, config.norte_graus),
    intensity: solarIntensity(position.altitude),
  };
}

/** Posição no instante real, inclusive segundos e ocorrências repetidas do relógio. */
export function solarAtInstant(config, millis = Date.now()) {
  const error = solarConfigError(config);
  if (error) throw new Error(error);
  const date = new Date(millis);
  if (!Number.isFinite(date.getTime())) throw new Error("Instante inválido.");
  return {
    date,
    utcISO: date.toISOString(),
    ...positionAtInstant(config, date),
  };
}

export function solarAt(config, date, time) {
  const error = solarConfigError(config);
  if (error) throw new Error(error);
  const local = solarLocalTime(date, time, config.timezone);
  return { ...local, ...positionAtInstant(config, local.date) };
}

/** Trajetória do DIA LOCAL, amostrada em instantes UTC a cada dez minutos.
 * Dias de 23/25h respeitam o fuso; não pulam nem repetem instantes por mudar o relógio.
 * Usa exatamente o mesmo cálculo de posição da luz, sem outra convenção de Norte. */
export function solarDayPositions(config, date) {
  const error = solarConfigError(config);
  if (error) throw new Error(error);
  if (!/^\d{4}-\d{2}-\d{2}$/.test(date))
    throw new Error("Informe uma data válida.");
  const start = DateTime.fromISO(date, { zone: config.timezone }).startOf(
    "day",
  );
  if (!start.isValid || start.toISODate() !== date)
    throw new Error("Esta data não existe nesse fuso.");
  const end = start.plus({ days: 1 }).startOf("day");
  const samples = [];
  for (
    let millis = start.toMillis();
    millis < end.toMillis();
    millis += 10 * 60000
  ) {
    const instant = new Date(millis);
    samples.push({ date: instant, ...positionAtInstant(config, instant) });
  }
  const last = end.toJSDate();
  samples.push({ date: last, ...positionAtInstant(config, last) });
  return samples;
}
