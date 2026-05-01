<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requirePermission('edit_assets');
requireLocation();

$assetId = (int)($_GET['id'] ?? 0);
$asset = getAssetById($assetId);
if (!$asset) {
    header('Location: ' . BASE_URL . '/modules/assets/?error=Asset+niet+gevonden');
    exit;
}

canEditLocation((int)$asset['location_id']);

$uploadDir = __DIR__ . '/../../assets/uploads/asset_images/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ongeldige CSRF token.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'upload') {
            if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Er is geen afbeelding geüpload.';
            } else {
                $image = $_FILES['image'];
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $extension = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));

                if (!in_array($extension, $allowedExtensions, true)) {
                    $errors[] = 'Ongeldig bestandstype. Gebruik jpg, png, gif of webp.';
                }

                if ($image['size'] > 5 * 1024 * 1024) {
                    $errors[] = 'Maximale bestandsgrootte is 5MB.';
                }

                $imageCount = queryOne("SELECT COUNT(*) as total FROM asset_images WHERE asset_id = ?", [$assetId]);
                if (($imageCount['total'] ?? 0) >= 20) {
                    $errors[] = 'Maximaal 20 foto\'s per asset toegestaan.';
                }

                if (empty($errors)) {
                    $filename = 'asset_' . $assetId . '_' . time() . '.' . $extension;
                    $destination = $uploadDir . $filename;

                    if (!move_uploaded_file($image['tmp_name'], $destination)) {
                        $errors[] = 'Kon het bestand niet opslaan.';
                    } else {
                        $nextOrder = queryOne("SELECT COALESCE(MAX(sort_order), 0) + 1 as next_order FROM asset_images WHERE asset_id = ?", [$assetId]);
                        $order = $nextOrder['next_order'] ?? 1;
                        execute(
                            "INSERT INTO asset_images (asset_id, filename, original_name, sort_order, uploaded_at) VALUES (?, ?, ?, ?, NOW())",
                            [$assetId, $filename, $image['name'], $order]
                        );
                        $success = 'Afbeelding succesvol geüpload.';
                    }
                }
            }
        }

        if ($action === 'delete') {
            $imageId = (int)($_POST['image_id'] ?? 0);
            if ($imageId <= 0 || !deleteAssetImage($imageId, $assetId)) {
                $errors[] = 'Kon afbeelding niet verwijderen.';
            } else {
                $success = 'Afbeelding verwijderd.';
            }
        }

        if ($action === 'move') {
            $imageId = (int)($_POST['image_id'] ?? 0);
            $direction = $_POST['direction'] ?? '';
            $current = queryOne("SELECT id, sort_order FROM asset_images WHERE id = ? AND asset_id = ?", [$imageId, $assetId]);

            if (!$current) {
                $errors[] = 'Afbeelding niet gevonden.';
            } else {
                $neighbor = null;
                if ($direction === 'up') {
                    $neighbor = queryOne(
                        "SELECT id, sort_order FROM asset_images WHERE asset_id = ? AND (sort_order < ? OR (sort_order = ? AND id < ?)) ORDER BY sort_order DESC, id DESC LIMIT 1",
                        [$assetId, $current['sort_order'], $current['sort_order'], $current['id']]
                    );
                } elseif ($direction === 'down') {
                    $neighbor = queryOne(
                        "SELECT id, sort_order FROM asset_images WHERE asset_id = ? AND (sort_order > ? OR (sort_order = ? AND id > ?)) ORDER BY sort_order ASC, id ASC LIMIT 1",
                        [$assetId, $current['sort_order'], $current['sort_order'], $current['id']]
                    );
                }

                if ($neighbor) {
                    execute("UPDATE asset_images SET sort_order = ? WHERE id = ?", [$neighbor['sort_order'], $current['id']]);
                    execute("UPDATE asset_images SET sort_order = ? WHERE id = ?", [$current['sort_order'], $neighbor['id']]);
                    $success = 'Volgorde succesvol aangepast.';
                }
            }
        }
    }
}

$images = getAssetImages($assetId);
$pageTitle = 'Asset Foto\'s';
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header">
    <h1>Foto's beheren voor <?= htmlspecialchars($asset['asset_number']) ?></h1>
    <a href="<?= BASE_URL ?>/modules/assets/view.php?id=<?= $asset['id'] ?>" class="btn btn-secondary">← Terug naar asset</a>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul>
        <?php foreach ($errors as $error): ?>
        <li><?= htmlspecialchars($error) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- Camera opname op mobiel -->
