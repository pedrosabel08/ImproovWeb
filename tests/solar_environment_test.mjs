import assert from 'node:assert/strict';
import { existsSync } from 'node:fs';
import {
  ENVIRONMENTS, ENVIRONMENT_THRESHOLDS, selectEnvironment,
  adjacentEnvironment, atmosphereInterval, nightFactor, smoothRange, ENVIRONMENT_SETTINGS,
} from '../MapaPavimento/predio-3d/environment-config.mjs';
import { solarAt } from '../MapaPavimento/predio-3d/solar-position.mjs';

for (const preset of Object.values(ENVIRONMENTS))
  assert.ok(existsSync(new URL('../assets/hdri/' + preset.file, import.meta.url)));
for (const rising of [false, true]) {
  assert.equal(selectEnvironment({ altitude: -6, rising }), 'night');
  assert.equal(selectEnvironment({ altitude: -90, rising }), 'night');
  assert.equal(selectEnvironment({ altitude: 85, rising }), 'morningLate');
}
assert.equal(selectEnvironment({ altitude: 5, rising: true }), 'morningEarly');
assert.equal(selectEnvironment({ altitude: 25, rising: true }), 'morningLate');
assert.equal(selectEnvironment({ altitude: 25, rising: false }), 'afternoon');
assert.equal(selectEnvironment({ altitude: -3, rising: false }), 'afternoon');
assert.equal(selectEnvironment({ altitude: 10, rising: true }, { ...ENVIRONMENT_THRESHOLDS, earlyMorning: 15 }), 'morningEarly');
assert.equal(adjacentEnvironment({ altitude: 5, rising: true }), 'morningLate');
assert.equal(adjacentEnvironment({ altitude: 40, rising: true }), null);
assert.equal(adjacentEnvironment({ altitude: -30, rising: false }), null);
assert.equal(adjacentEnvironment({ altitude: -4, rising: false }), 'night');
assert.equal(nightFactor(-6), 1);
assert.equal(nightFactor(0), 0);
assert.ok(nightFactor(-3) > 0 && nightFactor(-3) < 1);
// A visualização noturna deve clarear progressivamente sem alterar o patamar diurno.
for (const light of [ENVIRONMENT_SETTINGS.lightProbe, ENVIRONMENT_SETTINGS.hemisphere]) {
  const intensity = altitude => light.intensity * (light.baselineScale + (1 - light.baselineScale) *
    smoothRange(ENVIRONMENT_THRESHOLDS.night, ENVIRONMENT_THRESHOLDS.daylight, altitude) +
    (light.nightScale - light.baselineScale) * nightFactor(altitude));
  assert.equal(intensity(30), light.intensity);
  assert.ok(intensity(-20) > intensity(30));
  for (let altitude = -6; altitude < 0; altitude += .1)
    assert.ok(Math.abs(intensity(altitude + .1) - intensity(altitude)) < .02);
  for (let altitude = 0; altitude <= 30; altitude += .1)
    assert.ok(Math.abs(intensity(altitude) - light.intensity * (0.08 + 0.92 *
      smoothRange(ENVIRONMENT_THRESHOLDS.night, ENVIRONMENT_THRESHOLDS.daylight, altitude))) < 1e-10);
}
for (let altitude = -90; altitude <= 90; altitude += 0.05) {
  const state = atmosphereInterval(altitude);
  assert.ok(state.mix >= 0 && state.mix <= 1);
  assert.ok(state.from.zenith && state.to.horizon && state.from.sun);
}
// Latitude, estação, fuso e Norte não podem transformar tarde em manhã.
for (const timezone of ['America/Sao_Paulo', 'Europe/Lisbon']) {
  const config = { latitude: -27.0992256, longitude: -48.6179817, norte_graus: 83.9, timezone };
  for (const date of ['2026-01-15', '2026-06-21', '2026-10-30']) {
    for (let hour = 0; hour < 24; hour++) {
      const result = solarAt(config, date, String(hour).padStart(2, '0') + ':00');
      const rotated = solarAt({ ...config, norte_graus: 270 }, date, String(hour).padStart(2, '0') + ':00');
      assert.equal(selectEnvironment(result), selectEnvironment(rotated));
      if (result.altitude <= 0) assert.equal(result.intensity, 0);
      if (result.altitude > ENVIRONMENT_THRESHOLDS.earlyMorning && result.altitude < ENVIRONMENT_THRESHOLDS.afternoon)
        assert.equal(selectEnvironment(result), result.rising ? 'morningLate' : 'afternoon');
    }
  }
}
const config = { latitude: -27.0992256, longitude: -48.6179817, norte_graus: 83.9, timezone: 'America/Sao_Paulo' };
assert.equal(solarAt(config, '2026-10-30', '09:00').rising, true);
assert.equal(solarAt(config, '2026-10-30', '16:00').rising, false);
for (const latitude of [-80, 80]) for (const date of ['2026-06-21', '2026-12-21'])
  assert.ok(Object.hasOwn(ENVIRONMENTS, selectEnvironment(solarAt({ ...config, latitude }, date, '12:00'))));
console.log('OK: presets, thresholds configuráveis, manhã/tarde astronômicas, noite, preload limitado, atmosfera contínua, fusos, Norte e regiões polares.');
