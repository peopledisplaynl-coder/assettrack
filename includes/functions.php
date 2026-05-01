<?php
/**
 * General utility functions
 * AssetTrack - IT Asset Management System
 */

require_once __DIR__ . '/db.php';

// ─── Validatie ────────────────────────────────────────────────────────────────

function sanitize(string $data): string {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function isValidEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function isValidIP(string $ip): bool {
    return filter_var($ip, FILTER_VALIDATE_IP) !== false;
}

function isValidMAC(string $mac): bool {
    return preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $mac) === 1;
}

// ─── Datum functies ───────────────────────────────────────────────────────────

function formatDate(?string $date, string $format = 'd-m-Y'): string {
    if (!$date || $date === '0000-00-00') return '';
    $d = DateTime::createFromFormat('Y-m-d', substr($date, 0, 10));
    if (!$d) return '';
    $year = (int)$d->format('Y');
    if ($year < 1970 || $year > 2100) return ''; // onrealistisch jaar negeren
    return $d->format($format);
}

function formatDateTime(?string $datetime, string $format = 'd-m-Y H:i'): string {
    if (!$datetime || $datetime === '0000-00-00 00:00:00') return '';
    $timestamp = strtotime($datetime);
    if (!$timestamp || $timestamp < 0) return '';
    $year = (int)date('Y', $timestamp);
    if ($year < 1970 || $year > 2100) return '';
    return date($format, $timestamp);
}

function calculateAge(?string $date): ?float {
    if (!$date || $date === '0000-00-00') return null;
    $d = DateTime::createFromFormat('Y-m-d', substr($date, 0, 10));
    if (!$d) return null;
    $year = (int)$d->format('Y');
    if ($year < 1970 || $year > 2100) return null; // ongeldige datum
    $i = (new DateTime())->diff($d);
    $age = round($i->y + ($i->m / 12) + ($i->d / 365.25), 1);
    if ($age < 0 || $age > 50) return null; // onrealistisch
    return $age;
}

function isDateInPast(?string $date): bool {
    if (!$date) return false;
    return strtotime($date) < time();
}

function isDateWithinMonths(?string $date, int $months = 6): bool {
    if (!$date) return false;
    $check = strtotime($date);
    return $check >= time() && $check <= strtotime("+$months months");
}

// ─── Keuzelijsten ─────────────────────────────────────────────────────────────

function getAssetStatuses(): array {
    return [
        'In gebruik'     => 'In gebruik',
        'Beschikbaar'    => 'Beschikbaar',
        'In reparatie'   => 'In reparatie',
        'Buiten gebruik' => 'Buiten gebruik',
        'Afgevoerd'      => 'Afgevoerd',
    ];
}

function getUserRoles(): array {
    return [
        'superadmin' => 'Superadmin',
        'admin'      => 'Admin',
        'user'       => 'Gebruiker',
        'visitor'    => 'Bezoeker',
    ];
}

function getBrands(): array {
    return query("SELECT name FROM brands WHERE active = 1 ORDER BY use_count DESC, name ASC");
}

function getAssetTypes(): array {
    return query("SELECT name FROM asset_types WHERE active = 1 ORDER BY name");
}

function registerBrandUsage(string $brand): void {
    if (empty($brand)) return;
    $existing = queryOne("SELECT id FROM brands WHERE name = ?", [$brand]);
    if ($existing) {
        execute("UPDATE brands SET use_count = use_count + 1 WHERE name = ?", [$brand]);
    } else {
        execute("INSERT INTO brands (name, active, use_count) VALUES (?, 1, 1)", [$brand]);
    }
}

function getRooms(): array {
    $locationId = getLocationId();
    $sql = "SELECT id, name, location_desc FROM rooms WHERE active = 1";
    if (getRole() !== 'superadmin' && $locationId) {
        $sql .= " AND location_id = " . (int)$locationId;
    }
    $sql .= " ORDER BY name";
    return query($sql);
}

function getRoomsByLocation(int $locationId): array {
    if (!$locationId) return getRooms();
    return query("SELECT id, name, location_desc FROM rooms WHERE active = 1 AND location_id = ? ORDER BY name", [$locationId]);
}

// ─── Asset nummer ─────────────────────────────────────────────────────────────

