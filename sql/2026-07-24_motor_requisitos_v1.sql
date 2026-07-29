ALTER TABLE checklist_operacional
    ADD COLUMN requirements_version VARCHAR(40) NULL AFTER responsavel_id;

ALTER TABLE checklist_operacional_item
    ADD COLUMN update_mode ENUM('MANUAL','AUTOMATICO') NOT NULL DEFAULT 'MANUAL' AFTER required;
