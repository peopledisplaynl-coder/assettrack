-- AssetTrack migratie 2026-09-18: vast scan-domein voor QR/streepjescode-labels
-- Voer dit één keer uit op je LIVE database (bijv. via phpMyAdmin op Strato),
-- VOORDAT je de bijgewerkte PHP-bestanden uploadt. Op een nieuwe installatie
-- (install.sql) zit deze kolom al standaard in het schema -- dit bestand is
-- alleen nodig voor een database die al bestaat.
ALTER TABLE companies ADD COLUMN scan_base_url VARCHAR(255) NULL AFTER website;
