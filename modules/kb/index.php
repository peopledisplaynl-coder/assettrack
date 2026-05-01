<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$search   = trim($_GET['search'] ?? '');
$catId    = (int)($_GET['category'] ?? 0);
$success  = htmlspecialchars($_GET['success'] ?? '');

// Laad categorieën met artikeltelling
$categories = query("SELECT c.*, COUNT(a.id) as article_count
    FROM kb_categories c
    LEFT JOIN kb_articles a ON a.category_id = c.id AND a.active = 1
    WHERE c.active = 1
    GROUP BY c.id ORDER BY c.sort_order, c.name");

// Laad artikelen
$where  = ["a.active = 1"];
$params = [];
if ($catId)   { $where[] = "a.category_id = ?"; $params[] = $catId; }
if ($search)  {
    $where[] = "(a.title LIKE ? OR a.content LIKE ? OR a.tags LIKE ?)";
    $term = "%$search%";
    $params = array_merge($params, [$term, $term, $term]);
}
$whereClause = 'WHERE ' . implode(' AND ', $where);

$articles = query("SELECT a.*, c.name as category_name, c.icon as category_icon,
    u.username as author
    FROM kb_articles a
    LEFT JOIN kb_categories c ON a.category_id = c.id
    LEFT JOIN users u ON a.created_by = u.id
    $whereClause
    ORDER BY a.updated_at DESC", $params);

$pageTitle = 'Kennisbank';
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header">
    <h1>📚 Kennisbank</h1>
    <?php if (hasPermission('manage_kb')): ?>
    <div style="display:flex;gap:10px;">
        <a href="<?= BASE_URL ?>/modules/kb/article_edit.php" class="btn btn-primary">+ Artikel toevoegen</a>
        <a href="<?= BASE_URL ?>/modules/kb/categories.php" class="btn btn-secondary">Categorieën beheren</a>
    </div>
    <?php endif; ?>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:240px 1fr;gap:20px;">

    <!-- Sidebar: categorieën -->
    <div>
        <div class="card">
            <div class="card-body">
                <!-- Zoekbalk -->
                <form method="GET" style="margin-bottom:15px;">
                    <div style="display:flex;gap:6px;">
                        <input type="text" name="search" class="form-control"
                               placeholder="Zoeken..."
                               value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn btn-secondary" style="padding:7px 10px;">🔍</button>
                    </div>
                    <?php if ($search): ?>
                    <a href="<?= BASE_URL ?>/modules/kb/" style="font-size:0.8rem;color:#6b7280;display:block;margin-top:4px;">
                        ✕ Zoekopdracht wissen
                    </a>
                    <?php endif; ?>
                </form>

                <h4 style="margin:0 0 10px;font-size:0.85rem;color:#6b7280;text-transform:uppercase;font-weight:600;">
                    Categorieën
                </h4>

                <a href="<?= BASE_URL ?>/modules/kb/"
                   style="display:flex;justify-content:space-between;align-items:center;
                          padding:7px 10px;border-radius:6px;text-decoration:none;margin-bottom:3px;
                          <?= !$catId && !$search ? 'background:#eff6ff;color:#2563eb;font-weight:600;' : 'color:#374151;' ?>">
                    <span>📋 Alle artikelen</span>
                    <span style="font-size:0.8rem;color:#6b7280;"><?= count($articles) ?></span>
                </a>

                <?php foreach ($categories as $cat): ?>
                <a href="?category=<?= $cat['id'] ?>"
                   style="display:flex;justify-content:space-between;align-items:center;
                          padding:7px 10px;border-radius:6px;text-decoration:none;margin-bottom:3px;
                          <?= $catId === $cat['id'] ? 'background:#eff6ff;color:#2563eb;font-weight:600;' : 'color:#374151;' ?>">
                    <span><?= htmlspecialchars($cat['icon'] ?? '📄') ?> <?= htmlspecialchars($cat['name']) ?></span>
                    <span style="font-size:0.8rem;color:#6b7280;"><?= $cat['article_count'] ?></span>
                </a>
                <?php endforeach; ?>

                <?php if (empty($categories)): ?>
                <p style="color:#6b7280;font-size:0.875rem;">Nog geen categorieën.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Artikelen -->
    <div>
        <?php if (empty($articles)): ?>
        <div class="card">
            <div class="card-body" style="text-align:center;padding:40px;color:#6b7280;">
                <div style="font-size:3rem;margin-bottom:15px;">📚</div>
                <?php if ($search): ?>
                <p>Geen artikelen gevonden voor <strong>"<?= htmlspecialchars($search) ?>"</strong>.</p>
                <?php else: ?>
                <p>Nog geen artikelen in de kennisbank.</p>
                <?php if (hasPermission('manage_kb')): ?>
                <a href="<?= BASE_URL ?>/modules/kb/article_edit.php" class="btn btn-primary" style="margin-top:15px;">
                    + Eerste artikel toevoegen
                </a>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>

        <?php if ($search): ?>
        <p style="color:#6b7280;margin-bottom:15px;font-size:0.875rem;">
            <strong><?= count($articles) ?></strong> resultaten voor "<?= htmlspecialchars($search) ?>"
        </p>
        <?php endif; ?>

        <div style="display:grid;gap:12px;">
            <?php foreach ($articles as $article): ?>
            <div class="card" style="margin:0;">
                <div class="card-body">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;">
                        <div style="flex:1;">
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;flex-wrap:wrap;">
                                <span style="font-size:0.75rem;background:#f1f5f9;color:#475569;
                                             padding:2px 8px;border-radius:999px;">
                                    <?= htmlspecialchars($article['category_icon'] ?? '📄') ?>
                                    <?= htmlspecialchars($article['category_name'] ?? '') ?>
                                </span>
                                <?php if ($article['asset_type']): ?>
                                <span style="font-size:0.75rem;background:#dbeafe;color:#1e40af;
                                             padding:2px 8px;border-radius:999px;">
                                    <?= htmlspecialchars($article['asset_type']) ?>
                                </span>
                                <?php endif; ?>
                                <?php if ($article['brand']): ?>
                                <span style="font-size:0.75rem;background:#d1fae5;color:#065f46;
                                             padding:2px 8px;border-radius:999px;">
                                    <?= htmlspecialchars($article['brand']) ?>
                                </span>
                                <?php endif; ?>
                            </div>

                            <h3 style="margin:0 0 6px;font-size:1rem;">
                                <a href="<?= BASE_URL ?>/modules/kb/article.php?id=<?= $article['id'] ?>"
                                   style="color:#1a2332;text-decoration:none;">
                                    <?= htmlspecialchars($article['title']) ?>
                                </a>
                            </h3>

                            <p style="color:#6b7280;font-size:0.875rem;margin:0 0 8px;
                                      overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;
                                      -webkit-box-orient:vertical;">
                                <?= htmlspecialchars(strip_tags(mb_substr($article['content'], 0, 200))) ?>...
                            </p>

                            <div style="display:flex;align-items:center;gap:12px;font-size:0.78rem;color:#94a3b8;">
                                <?php if ($article['external_url']): ?>
                                <a href="<?= htmlspecialchars($article['external_url']) ?>"
                                   target="_blank" style="color:#2563eb;">🔗 Externe link</a>
                                <?php endif; ?>
                                <?php if ($article['tags']): ?>
                                <span>🏷️ <?= htmlspecialchars($article['tags']) ?></span>
                                <?php endif; ?>
                                <span>📅 <?= formatDate($article['updated_at']) ?></span>
                                <?php if ($article['author']): ?>
                                <span>✍️ <?= htmlspecialchars($article['author']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div style="display:flex;gap:6px;flex-shrink:0;">
                            <a href="<?= BASE_URL ?>/modules/kb/article.php?id=<?= $article['id'] ?>"
                               class="btn btn-sm btn-secondary">Lezen</a>
                            <?php if (hasPermission('manage_kb')): ?>
                            <a href="<?= BASE_URL ?>/modules/kb/article_edit.php?id=<?= $article['id'] ?>"
                               class="btn btn-sm btn-secondary">✏️</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
