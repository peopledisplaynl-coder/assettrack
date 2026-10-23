<?php
// "Onthoud mij" (stay logged in for 30 days), introduced 2026-09-13.
return function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS remember_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        selector VARCHAR(24) NOT NULL,
        validator_hash VARCHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_selector (selector),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
};
