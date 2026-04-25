<?php
/**
 * Dashboard page for AssetTrack
 */

require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();
requireLocation();

$stats = getDashboardStats();
$recentActivity = getRecentActivity(10);

// Get current location info
$currentLocationId = getLocationId();
$currentLocation = null;
if ($currentLocationId) {
    $currentLocation = queryOne(
        "SELECT l.*, o.name as org_name FROM locations l
         JOIN organisations o ON l.organisation_id = o.id
         WHERE l.id = ?",
        [$currentLocationId]
    );
}

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - AssetTrack</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
    <?php include 'templates/header.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1>Dashboard</h1>
            <p>Welkom terug, <?php echo htmlspecialchars(getCurrentUsername()); ?>!</p>
            <?php if ($currentLocation): ?>
            <p style="color: #64748b; margin-top: 5px;">
                📍 <?php echo htmlspecialchars($currentLocation['org_name'] . ' - ' . $currentLocation['name']); ?>
            </p>
            <?php endif; ?>
        </div>

        <div class="dashboard-grid">
            <!-- Total Assets -->
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['total_assets']); ?></h3>
                    <p>Totaal Assets</p>
                </div>
            </div>

            <!-- Assets by Status -->
            <?php foreach (getAssetStatuses() as $status => $label): ?>
                <div class="stat-card">
                    <div class="stat-icon">
                        <?php
                        $icons = [
                            'In gebruik' => '✅',
                            'Beschikbaar' => '📦',
                            'In reparatie' => '🔧',
                            'Buiten gebruik' => '❌',
                            'Afgevoerd' => '🗑️'
                        ];
                        echo $icons[$status] ?? '📦';
                        ?>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['assets_by_status'][$status] ?? 0); ?></h3>
                        <p><?php echo htmlspecialchars($label); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Due for Replacement -->
            <div class="stat-card warning">
                <div class="stat-icon">⚠️</div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['due_for_replacement']); ?></h3>
                    <p>Vervangen binnen 6 maanden</p>
                </div>
            </div>

            <!-- Expired Warranties -->
            <div class="stat-card danger">
                <div class="stat-icon">🚨</div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['expired_warranties']); ?></h3>
                    <p>Verlopen Garanties</p>
                </div>
            </div>
        </div>

        <div class="dashboard-section">
            <h2>Recente Activiteit</h2>
            <div class="activity-list">
                <?php if (empty($recentActivity)): ?>
                    <p class="no-data">Geen recente activiteit gevonden.</p>
                <?php else: ?>
                    <?php foreach ($recentActivity as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <?php
                                $actionIcons = [
                                    'INSERT' => '➕',
                                    'UPDATE' => '✏️',
                                    'DELETE' => '🗑️'
                                ];
                                echo $actionIcons[$activity['action']] ?? '📝';
                                ?>
                            </div>
                            <div class="activity-content">
                                <p>
                                    <strong><?php echo htmlspecialchars($activity['username'] ?? 'Systeem'); ?></strong>
                                    heeft <?php echo htmlspecialchars($activity['action']); ?>
                                    uitgevoerd op <strong><?php echo htmlspecialchars($activity['table_name']); ?></strong>
                                    (ID: <?php echo htmlspecialchars($activity['record_id']); ?>)
                                </p>
                                <small><?php echo formatDateTime($activity['created_at']); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'templates/footer.php'; ?>

    <script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>