<div class="card" style="margin-bottom:15px;">
    <div class="card-body">
        <h3 style="margin-top:0;">Foto maken met camera</h3>
        <div id="cameraSection">
            <button type="button" onclick="startCamera()" 
                    class="btn btn-primary" id="startCameraBtn">
                📷 Camera openen
            </button>
            <div id="cameraContainer" style="display:none;">
                <video id="cameraVideo" autoplay playsinline 
                       style="width:100%;border-radius:8px;margin:10px 0;">
                </video>
                <div style="display:flex;gap:10px;margin-top:10px;">
                    <button type="button" onclick="takePhoto()" 
                            class="btn btn-primary">
                        📸 Foto maken
                    </button>
                    <button type="button" onclick="stopCamera()" 
                            class="btn btn-secondary">
                        Stoppen
                    </button>
                </div>
            </div>
            <canvas id="photoCanvas" style="display:none;"></canvas>
            <div id="photoPreview" style="display:none;margin-top:10px;">
                <img id="previewImg" style="width:100%;border-radius:8px;">
                <div style="display:flex;gap:10px;margin-top:10px;">
                    <button type="button" onclick="uploadPhoto()" 
                            class="btn btn-primary">
                        ✅ Foto opslaan
                    </button>
                    <button type="button" onclick="retakePhoto()" 
                            class="btn btn-secondary">
                        🔄 Opnieuw
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-bottom:20px;">
    <div class="card-body">
        <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">Upload nieuwe foto</h3>
        <form method="post" enctype="multipart/form-data" style="display:grid;gap:15px;max-width:520px;">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="action" value="upload">
            <div class="form-group">
                <label for="image">Selecteer afbeelding</label>
                <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif,image/webp" required>
                <small>Max 5MB. JPG, PNG, GIF of WEBP.</small>
            </div>
            <button type="submit" class="btn btn-primary">Uploaden</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:20px;flex-wrap:wrap;">
            <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">Foto galery</h3>
            <div style="color:#6b7280;font-size:0.95rem;">Hoofdfoto is de eerste in de lijst.</div>
        </div>

        <?php if (empty($images)): ?>
            <p>Geen foto's gevonden voor dit asset.</p>
        <?php else: ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:15px;">
                <?php foreach ($images as $index => $image): ?>
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;position:relative;">
                    <img src="<?= BASE_URL ?>/assets/uploads/asset_images/<?= htmlspecialchars($image['filename']) ?>" alt="<?= htmlspecialchars($image['original_name']) ?>" style="width:100%;height:160px;object-fit:cover;display:block;">
                    <?php if ($index === 0): ?>
                    <span style="position:absolute;top:10px;left:10px;background:#2563eb;color:#fff;padding:4px 10px;border-radius:999px;font-size:0.78rem;font-weight:600;">Hoofdfoto</span>
                    <?php endif; ?>
                    <div style="padding:12px;display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                        <span style="font-size:0.85rem;color:#334155;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;"><?= htmlspecialchars($image['original_name']) ?></span>
                        <form method="post" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                            <input type="hidden" name="image_id" value="<?= $image['id'] ?>">
                            <input type="hidden" name="action" value="move">
                            <button type="submit" name="direction" value="up" class="btn btn-sm btn-secondary" style="padding:6px 10px;">↑</button>
                            <button type="submit" name="direction" value="down" class="btn btn-sm btn-secondary" style="padding:6px 10px;">↓</button>
                        </form>
                        <form method="post" onsubmit="return confirm('Weet je zeker dat je deze foto wilt verwijderen?');" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="image_id" value="<?= $image['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger" style="padding:6px 10px;">Verwijderen</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
let cameraStream = null;

async function startCamera() {
    try {
        cameraStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment' }
        });
        document.getElementById('cameraVideo').srcObject = cameraStream;
        document.getElementById('cameraContainer').style.display = 'block';
        document.getElementById('startCameraBtn').style.display = 'none';
        document.getElementById('photoPreview').style.display = 'none';
    } catch(e) {
        alert('Camera niet beschikbaar: ' + e.message);
    }
}

function stopCamera() {
    if (cameraStream) {
        cameraStream.getTracks().forEach(t => t.stop());
        cameraStream = null;
    }
    document.getElementById('cameraContainer').style.display = 'none';
    document.getElementById('startCameraBtn').style.display = 'inline-block';
}

function takePhoto() {
    const video = document.getElementById('cameraVideo');
    const canvas = document.getElementById('photoCanvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    document.getElementById('previewImg').src = canvas.toDataURL('image/jpeg', 0.85);
    document.getElementById('photoPreview').style.display = 'block';
    document.getElementById('cameraContainer').style.display = 'none';
    stopCamera();
}

function retakePhoto() {
    document.getElementById('photoPreview').style.display = 'none';
    startCamera();
}

async function uploadPhoto() {
    const canvas = document.getElementById('photoCanvas');
    canvas.toBlob(async function(blob) {
        const formData = new FormData();
        formData.append('image', blob, 'camera_photo.jpg');
        formData.append('action', 'upload');
        formData.append('csrf_token', '<?= generateCsrfToken() ?>');
        
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        
        if (response.ok) {
            window.location.reload();
        } else {
            alert('Upload mislukt. Probeer opnieuw.');
        }
    }, 'image/jpeg', 0.85);
}
</script>

<?php include __DIR__ . '/../../templates/footer.php';
