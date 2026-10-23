<?php
// Profile photo (Mijn profiel), introduced 2026-09-11. Was applied by hand on
// older installs (migratie_2026-09-11_profielfoto.sql), hence the existence check.
return function (PDO $pdo): void {
    if (!migrationColumnExists($pdo, 'users', 'avatar_filename')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN avatar_filename VARCHAR(255) NULL AFTER active");
    }
};