function generateAssetNumber(): string {
    $result = queryOne("SELECT asset_number FROM assets WHERE asset_number LIKE 'AT-%' ORDER BY CAST(SUBSTRING(asset_number, 4) AS UNSIGNED) DESC LIMIT 1");
    $next = $result ? (int)substr($result['asset_number'], 3) + 1 : 1;
    return 'AT-' . str_pad($next, 5, '0', STR_PAD_LEFT);
}

// ─── Asset CRUD ───────────────────────────────────────────────────────────────

function getAssetById(int $id): ?array {
    return queryOne("SELECT a.*, l.name as location_name, o.name as org_name
                     FROM assets a
                     LEFT JOIN locations l ON a.location_id = l.id
                     LEFT JOIN organisations o ON l.organisation_id = o.id
                     WHERE a.id = ?", [$id]);
}

function createAsset(array $data): int {
    $allowedFields = [
        'asset_number', 'location_id', 'room', 'brand', 'model', 'type',
        'serial_number', 'status', 'assigned_to', 'installed_date',
        'purchase_date', 'warranty_end_date', 'depreciation_years',
        'autoupdate_expiry', 'advised_replacement_date', 'mac_address',
        'lan_ip_address', 'management_ip', 'most_recent_user', 'notes',
        'touchscreen_monitor_type', 'monitor_count', 'monitor_serial',
        'in_repair_since', 'out_of_service_since', 'ram', 'cpu',
        'operating_system', 'business_critical', 'phone_number',
        'access_point_number', 'manufacturer_url', 'created_by',
    ];

    $fields       = [];
    $placeholders = [];
    $params       = [];

    foreach ($allowedFields as $field) {
        if (array_key_exists($field, $data)) {
            $fields[]       = $field;
            $placeholders[] = '?';
            $params[]       = $data[$field] === '' ? null : $data[$field];
        }
    }

    if (!in_array('asset_number', $fields)) {
        $fields[]       = 'asset_number';
        $placeholders[] = '?';
        $params[]       = generateAssetNumber();
    }

    if (!in_array('created_by', $fields)) {
        $fields[]       = 'created_by';
        $placeholders[] = '?';
        $params[]       = getUserId();
    }

    execute(
        "INSERT INTO assets (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")",
        $params
    );

    return (int)lastInsertId();
}

function updateAsset(int $id, array $data): bool {
    $allowedFields = [
        'room', 'brand', 'model', 'type', 'serial_number', 'status',
        'assigned_to', 'installed_date', 'purchase_date', 'warranty_end_date',
        'depreciation_years', 'autoupdate_expiry', 'advised_replacement_date',
        'mac_address', 'lan_ip_address', 'management_ip', 'most_recent_user',
        'notes', 'touchscreen_monitor_type', 'monitor_count', 'monitor_serial',
        'in_repair_since', 'out_of_service_since', 'ram', 'cpu',
        'operating_system', 'business_critical', 'phone_number',
        'access_point_number', 'manufacturer_url',
    ];

    $fields = [];
    $params = [];

    foreach ($allowedFields as $field) {
        if (array_key_exists($field, $data)) {
            $fields[] = "$field = ?";
            $params[] = $data[$field] === '' ? null : $data[$field];
        }
    }

    if (empty($fields)) return false;

    $params[] = $id;
    return execute("UPDATE assets SET " . implode(', ', $fields) . " WHERE id = ?", $params) > 0;
}

function deleteAsset(int $id): bool {
    return execute("DELETE FROM assets WHERE id = ?", [$id]) > 0;
}

function calculateAssetFields(array $asset): array {
    if (!empty($asset['purchase_date'])) {
        $asset['age_years'] = calculateAge($asset['purchase_date']);
    } else {
        $asset['age_years'] = null;
    }

    if (!empty($asset['purchase_date']) && !empty($asset['depreciation_years'])) {
        $d = new DateTime($asset['purchase_date']);
        $d->add(new DateInterval('P' . (int)$asset['depreciation_years'] . 'Y'));
        $asset['replacement_due_date'] = $d->format('Y-m-d');
    } else {
        $asset['replacement_due_date'] = null;
    }

    if (empty($asset['advised_replacement_date'])) {
        $asset['advised_replacement_date'] = $asset['replacement_due_date'];
    }

    return $asset;
}

/**
 * Get images for an asset
 * @param int $assetId
 * @return array
 */
function getAssetImages(int $assetId): array {
    return query(
        "SELECT * FROM asset_images WHERE asset_id = ? ORDER BY sort_order ASC, id ASC",
        [$assetId]
    );
}

