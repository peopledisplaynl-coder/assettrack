<?php
/**
 * Sidebar template for AssetTrack
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireLogin();
?>
<aside class="sidebar">
    <nav class="sidebar-nav">
        <ul>
            <li>
                <a href="<?= BASE_URL ?>/dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                    <span class="icon">📊</span>
                    Dashboard
                </a>
            </li>

            <?php if (hasPermission('view_assets')): ?>
                <li>
                    <a href="<?= BASE_URL ?>/modules/assets/" class="<?php echo strpos($_SERVER['REQUEST_URI'], '/assets/') !== false ? 'active' : ''; ?>">
                        <span class="icon">📦</span>
                        Assets
                    </a>
                </li>
            <?php endif; ?>

            <?php if (hasPermission('manage_users')): ?>
                <li>
                    <a href="<?= BASE_URL ?>/modules/users/" class="<?php echo strpos($_SERVER['REQUEST_URI'], '/users/') !== false ? 'active' : ''; ?>">
                        <span class="icon">👥</span>
                        Gebruikers
                    </a>
                </li>
            <?php endif; ?>

            <?php if (hasPermission('view_reports')): ?>
                <li>
                    <a href="<?= BASE_URL ?>/modules/reports/" class="<?php echo strpos($_SERVER['REQUEST_URI'], '/reports/') !== false ? 'active' : ''; ?>">
                        <span class="icon">📈</span>
                        Rapporten
                    </a>
                </li>
            <?php endif; ?>

            <?php if (hasPermission('print_labels')): ?>
                <li>
                    <a href="<?= BASE_URL ?>/modules/labels/" class="<?php echo strpos($_SERVER['REQUEST_URI'], '/labels/') !== false ? 'active' : ''; ?>">
                        <span class="icon">🏷️</span>
                        Labels
                    </a>
                </li>
            <?php endif; ?>

            <?php if (hasPermission('manage_settings')): ?>
                <li>
                    <a href="<?= BASE_URL ?>/modules/settings/" class="<?php echo strpos($_SERVER['REQUEST_URI'], '/settings/') !== false ? 'active' : ''; ?>">
                        <span class="icon">⚙️</span>
                        Instellingen
                    </a>
                </li>
            <?php endif; ?>

            <li class="divider"></li>

            <li>
                <a href="<?= BASE_URL ?>/logout.php">
                    <span class="icon">🚪</span>
                    Uitloggen
                </a>
            </li>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <div class="user-info">
            <strong><?php echo htmlspecialchars(getCurrentUsername()); ?></strong><br>
            <small><?php echo htmlspecialchars(getRole()); ?></small>
        </div>
    </div>
</aside>