USE niaride;

ALTER TABLE drivers
    ADD COLUMN IF NOT EXISTS current_lat DECIMAL(10,7) NULL AFTER rating,
    ADD COLUMN IF NOT EXISTS current_lng DECIMAL(10,7) NULL AFTER current_lat;