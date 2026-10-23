<?php
// Fixed scan domain for QR/barcode labels, introduced 2026-09-18. Was applied by
// hand on older installs (migratie_2026-09-18_scan_domein.sql).
return function (PDO $pdo): void {
    if (!migrationColumnExists($pdo, 'companies', 'scan_base_url')) {
        $pdo->exec("ALTER TABLE companies ADD COLUMN scan_base_url VARCHAR(255) NULL AFTER website");
    }
};