/**
 * Get main image for an asset
 * @param int $assetId
 * @return array|null
 */
function getAssetMainImage(int $assetId): ?array {
    return queryOne(
        "SELECT * FROM asset_images WHERE asset_id = ? ORDER BY sort_order ASC, id ASC LIMIT 1",
        [$assetId]
    );
}

/**
 * Delete an asset image
 * @param int $imageId
 * @param int $assetId
 * @return bool
 */
function deleteAssetImage(int $imageId, int $assetId): bool {
    $image = queryOne("SELECT * FROM asset_images WHERE id = ? AND asset_id = ?", [$imageId, $assetId]);
    if (!$image) {
        return false;
    }

    $filePath = __DIR__ . '/../assets/uploads/asset_images/' . $image['filename'];
    if (file_exists($filePath)) {
        unlink($filePath);
    }

    execute("DELETE FROM asset_images WHERE id = ?", [$imageId]);
    return true;
}

// Valideert één CSV rij zonder te importeren
function validateAssetRow(array $data, int $locationId): array {
    $errors   = [];
    $warnings = [];

    // Datumconversie — accepteer D-M-YYYY, DD-MM-YYYY en YYYY-MM-DD
    foreach (['purchase_date', 'warranty_end_date', 'installed_date', 'replacement_due_date', 'advised_replacement_date'] as $dateField) {
        if (!empty($data[$dateField])) {
            $parsed  = null;
            $dateVal = trim($data[$dateField]);

            // Sla over als het geen datum-achtige waarde is (bevat letters)
            if (preg_match('/[a-zA-Z]/', $dateVal)) {
                $warnings[] = "Veld $dateField bevat tekst in plaats van datum ('$dateVal'), overgeslagen.";
                $data[$dateField] = null;
                continue;
            }

            // D-M-YYYY of DD-MM-YYYY (met of zonder voorloopnullen)
            if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $dateVal, $m)) {
                $day   = str_pad($m[1], 2, '0', STR_PAD_LEFT);
                $month = str_pad($m[2], 2, '0', STR_PAD_LEFT);
                $year  = (int)$m[3];
                // Sanity check: jaar moet realistisch zijn
                if ($year >= 1970 && $year <= 2100) {
                    $parsed = DateTime::createFromFormat('d-m-Y', "$day-$month-$year");
                    if ($parsed && $parsed->format('d-m-Y') === "$day-$month-$year") {
                        $data[$dateField] = $parsed->format('Y-m-d');
                    } else {
                        $warnings[] = "Ongeldige datum in $dateField (overgeslagen): $dateVal";
                        $data[$dateField] = null;
                    }
                } else {
                    $warnings[] = "Jaar buiten bereik in $dateField (overgeslagen): $dateVal";
                    $data[$dateField] = null;
                }
                continue;
            }

            // DD/MM/YYYY formaat (slash)
            if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $dateVal, $m)) {
                $day   = str_pad($m[1], 2, '0', STR_PAD_LEFT);
                $month = str_pad($m[2], 2, '0', STR_PAD_LEFT);
                $year  = (int)$m[3];
                if ($year >= 1970 && $year <= 2100) {
                    $parsed = DateTime::createFromFormat('d/m/Y', "$day/$month/$year");
                    if ($parsed) {
                        $data[$dateField] = $parsed->format('Y-m-d');
                    } else {
                        $data[$dateField] = null;
                    }
                } else {
                    $data[$dateField] = null;
                }
                continue;
            }

            // YYYY-MM-DD formaat
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dateVal, $m)) {
                $year = (int)$m[1];
                if ($year >= 1970 && $year <= 2100) {
                    $parsed = DateTime::createFromFormat('Y-m-d', $dateVal);
                    if ($parsed) {
                        $data[$dateField] = $parsed->format('Y-m-d');
                    } else {
                        $data[$dateField] = null;
                    }
                } else {
                    $data[$dateField] = null;
                }
                continue;
            }

            // Geen geldig datumformaat herkend
            $warnings[] = "Onbekend datumformaat in $dateField (overgeslagen): '$dateVal'. Gebruik DD-MM-YYYY.";
            $data[$dateField] = null;
        } else {
            $data[$dateField] = null;
        }
    }

    // Valideer asset_number uniciteit
    if (!empty($data['asset_number'])) {
        $existing = queryOne("SELECT id FROM assets WHERE asset_number = ?", [$data['asset_number']]);
        if ($existing) $errors[] = "Assetnummer bestaat al: " . $data['asset_number'];
    } else {
        $warnings[] = "Assetnummer is leeg, wordt auto-gegenereerd";
    }

    if (empty($data['brand']))  $warnings[] = 'Merk is leeg, wordt leeg gelaten.';
    if (empty($data['model']))  $warnings[] = 'Model is leeg, wordt leeg gelaten.';

    // Ruimte: haal schone naam op (strip " | Locatie" suffix uit CSV)
    // CSV formaat: "Babbel 3 | Brakken" → schone naam = "Babbel 3"
    if (!empty($data['room'])) {
        $roomVal   = trim($data['room']);
        $locId     = $locationId;
        // Strip alles na " | " — dat is de locatienaam toegevoegd door de export
        $cleanName = strpos($roomVal, '|') !== false
            ? trim(explode('|', $roomVal)[0])
            : $roomVal;

        // Zoek of ruimte al bestaat in deze locatie
        $existing = queryOne(
            "SELECT name FROM rooms WHERE location_id = ? AND name = ? AND active = 1",
            [$locId, $cleanName]
        );

        if ($existing) {
            // Ruimte bestaat al — gebruik die naam
            $data['room'] = $existing['name'];
        } else {
            // Ruimte bestaat nog niet → maak automatisch aan
            execute(
                "INSERT IGNORE INTO rooms (location_id, name, active) VALUES (?, ?, 1)",
                [$locId, $cleanName]
            );
            $data['room'] = $cleanName;
            $warnings[] = "Ruimte '$cleanName' automatisch aangemaakt.";
        }
    }

    // Valideer en normaliseer status
    $validStatuses = ['In gebruik', 'Beschikbaar', 'In reparatie', 'Buiten gebruik', 'Afgevoerd'];
    if (!empty($data['status'])) {
        if (!in_array($data['status'], $validStatuses)) {
            // Gedeeltelijke match poging
            $statusLower = strtolower($data['status']);
            $mapped = null;
            if (str_contains($statusLower, 'reparatie') || str_contains($statusLower, 'repair')) $mapped = 'In reparatie';
            elseif (str_contains($statusLower, 'gebruik') && !str_contains($statusLower, 'buiten')) $mapped = 'In gebruik';
            elseif (str_contains($statusLower, 'buiten') || str_contains($statusLower, 'vervang') || str_contains($statusLower, 'afgeschrev')) $mapped = 'Buiten gebruik';
            elseif (str_contains($statusLower, 'afgevoerd') || str_contains($statusLower, 'afvoer')) $mapped = 'Afgevoerd';
            elseif (str_contains($statusLower, 'beschikbaar') || str_contains($statusLower, 'vrij')) $mapped = 'Beschikbaar';

            if ($mapped) {
                $warnings[] = "Status '{$data['status']}' herkend als '$mapped'.";
                $data['status'] = $mapped;
            } else {
                $warnings[] = "Onbekende status '{$data['status']}', wordt 'Beschikbaar'.";
                $data['status'] = 'Beschikbaar';
            }
        }
    } else {
        $data['status'] = 'Beschikbaar';
    }

    // Valideer MAC adres
    if (!empty($data['mac_address']) && !isValidMAC($data['mac_address'])) {
        $warnings[] = "Ongeldig MAC adres: " . $data['mac_address'];
        $data['mac_address'] = '';
    }

    // Valideer IP adressen
    if (!empty($data['lan_ip_address']) && !isValidIP($data['lan_ip_address'])) {
        $warnings[] = "Ongeldig LAN IP: " . $data['lan_ip_address'];
        $data['lan_ip_address'] = '';
    }
    if (!empty($data['management_ip']) && !isValidIP($data['management_ip'])) {
        $warnings[] = "Ongeldig Management IP: " . $data['management_ip'];
        $data['management_ip'] = '';
    }

    return ['valid' => empty($errors), 'errors' => $errors, 'warnings' => $warnings, 'data' => $data];
}


