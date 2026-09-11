-- AssetTrack migratie 2026-09-11: profielfoto-veld toevoegen aan bestaande installatie
-- Voer dit één keer uit op je LIVE database (bijv. via phpMyAdmin op Strato),
-- VOORDAT je de bijgewerkte PHP-bestanden uploadt. Op een nieuwe installatie
-- (install.sql) zit deze kolom al standaard in het schema -- dit bestand is
-- alleen nodig voor een database die al bestaat.
ALTER TABLE users ADD COLUMN avatar_filename VARCHAR(255) NULL AFTER active;
