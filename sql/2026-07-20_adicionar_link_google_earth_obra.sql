-- Link preenchido pela pendência automática após a liberação do onboarding.
-- O modo relaxado evita rejeição por datas zero em registros legados durante o ALTER.
SET SESSION sql_mode = REPLACE(REPLACE(@@SESSION.sql_mode, 'NO_ZERO_IN_DATE', ''), 'NO_ZERO_DATE', '');

ALTER TABLE obra
    ADD COLUMN google_earth VARCHAR(2048) NULL AFTER link_review;