// Importeert één rij (alleen aanroepen na validateAssetRow)
function importAssetFromRow(array $data, int $locationId): array {
    $data['location_id'] = $locationId;
    if (empty($data['status'])) $data['status'] = 'Beschikbaar';
    try {
        $assetId = createAsset($data);
        if (!empty($data['brand'])) registerBrandUsage($data['brand']);
        return ['success' => true, 'id' => $assetId];
    } catch (Exception $e) {
        return ['success' => false, 'errors' => [$e->getMessage()]];
    }
}


// ─── Zoeken ───────────────────────────────────────────────────────────────────

function searchAssets(string $query = '', array $filters = [], int $limit = 50, int $offset = 0): array {
    $where  = [];
    $params = [];

    $locationId = getLocationId();
    if ($locationId) {
        $where[]  = "a.location_id = ?";
        $params[] = $locationId;
    }

    if (!empty($query)) {
        $where[]  = "(a.asset_number LIKE ? OR a.brand LIKE ? OR a.model LIKE ? OR a.serial_number LIKE ? OR a.assigned_to LIKE ?)";
        $term     = "%$query%";
        $params   = array_merge($params, [$term, $term, $term, $term, $term]);
    }

    if (!empty($filters['status'])) { $where[] = "a.status = ?"; $params[] = $filters['status']; }
    if (!empty($filters['type']))   { $where[] = "a.type = ?";   $params[] = $filters['type']; }
    if (!empty($filters['room']))   { $where[] = "a.room = ?";   $params[] = $filters['room']; }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $params[] = $limit;
    $params[] = $offset;

    return query("SELECT a.*, l.name as location_name, o.name as org_name
                  FROM assets a
                  LEFT JOIN locations l ON a.location_id = l.id
                  LEFT JOIN organisations o ON l.organisation_id = o.id
                  $whereClause GROUP BY a.id ORDER BY a.asset_number LIMIT ? OFFSET ?", $params);
}

// ─── Dashboard ────────────────────────────────────────────────────────────────

function getDashboardStats(): array {
    $where  = '';
    $params = [];

    if (getRole() !== 'superadmin') {
        $locationId = getLocationId();
        if ($locationId) {
            $where    = " WHERE location_id = ?";
            $params[] = $locationId;
        }
    }

    $stats = [];
    $total = queryOne("SELECT COUNT(*) as total FROM assets" . $where, $params);
    $stats['total_assets'] = $total['total'] ?? 0;

    $statusStats = query("SELECT status, COUNT(*) as count FROM assets" . $where . " GROUP BY status", $params);
    $stats['assets_by_status'] = [];
    foreach ($statusStats as $s) {
        $stats['assets_by_status'][$s['status']] = $s['count'];
    }

    $replacementWhere = $where ? $where . " AND replacement_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 6 MONTH)"
                                : " WHERE replacement_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 6 MONTH)";
    $r = queryOne("SELECT COUNT(*) as count FROM assets" . $replacementWhere, $params);
    $stats['due_for_replacement'] = $r['count'] ?? 0;

    $warrantyWhere = $where ? $where . " AND warranty_end_date < CURDATE()"
                            : " WHERE warranty_end_date < CURDATE()";
    $w = queryOne("SELECT COUNT(*) as count FROM assets" . $warrantyWhere, $params);
    $stats['expired_warranties'] = $w['count'] ?? 0;

    return $stats;
}

function getRecentActivity(int $limit = 10): array {
    return query("SELECT al.*, u.username FROM audit_log al
                  LEFT JOIN users u ON al.user_id = u.id
                  ORDER BY al.created_at DESC LIMIT ?", [$limit]);
}

// ─── Audit log ────────────────────────────────────────────────────────────────

function logAudit(string $action, string $tableName, int $recordId, ?array $oldValues = null, ?array $newValues = null): void {
    try {
        $userId = getUserId();
        $ip     = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        execute(
            "INSERT INTO audit_log (user_id, action, table_name, record_id, old_values, new_values, ip_address, created_at) VALUES (?,?,?,?,?,?,?,NOW())",
            [
                $userId,
                $action,
                $tableName,
                $recordId,
                $oldValues ? json_encode($oldValues) : null,
                $newValues ? json_encode($newValues) : null,
                $ip,
            ]
        );
    } catch (Exception $e) {
        error_log("Audit log fout: " . $e->getMessage());
    }
}

// Alias voor backwards compatibiliteit
function logActivity(string $action, string $table, int $recordId, ?array $oldValues = null, ?array $newValues = null): void {
    logAudit($action, $table, $recordId, $oldValues, $newValues);
}

// ─── Hulpfuncties ─────────────────────────────────────────────────────────────

function generateRandomString(int $length = 10): string {
    $chars  = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $result = '';
    for ($i = 0; $i < $length; $i++) {
        $result .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $result;
}
