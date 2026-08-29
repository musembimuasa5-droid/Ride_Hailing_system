USE niaride;

ALTER TABLE rides
    ADD COLUMN IF NOT EXISTS scheduled_departure DATETIME NULL AFTER requested_at;
