<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
$article = queryOne("SELECT a.*, c.name as category_name, c.icon as category_icon,
    u.username as author
    FROM kb_articles a
    LEFT JOIN kb_categories c ON a.category_id = c.id
    LEFT JOIN users u ON a.created_by = u.id
    WHERE a.id = ? AND a.active = 1", [$id]);

if (!$article) {
    header('Location: ' . BASE_URL . '/modules/kb/?error=Artikel+niet+gevonden');
    exit;
}

// Gekoppelde assets
$linkedAssets = query("SELECT a.id, a.asset_number, a.brand, a.model, a.type, a.status, a.room,
    l.name as location_name
    FROM kb_article_assets ka
    JOIN assets a ON ka.asset_id = a.id
    LEFT JOIN locations l ON a.location_id = l.id
    WHERE ka.article_id = ?
    ORDER BY a.asset_number", [$id]);

// Gerelateerde artikelen (zelfde categorie of zelfde asset_type)
$related = query("SELECT id, title, category_id FROM kb_articles
    WHERE active = 1 AND id != ? AND (category_id = ? OR asset_type = ?)
    ORDER BY updated_at DESC LIMIT 5",
    [$id, $article['category_id'], $article['asset_type'] ?? '']);

$pageTitle = $article['title'];
include __DIR__ . '/../../templates/header.php';
?>

<div style="display:grid;grid-template-columns:1fr 280px;gap:20px;">

    <!-- Artikel -->
    <div>
        <div class="card">
            <div class="card-body">
                <!-- Broodkruimel -->
                <div style="font-size:0.8rem;color:#6b7280;margin-bottom:15px;">
                    <a href="<?= BASE_URL ?>/modules/kb/" style="color:#2563eb;text-decoration:none;">Kennisbank</a>
                    › <?= htmlspecialchars($article['category_name'] ?? '') ?>
                    › <?= htmlspecialchars($article['title']) ?>
                </div>

                <!-- Header -->
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;">
                    <div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;">
                            <span style="background:#f1f5f9;color:#475569;padding:3px 10px;border-radius:999px;font-size:0.8rem;">
                                <?= htmlspecialchars($article['category_icon'] ?? '📄') ?>
                                <?= htmlspecialchars($article['category_name'] ?? '') ?>
                            </span>
                            <?php if ($article['asset_type']): ?>
                            <span style="background:#dbeafe;color:#1e40af;padding:3px 10px;border-radius:999px;font-size:0.8rem;">
                                🖥️ <?= htmlspecialchars($article['asset_type']) ?>
                            </span>
                            <?php endif; ?>
                            <?php if ($article['brand']): ?>
                            <span style="background:#d1fae5;color:#065f46;padding:3px 10px;border-radius:999px;font-size:0.8rem;">
                                🏷️ <?= htmlspecialchars($article['brand']) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <h1 style="margin:0;font-size:1.4rem;color:#1a2332;">
                            <?= htmlspecialchars($article['title']) ?>
                        </h1>
                        <div style="font-size:0.8rem;color:#94a3b8;margin-top:8px;">
                            Bijgewerkt op <?= formatDate($article['updated_at']) ?>
                            <?php if ($article['author']): ?>
                            door <?= htmlspecialchars($article['author']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (hasPermission('manage_kb')): ?>
                    <div style="display:flex;gap:8px;flex-shrink:0;">
                        <a href="<?= BASE_URL ?>/modules/kb/article_edit.php?id=<?= $article['id'] ?>"
                           class="btn btn-secondary">✏️ Bewerken</a>
                        <a href="<?= BASE_URL ?>/modules/kb/" class="btn btn-secondary">← Terug</a>
                    </div>
                    <?php else: ?>
                    <a href="<?= BASE_URL ?>/modules/kb/" class="btn btn-secondary">← Terug</a>
                    <?php endif; ?>
                </div>

                <!-- Inhoud -->
                <div style="line-height:1.7;color:#374151;border-top:1px solid #e5e7eb;padding-top:20px;">
                    <?= nl2br(htmlspecialchars($article['content'])) ?>
                </div>

                <!-- Externe link -->
                <?php if ($article['external_url']): ?>
                <div style="margin-top:20px;padding:15px;background:#eff6ff;border-radius:8px;border:1px solid #bfdbfe;">
                    <strong style="font-size:0.875rem;">🔗 Externe documentatie:</strong><br>
                    <a href="<?= htmlspecialchars($article['external_url']) ?>"
                       target="_blank" rel="noopener"
                       style="color:#2563eb;word-break:break-all;">
                        <?= htmlspecialchars($article['external_url']) ?>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Tags -->
                <?php if ($article['tags']): ?>
                <div style="margin-top:15px;">
                    <?php foreach (explode(',', $article['tags']) as $tag): ?>
                    <a href="<?= BASE_URL ?>/modules/kb/?search=<?= urlencode(trim($tag)) ?>"
                       style="display:inline-block;background:#f1f5f9;color:#475569;
                              padding:3px 10px;border-radius:999px;font-size:0.8rem;
                              text-decoration:none;margin:2px;">
                        #<?= htmlspecialchars(trim($tag)) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div>
        <!-- Gekoppelde assets -->
        <?php if (!empty($linkedAssets)): ?>
        <div class="card" style="margin-bottom:15px;">
            <div class="card-body">
                <h4 style="margin:0 0 12px;font-size:0.9rem;color:#1a2332;">
                    🔗 Gekoppelde assets
                </h4>
                <div style="display:grid;gap:8px;">
                    <?php foreach ($linkedAssets as $a):
                    $colors = ['In gebruik'=>'#10b981','Beschikbaar'=>'#3b82f6',
                               'In reparatie'=>'#f59e0b','Buiten gebruik'=>'#ef4444','Afgevoerd'=>'#6b7280'];
                    $sc = $colors[$a['status']] ?? '#6b7280';
                    ?>
                    <a href="<?= BASE_URL ?>/modules/assets/view.php?id=<?= $a['id'] ?>"
                       style="display:block;padding:8px 10px;background:#f8fafc;
                              border-radius:6px;border:1px solid #e5e7eb;text-decoration:none;">
                        <div style="font-weight:600;font-size:0.875rem;color:#1a2332;">
                            <?= htmlspecialchars($a['asset_number']) ?>
                        </div>
                        <div style="font-size:0.78rem;color:#6b7280;">
                            <?= htmlspecialchars(trim(($a['brand']??'').' '.($a['model']??''))) ?>
                        </div>
                        <div style="margin-top:4px;">
                            <span style="font-size:0.72rem;color:white;background:<?= $sc ?>;
                                         padding:1px 6px;border-radius:3px;">
                                <?= htmlspecialchars($a['status']) ?>
                            </span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Gerelateerde artikelen -->
        <?php if (!empty($related)): ?>
        <div class="card" style="margin-bottom:15px;">
            <div class="card-body">
                <h4 style="margin:0 0 12px;font-size:0.9rem;color:#1a2332;">
                    📄 Gerelateerde artikelen
                </h4>
                <div style="display:grid;gap:6px;">
                    <?php foreach ($related as $r): ?>
                    <a href="<?= BASE_URL ?>/modules/kb/article.php?id=<?= $r['id'] ?>"
                       style="font-size:0.875rem;color:#2563eb;text-decoration:none;
                              padding:5px 8px;border-radius:4px;display:block;"
                       onmouseover="this.style.background='#f8fafc'"
                       onmouseout="this.style.background=''">
                        → <?= htmlspecialchars($r['title']) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Snel navigeren -->
        <div class="card">
            <div class="card-body">
                <h4 style="margin:0 0 12px;font-size:0.9rem;color:#1a2332;">Navigatie</h4>
                <a href="<?= BASE_URL ?>/modules/kb/" class="btn btn-secondary" style="width:100%;text-align:center;margin-bottom:6px;">
                    ← Kennisbank overzicht
                </a>
                <?php if (hasPermission('manage_kb')): ?>
                <a href="<?= BASE_URL ?>/modules/kb/article_edit.php" class="btn btn-secondary" style="width:100%;text-align:center;">
                    + Nieuw artikel
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
