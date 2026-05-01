<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requirePermission('import_assets');
requireLocation();

$step = (int)($_GET['step'] ?? 1);
$userLocations = getUserLocations();
$errors = [];
$success = '';

// Zorg dat sessie bestaat
if (!isset($_SESSION['import_state'])) {
    $_SESSION['import_state'] = [];
}

// Stap 1: Upload CSV
if ($step === 1) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $errors[] = 'Ongeldige CSRF token.';
        } else {
            // Controleer locatie selectie
            $selectedLocationId = (int)($_POST['location_id'] ?? 0);
            if ($selectedLocationId <= 0) {
                $errors[] = 'Selecteer een locatie.';
            } else {
                // Controleer of gebruiker toegang heeft tot deze locatie
                $hasAccess = false;
                foreach ($userLocations as $loc) {
                    if ($loc['id'] === $selectedLocationId) {
                        $hasAccess = true;
                        break;
                    }
                }
                if (!$hasAccess) {
                    $errors[] = 'Je hebt geen toegang tot deze locatie.';
                }
            }

            if (empty($errors)) {
                if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                    $errors[] = 'Kies een CSV bestand.';
                } else {
                    $file = $_FILES['csv_file'];
                    if ($file['size'] > 2 * 1024 * 1024) {
                        $errors[] = 'Maximale bestandsgrootte is 2MB.';
                    } else {
                        $mime = mime_content_type($file['tmp_name']);
                        if (!in_array($mime, ['text/plain', 'text/csv', 'application/vnd.ms-excel'])) {
                            $errors[] = 'Kies een geldig CSV bestand.';
                        } else {
                            $content = file_get_contents($file['tmp_name']);
                            $delimiter = $_POST['delimiter'] ?? ';';

                            $lines = array_filter(array_map('trim', explode("\n", $content)));
                            if (count($lines) > 500) {
                                $errors[] = 'Maximaal 500 rijen mag geïmporteerd worden.';
                            } else {
                                // Parse CSV
                                $csvData = [];
                                $headers = null;

                                foreach ($lines as $lineNum => $line) {
                                    $row = str_getcsv($line, $delimiter);
                                    if ($lineNum === 0) {
                                        $headers = array_map('trim', $row);
                                    } else {
                                        $record = [];
                                        foreach ($headers as $idx => $header) {
                                            $record[$header] = trim($row[$idx] ?? '');
                                        }
                                        $csvData[] = $record;
                                    }
                                }

                                if (empty($csvData)) {
                                    $errors[] = 'CSV bestand bevat geen gegevens.';
                                } else if (count($csvData) > 500) {
                                    $errors[] = 'Maximaal 500 rijen per import.';
                                } else {
                                    $_SESSION['import_state']['csv_data'] = $csvData;
                                    $_SESSION['import_state']['headers'] = $headers;
                                    $_SESSION['import_state']['delimiter'] = $delimiter;
                                    $_SESSION['import_state']['location_id'] = $selectedLocationId;
                                    header('Location: ' . BASE_URL . '/modules/assets/import.php?step=2');
                                    exit;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

// Stap 2: Kolomkoppeling
elseif ($step === 2) {
    if (!isset($_SESSION['import_state']['csv_data'])) {
        header('Location: ' . BASE_URL . '/modules/assets/import.php?step=1');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $errors[] = 'Ongeldige CSRF token.';
        } else {
            $mapping = [];
            foreach ($_POST['mapping'] ?? [] as $csvCol => $assetField) {
                if ($assetField !== 'skip') {
                    $mapping[$csvCol] = $assetField;
                }
            }

            if (empty($mapping)) {
                $errors[] = 'Selecteer minstens één kolom om te importeren.';
            } else {
                $_SESSION['import_state']['mapping'] = $mapping;
                header('Location: ' . BASE_URL . '/modules/assets/import.php?step=3');
                exit;
            }
        }
    }
}

// Stap 3: Validatie en import
elseif ($step === 3) {
    if (!isset($_SESSION['import_state']['csv_data']) || !isset($_SESSION['import_state']['mapping'])) {
        header('Location: ' . BASE_URL . '/modules/assets/import.php?step=1');
        exit;
    }

    $csvData = $_SESSION['import_state']['csv_data'];
    $mapping = $_SESSION['import_state']['mapping'];
    $importLocationId = $_SESSION['import_state']['location_id'];

    // Valideer rijen (alleen valideren, NIET importeren)
    $validationResults = [];
    foreach ($csvData as $idx => $row) {
        $mappedData = [];
        foreach ($mapping as $csvCol => $assetField) {
            if (strpos($assetField, 'custom_') === 0) continue;
            $mappedData[$assetField] = $row[$csvCol] ?? '';
        }
        $validationResults[$idx] = validateAssetRow($mappedData, $importLocationId);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $errors[] = 'Ongeldige CSRF token.';
        } else {
            $importedCount = 0;
            foreach ($csvData as $idx => $row) {
                // Sla rijen met fouten over — waarschuwingen mogen wel
                if (!$validationResults[$idx]['valid']) {
                    continue;
                }
                // Gebruik de reeds geparsede data uit de validatiestap
                // (datums zijn al omgezet naar Y-m-d, ruimtes aangemaakt, etc.)
                $mappedData = $validationResults[$idx]['data'];
                $result = importAssetFromRow($mappedData, $importLocationId);
                if ($result['success']) {
                    $importedCount++;
                    // Process custom fields
                    if ($result['id']) {
                        foreach ($mapping as $csvCol => $assetField) {
                            if (strpos($assetField, 'custom_') === 0) {
                                $fieldName = substr($assetField, 7);
                                $customField = queryOne("SELECT id FROM custom_fields WHERE field_name = ?", [$fieldName]);
                                if ($customField && !empty($mappedData[$assetField])) {
                                    execute(
                                        "INSERT INTO custom_field_values (asset_id, field_id, value) VALUES (?, ?, ?) 
                                         ON DUPLICATE KEY UPDATE value = ?",
                                        [$result['id'], $customField['id'], $mappedData[$assetField], $mappedData[$assetField]]
                                    );
                                }
                            }
                        }
                    }
                }
            }

            if ($importedCount > 0) {
                $success = "$importedCount asset(s) succesvol geïmporteerd.";
                unset($_SESSION['import_state']);
                header('Location: ' . BASE_URL . '/modules/assets/?success=' . urlencode($success));
                exit;
            } else {
                $errors[] = 'Geen geldige rijen om te importeren.';
            }
        }
    }
}

$pageTitle = 'Assets importeren';
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header">
    <h1>Assets importeren</h1>
    <a href="<?= BASE_URL ?>/modules/assets/" class="btn btn-secondary">← Terug naar assets</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul>
        <?php foreach ($errors as $error): ?>
        <li><?= htmlspecialchars($error) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div style="display:flex;gap:10px;margin-bottom:30px;justify-content:center;">
    <div style="display:flex;align-items:center;gap:10px;">
        <span class="badge <?= $step >= 1 ? 'badge-success' : 'badge-secondary' ?>">1. Upload</span>
        <span style="color:#cbd5e1;">→</span>
        <span class="badge <?= $step >= 2 ? 'badge-success' : 'badge-secondary' ?>">2. Koppeling</span>
        <span style="color:#cbd5e1;">→</span>
        <span class="badge <?= $step >= 3 ? 'badge-success' : 'badge-secondary' ?>">3. Validatie</span>
    </div>
</div>

<!-- STAP 1: Upload CSV -->
<?php if ($step === 1): ?>
<div class="card">
    <div class="card-body">
        <h2>Stap 1: CSV bestand uploaden</h2>
        <p>Bereidt uw CSV bestand voor. Het bestand moet:</p>
        <ul>
            <li>Maximaal 2MB groot zijn</li>
            <li>Maximaal 500 rijen bevatten (exclusief kopregels)</li>
            <li>Een kopregels met kolomnamen hebben</li>
            <li>Gescheiden zijn door komma (,) of puntkomma (;)</li>
        </ul>

        <hr style="margin:30px 0;">

        <form method="post" enctype="multipart/form-data" style="max-width:600px;display:grid;gap:15px;">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

            <div class="form-group">
                <label>Importeer naar locatie *</label>
                <select name="location_id" class="form-control" required>
                    <option value="">Selecteer locatie...</option>
                    <?php foreach ($userLocations as $loc): ?>
                    <option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>CSV bestand *</label>
                <input type="file" name="csv_file" accept=".csv,text/csv" required>
            </div>

            <div class="form-group">
                <label>Scheidingsteken</label>
                <select name="delimiter" class="form-control">
                    <option value=";">Puntkomma (;)</option>
                    <option value=",">Komma (,)</option>
                </select>
            </div>

            <div style="background:#f3f4f6;padding:15px;border-radius:8px;">
                <strong>Voorbeeld template:</strong><br>
                <a href="<?= BASE_URL ?>/assets/downloads/import_template.csv" class="btn btn-secondary btn-small" download>
                    📥 import_template.csv
                </a>
            </div>

            <button type="submit" class="btn btn-primary">Volgende stap →</button>
        </form>
    </div>
</div>

<!-- STAP 2: Kolomkoppeling -->
<?php elseif ($step === 2): ?>
<div class="card" style="margin-bottom:20px;">
    <div class="card-body">
        <h2>Stap 2: Kolomkoppeling</h2>
        <p>Selecteer voor elke CSV kolom het corresponderende asset veld.</p>

        <div style="background:#f3f4f6;padding:15px;border-radius:8px;margin-bottom:20px;overflow-x:auto;">
            <strong style="display:block;margin-bottom:10px;">CSV preview (eerste 3 rijen):</strong>
            <table class="data-table" style="font-size:0.85rem;">
                <thead>
                    <tr>
                        <?php foreach ($_SESSION['import_state']['headers'] as $header): ?>
                        <th><?= htmlspecialchars($header) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($_SESSION['import_state']['csv_data'], 0, 3) as $row): ?>
                    <tr>
                        <?php foreach ($_SESSION['import_state']['headers'] as $header): ?>
                        <td><?= htmlspecialchars($row[$header] ?? '') ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

            <?php $customFields = query("SELECT field_name, field_label FROM custom_fields WHERE active = 1 ORDER BY sort_order"); ?>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(400px,1fr));gap:20px;">
                <?php foreach ($_SESSION['import_state']['headers'] as $csvCol): ?>
                <div class="form-group">
                    <label><?= htmlspecialchars($csvCol) ?></label>
                    <select name="mapping[<?= htmlspecialchars($csvCol) ?>]" class="form-control">
                        <option value="skip">- Niet importeren -</option>
                        <optgroup label="Standaard velden">
                            <option value="asset_number">Assetnummer</option>
                            <option value="brand">Merk</option>
                            <option value="model">Model</option>
                            <option value="type">Soort</option>
                            <option value="room">Ruimte</option>
                            <option value="serial_number">Serienummer</option>
                            <option value="status">Status</option>
                            <option value="assigned_to">In gebruik bij</option>
                            <option value="purchase_date">Aankoopdatum</option>
                            <option value="warranty_end_date">Garantie tot</option>
                            <option value="depreciation_years">Afschrijving jaren</option>
                            <option value="mac_address">MAC adres</option>
                            <option value="lan_ip_address">LAN IP adres</option>
                            <option value="management_ip">Management IP</option>
                            <option value="operating_system">Besturingssysteem</option>
                            <option value="ram">RAM</option>
                            <option value="cpu">CPU</option>
                            <option value="notes">Opmerking</option>
                            <option value="business_critical">Bedrijfskritisch</option>
                            <option value="phone_number">Telefoonnummer</option>
                            <option value="manufacturer_url">Fabrikant URL</option>
                            <option value="replacement_due_date">Vervangen o.b.v. afschrijvingstermijn</option>
                            <option value="advised_replacement_date">Advies datum vervangen</option>
                            <option value="installed_date">Geïnstalleerd op</option>
                            <option value="in_repair_since">In reparatie sinds</option>
                            <option value="out_of_service_since">Buiten gebruik sinds</option>
                            <option value="most_recent_user">Meest recente gebruiker</option>
                            <option value="access_point_number">Access Point nummer</option>
                            <option value="touchscreen_monitor_type">Monitor type</option>
                            <option value="monitor_count">Aantal monitoren</option>
                            <option value="monitor_serial">Serienummer monitor</option>
                        </optgroup>
                        <?php if (!empty($customFields)): ?>
                        <optgroup label="Custom velden">
                            <?php foreach ($customFields as $cf): ?>
                            <option value="custom_<?= htmlspecialchars($cf['field_name']) ?>">
                                <?= htmlspecialchars($cf['field_label']) ?>
                            </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endif; ?>
                    </select>
                </div>
                <?php endforeach; ?>
            </div>

            <div style="margin-top:30px;display:flex;gap:10px;flex-wrap:wrap;">
                <button type="submit" class="btn btn-primary">Controleren →</button>
                <a href="<?= BASE_URL ?>/modules/assets/import.php?step=1" class="btn btn-secondary">← Terug</a>
            </div>
        </form>
    </div>
</div>

<!-- STAP 3: Validatie en import -->
<?php elseif ($step === 3): ?>
<?php
$statsValid = 0;
$statsWarnings = 0;
$statsErrors = 0;

foreach ($validationResults as $result) {
    if ($result['valid'] && empty($result['warnings'])) {
        $statsValid++;
    } elseif ($result['valid'] && !empty($result['warnings'])) {
        $statsWarnings++;
    } else {
        $statsErrors++;
    }
}
?>
<div class="card" style="margin-bottom:20px;">
    <div class="card-body">
        <h2>Stap 3: Validatie en import</h2>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin-bottom:30px;">
            <div style="background:#d1fae5;padding:15px;border-radius:8px;border-left:4px solid #10b981;">
                <div style="font-weight:600;font-size:1.5rem;color:#059669;"><?= $statsValid ?></div>
                <div style="color:#047857;font-size:0.9rem;">Geldig</div>
            </div>
            <div style="background:#fef3c7;padding:15px;border-radius:8px;border-left:4px solid #f59e0b;">
                <div style="font-weight:600;font-size:1.5rem;color:#d97706;"><?= $statsWarnings ?></div>
                <div style="color:#b45309;font-size:0.9rem;">Waarschuwingen</div>
            </div>
            <div style="background:#fee2e2;padding:15px;border-radius:8px;border-left:4px solid #ef4444;">
                <div style="font-weight:600;font-size:1.5rem;color:#dc2626;"><?= $statsErrors ?></div>
                <div style="color:#991b1b;font-size:0.9rem;">Fouten</div>
            </div>
        </div>

        <div style="max-height:500px;overflow-y:auto;border:1px solid #e5e7eb;border-radius:8px;">
            <?php foreach ($validationResults as $idx => $result): ?>
            <div style="padding:12px;border-bottom:1px solid #f3f4f6;<?php
                if ($result['valid'] && empty($result['warnings'])) {
                    echo 'background:#f0fdf4;';
                } elseif ($result['valid'] && !empty($result['warnings'])) {
                    echo 'background:#fffbeb;';
                } else {
                    echo 'background:#fef2f2;';
                }
            ?>">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                    <?php if ($result['valid'] && empty($result['warnings'])): ?>
                        <span style="display:inline-block;width:6px;height:6px;background:#10b981;border-radius:50%;"></span>
                        <strong>Rij <?= $idx + 1 ?>: OK</strong>
                    <?php elseif ($result['valid'] && !empty($result['warnings'])): ?>
                        <span style="display:inline-block;width:6px;height:6px;background:#f59e0b;border-radius:50%;"></span>
                        <strong>Rij <?= $idx + 1 ?>: Waarschuwing</strong>
                    <?php else: ?>
                        <span style="display:inline-block;width:6px;height:6px;background:#ef4444;border-radius:50%;"></span>
                        <strong>Rij <?= $idx + 1 ?>: Fout</strong>
                    <?php endif; ?>
                </div>

                <?php if (!empty($result['warnings'])): ?>
                <div style="color:#92400e;font-size:0.9rem;margin-bottom:6px;">
                    <strong>Waarschuwingen:</strong>
                    <?php foreach ($result['warnings'] as $warning): ?>
                        <div>⚠ <?= htmlspecialchars($warning) ?></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($result['errors'])): ?>
                <div style="color:#7f1d1d;font-size:0.9rem;">
                    <strong>Fouten:</strong>
                    <?php foreach ($result['errors'] as $error): ?>
                        <div>✕ <?= htmlspecialchars($error) ?></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($statsValid > 0 || $statsWarnings > 0): ?>
        <?php if ($statsWarnings > 0 && $statsErrors === 0): ?>
        <div class="alert alert-warning" style="margin-top:16px;">
            ⚠️ <strong><?= $statsWarnings ?> rijen hebben waarschuwingen</strong> maar kunnen wel worden geïmporteerd.
            Waarschuwingen zijn meldingen (bijv. ruimte niet gevonden, datum als tekst), geen blokkerende fouten.
        </div>
        <?php endif; ?>
        <form method="post" style="margin-top:12px;">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <button type="submit" class="btn btn-primary">✓ Importeren (<?= $statsValid + $statsWarnings ?> rijen)</button>
            <a href="<?= BASE_URL ?>/modules/assets/import.php?step=1" class="btn btn-secondary">← Opnieuw beginnen</a>
        </form>
        <?php else: ?>
        <div style="margin-top:20px;">
            <a href="<?= BASE_URL ?>/modules/assets/import.php?step=1" class="btn btn-secondary">← Opnieuw beginnen</a>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../templates/footer.php';
