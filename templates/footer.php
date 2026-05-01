<?php
// Laad thema voor footer als het nog niet geladen is
if (!isset($company)) {
    $company = function_exists('queryOne')
        ? queryOne("SELECT app_name, theme_primary, theme_secondary FROM companies WHERE active = 1 ORDER BY id LIMIT 1")
        : null;
}
$footerAppName  = $company['app_name']      ?? 'AssetTrack';
$footerPrimary  = $company['theme_primary']  ?? '#2563eb';
$footerSecondary= $company['theme_secondary'] ?? '#1a2332';
$footerVersion  = '3.1';
?>
</main>

<footer style="background:<?= $footerSecondary ?>;margin-top:auto;">
    <div style="max-width:1280px;margin:0 auto;padding:16px 20px;
                display:flex;align-items:center;justify-content:space-between;
                flex-wrap:wrap;gap:10px;">

        <!-- Links: naam + versie -->
        <div style="display:flex;align-items:center;gap:10px;">
            <span style="font-size:1rem;">📦</span>
            <span style="color:rgba(255,255,255,0.9);font-weight:600;font-size:0.85rem;">
                <?= htmlspecialchars($footerAppName) ?>
            </span>
            <span style="background:rgba(255,255,255,0.1);color:rgba(255,255,255,0.5);
                         padding:2px 8px;border-radius:999px;font-size:0.72rem;">
                v<?= $footerVersion ?>
            </span>
        </div>

        <!-- Midden: copyright -->
        <div style="color:rgba(255,255,255,0.45);font-size:0.78rem;">
            © <?= date('Y') ?> <?= htmlspecialchars($footerAppName) ?>. Alle rechten voorbehouden.
        </div>

        <!-- Rechts: snelle links -->
        <div style="display:flex;align-items:center;gap:16px;">
            <?php if (function_exists('hasPermission') && hasPermission('view_assets')): ?>
            <a href="<?= BASE_URL ?>/modules/assets/"
               style="color:rgba(255,255,255,0.45);font-size:0.78rem;text-decoration:none;
                      transition:color 0.15s;"
               onmouseover="this.style.color='rgba(255,255,255,0.85)'"
               onmouseout="this.style.color='rgba(255,255,255,0.45)'">
                Assets
            </a>
            <?php endif; ?>
            <?php if (function_exists('hasPermission') && hasPermission('view_reports')): ?>
            <a href="<?= BASE_URL ?>/modules/reports/"
               style="color:rgba(255,255,255,0.45);font-size:0.78rem;text-decoration:none;
                      transition:color 0.15s;"
               onmouseover="this.style.color='rgba(255,255,255,0.85)'"
               onmouseout="this.style.color='rgba(255,255,255,0.45)'">
                Rapporten
            </a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/modules/kb/"
               style="color:rgba(255,255,255,0.45);font-size:0.78rem;text-decoration:none;
                      transition:color 0.15s;"
               onmouseover="this.style.color='rgba(255,255,255,0.85)'"
               onmouseout="this.style.color='rgba(255,255,255,0.45)'">
                Kennisbank
            </a>
            <?php if (function_exists('hasPermission') && hasPermission('manage_settings')): ?>
            <a href="<?= BASE_URL ?>/modules/settings/"
               style="color:rgba(255,255,255,0.45);font-size:0.78rem;text-decoration:none;
                      transition:color 0.15s;"
               onmouseover="this.style.color='rgba(255,255,255,0.85)'"
               onmouseout="this.style.color='rgba(255,255,255,0.45)'">
                Instellingen
            </a>
            <?php endif; ?>
        </div>
    </div>
</footer>

</body>
</html>
