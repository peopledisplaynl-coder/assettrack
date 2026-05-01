<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requirePermission('view_reports');

$type   = $_GET['type']   ?? 'all';
$export = $_GET['export'] ?? '';

// Filters
$filterStatus   = $_GET['status']      ?? '';
$filterRoom     = $_GET['room']        ?? '';
$filterLocation = $_GET['location_id'] ?? '';
$filterType     = $_GET['asset_type']  ?? '';
$filterBrand    = $_GET['brand']       ?? '';
$filterMonths   = (int)($_GET['months'] ?? 12);
$showDetails    = isset($_GET['details']) && $_GET['details'] === '1';

$titles = [
    'all'          => 'Volledig asset overzicht',
    'per_room'     => 'Assets per ruimte',
    'per_location' => 'Assets per locatie',
    'per_status'   => 'Assets per status',
    'warranty'     => 'Verlopen garanties',
    'replacement'  => 'Vervanging komende periode',
    'depreciation' => 'Afschrijvingsoverzicht',
    'critical'     => 'Bedrijfskritische assets',
];
$title = $titles[$type] ?? 'Rapport';

// Helper: basis WHERE clausule bouwen
function buildWhere(string $loc, string $status, string $room, string $atype, string $brand, string $p = 'a'): array {
    $where = []; $params = [];
    if ($loc)    { $where[] = "$p.location_id = ?"; $params[] = $loc; }
    if ($status) { $where[] = "$p.status = ?";      $params[] = $status; }
    if ($room)   { $where[] = "$p.room = ?";        $params[] = $room; }
    if ($atype)  { $where[] = "$p.type = ?";        $params[] = $atype; }
    if ($brand)  { $where[] = "$p.brand LIKE ?";    $params[] = "%$brand%"; }
    return [$where, $params];
}

$detailJoin = "LEFT JOIN locations l ON a.location_id = l.id";

$summaryRows = [];
$rows        = [];

switch ($type) {

    case 'per_room':
        [$w, $p] = buildWhere($filterLocation, $filterStatus, '', $filterType, $filterBrand);
        $wc = $w ? 'WHERE '.implode(' AND ',$w) : '';
        $summaryRows = query("SELECT COALESCE(a.room,'— Geen ruimte —') as Ruimte,
            COALESCE(l.name,'— Geen locatie —') as Locatie, COUNT(*) as Totaal,
            SUM(CASE WHEN a.status='In gebruik' THEN 1 ELSE 0 END) as 'In gebruik',
            SUM(CASE WHEN a.status='Beschikbaar' THEN 1 ELSE 0 END) as Beschikbaar,
            SUM(CASE WHEN a.status='In reparatie' THEN 1 ELSE 0 END) as 'In reparatie',
            SUM(CASE WHEN a.status='Buiten gebruik' THEN 1 ELSE 0 END) as 'Buiten gebruik',
            SUM(CASE WHEN a.status='Afgevoerd' THEN 1 ELSE 0 END) as Afgevoerd
            FROM assets a $detailJoin $wc GROUP BY a.room, l.name ORDER BY l.name, a.room", $p);
        if ($showDetails || $filterRoom) {
            [$w2,$p2] = buildWhere($filterLocation,$filterStatus,$filterRoom,$filterType,$filterBrand);
            $wc2 = $w2 ? 'WHERE '.implode(' AND ',$w2) : '';
            $rows = query("SELECT a.asset_number as Assetnummer, l.name as Locatie,
                a.brand as Merk, a.model as Model, a.type as Soort, a.room as Ruimte,
                a.serial_number as Serienummer, a.status as Status,
                a.assigned_to as 'In gebruik bij', a.purchase_date as Aankoopdatum,
                a.warranty_end_date as 'Einde garantie', a.mac_address as MAC,
                a.lan_ip_address as IP, a.operating_system as OS
                FROM assets a $detailJoin $wc2 ORDER BY l.name, a.room, a.asset_number", $p2);
        }
        break;

    case 'per_location':
        [$w,$p] = buildWhere($filterLocation,$filterStatus,$filterRoom,$filterType,$filterBrand);
        $wc = $w ? 'WHERE '.implode(' AND ',$w) : '';
        $summaryRows = query("SELECT COALESCE(l.name,'— Geen locatie —') as Locatie,
            COUNT(a.id) as Totaal,
            SUM(CASE WHEN a.status='In gebruik' THEN 1 ELSE 0 END) as 'In gebruik',
            SUM(CASE WHEN a.status='Beschikbaar' THEN 1 ELSE 0 END) as Beschikbaar,
            SUM(CASE WHEN a.status='In reparatie' THEN 1 ELSE 0 END) as 'In reparatie',
            SUM(CASE WHEN a.status='Buiten gebruik' THEN 1 ELSE 0 END) as 'Buiten gebruik',
            SUM(CASE WHEN a.status='Afgevoerd' THEN 1 ELSE 0 END) as Afgevoerd
            FROM assets a $detailJoin $wc GROUP BY l.id, l.name ORDER BY l.name", $p);
        if ($showDetails) {
            [$w2,$p2] = buildWhere($filterLocation,$filterStatus,$filterRoom,$filterType,$filterBrand);
            $wc2 = $w2 ? 'WHERE '.implode(' AND ',$w2) : '';
            $rows = query("SELECT a.asset_number as Assetnummer, l.name as Locatie,
                a.brand as Merk, a.model as Model, a.type as Soort, a.room as Ruimte,
                a.status as Status, a.assigned_to as 'In gebruik bij',
                a.purchase_date as Aankoopdatum, a.warranty_end_date as 'Einde garantie'
                FROM assets a $detailJoin $wc2 ORDER BY l.name, a.room, a.asset_number", $p2);
        }
        break;

    case 'per_status':
        [$w,$p] = buildWhere($filterLocation,'',$filterRoom,$filterType,$filterBrand);
        $wc = $w ? 'WHERE '.implode(' AND ',$w) : '';
        $summaryRows = query("SELECT COALESCE(a.status,'— Onbekend —') as Status,
            COUNT(*) as Totaal,
            GROUP_CONCAT(DISTINCT a.type ORDER BY a.type SEPARATOR ', ') as 'Soorten'
            FROM assets a $detailJoin $wc GROUP BY a.status ORDER BY a.status", $p);
        if ($filterStatus || $showDetails) {
            [$w2,$p2] = buildWhere($filterLocation,$filterStatus,$filterRoom,$filterType,$filterBrand);
            $wc2 = $w2 ? 'WHERE '.implode(' AND ',$w2) : '';
            $rows = query("SELECT a.asset_number as Assetnummer, l.name as Locatie,
                a.brand as Merk, a.model as Model, a.type as Soort, a.room as Ruimte,
                a.status as Status, a.assigned_to as 'In gebruik bij',
                a.serial_number as Serienummer, a.purchase_date as Aankoopdatum,
                a.warranty_end_date as 'Einde garantie', a.notes as Opmerking
                FROM assets a $detailJoin $wc2 ORDER BY a.status, l.name, a.room, a.asset_number", $p2);
        }
        break;

    case 'warranty':
        [$w,$p] = buildWhere($filterLocation,$filterStatus,$filterRoom,$filterType,$filterBrand);
        $base = "a.warranty_end_date < CURDATE() AND a.warranty_end_date IS NOT NULL";
        $wc = $w ? "WHERE $base AND ".implode(' AND ',$w) : "WHERE $base";
        $rows = query("SELECT a.asset_number as Assetnummer, l.name as Locatie,
            a.brand as Merk, a.model as Model, a.type as Soort, a.room as Ruimte,
            a.status as Status, a.assigned_to as 'In gebruik bij',
            a.warranty_end_date as 'Garantie vervallen',
            DATEDIFF(CURDATE(), a.warranty_end_date) as 'Dagen verlopen'
            FROM assets a $detailJoin $wc ORDER BY a.warranty_end_date", $p);
        break;

    case 'replacement':
        [$w,$p] = buildWhere($filterLocation,$filterStatus,$filterRoom,$filterType,$filterBrand);
        $base = "a.purchase_date IS NOT NULL AND a.depreciation_years IS NOT NULL
                 AND DATE_ADD(a.purchase_date, INTERVAL a.depreciation_years YEAR)
                     BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? MONTH)";
        $p = array_merge([$filterMonths], $p);
        $wc = $w ? "WHERE $base AND ".implode(' AND ',$w) : "WHERE $base";
        $rows = query("SELECT a.asset_number as Assetnummer, l.name as Locatie,
            a.brand as Merk, a.model as Model, a.type as Soort, a.room as Ruimte,
            a.status as Status, a.assigned_to as 'In gebruik bij',
            a.purchase_date as Aankoopdatum, a.depreciation_years as 'Afschr. (jr)',
            DATE_ADD(a.purchase_date, INTERVAL a.depreciation_years YEAR) as Vervangingsdatum,
            DATEDIFF(DATE_ADD(a.purchase_date, INTERVAL a.depreciation_years YEAR), CURDATE()) as 'Dagen resterend',
            a.advised_replacement_date as 'Advies vervanging'
            FROM assets a $detailJoin $wc
            ORDER BY DATE_ADD(a.purchase_date, INTERVAL a.depreciation_years YEAR)", $p);
        break;

    case 'depreciation':
        [$w,$p] = buildWhere($filterLocation,$filterStatus,$filterRoom,$filterType,$filterBrand);
        $base = "a.purchase_date IS NOT NULL";
        $wc = $w ? "WHERE $base AND ".implode(' AND ',$w) : "WHERE $base";
        $rows = query("SELECT a.asset_number as Assetnummer, l.name as Locatie,
            a.brand as Merk, a.model as Model, a.type as Soort, a.room as Ruimte,
            a.status as Status, a.purchase_date as Aankoopdatum,
            a.depreciation_years as 'Afschr. (jr)',
            ROUND(DATEDIFF(CURDATE(), a.purchase_date)/365, 1) as 'Leeftijd (jr)',
            DATE_ADD(a.purchase_date, INTERVAL a.depreciation_years YEAR) as Vervangingsdatum,
            a.warranty_end_date as 'Einde garantie',
            a.advised_replacement_date as 'Advies vervanging'
            FROM assets a $detailJoin $wc
            ORDER BY DATE_ADD(a.purchase_date, INTERVAL a.depreciation_years YEAR) ASC", $p);
        break;

    case 'critical':
        [$w,$p] = buildWhere($filterLocation,$filterStatus,$filterRoom,$filterType,$filterBrand);
        $base = "a.business_critical = 1";
        $wc = $w ? "WHERE $base AND ".implode(' AND ',$w) : "WHERE $base";
        $rows = query("SELECT a.asset_number as Assetnummer, l.name as Locatie,
            a.brand as Merk, a.model as Model, a.type as Soort, a.room as Ruimte,
            a.status as Status, a.assigned_to as 'In gebruik bij',
            a.serial_number as Serienummer, a.lan_ip_address as IP,
            a.phone_number as Telefoon, a.warranty_end_date as 'Einde garantie'
            FROM assets a $detailJoin $wc ORDER BY a.status, l.name, a.room, a.brand", $p);
        break;

    default: // all
        [$w,$p] = buildWhere($filterLocation,$filterStatus,$filterRoom,$filterType,$filterBrand);
        $wc = $w ? 'WHERE '.implode(' AND ',$w) : '';
        $rows = query("SELECT a.asset_number as Assetnummer, l.name as Locatie,
            a.brand as Merk, a.model as Model, a.type as Soort, a.room as Ruimte,
            a.status as Status, a.assigned_to as 'In gebruik bij',
            a.serial_number as Serienummer, a.purchase_date as Aankoopdatum,
            a.warranty_end_date as 'Einde garantie', a.operating_system as OS,
            a.lan_ip_address as IP, a.mac_address as MAC
            FROM assets a $detailJoin $wc ORDER BY a.asset_number", $p);
        break;
}

$exportRows = !empty($rows) ? $rows : $summaryRows;

// CSV export
if ($export === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$type.'_'.date('Y-m-d').'.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    if (!empty($exportRows)) {
        fputcsv($out, array_keys($exportRows[0]), ';');
        foreach ($exportRows as $row) {
            $fmt = array_map(fn($v) => ($v && preg_match('/^\d{4}-\d{2}-\d{2}/', (string)$v))
                ? date('d-m-Y', strtotime($v)) : $v, $row);
            fputcsv($out, $fmt, ';');
        }
    }
    fclose($out); exit;
}

// Hulpdata
$userLocations = getUserLocations();
$allRooms      = getRoomsByLocation((int)$filterLocation);
$allTypes      = getAssetTypes();
$allStatuses   = getAssetStatuses();

$exportParams = http_build_query(array_filter([
    'type' => $type, 'export' => 'csv',
    'location_id' => $filterLocation, 'status' => $filterStatus,
    'room' => $filterRoom, 'asset_type' => $filterType,
    'months' => $filterMonths !== 12 ? $filterMonths : '',
    'details' => $showDetails ? '1' : '',
]));

$pageTitle = $title;
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header">
    <h1><?= htmlspecialchars($title) ?></h1>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a href="<?= BASE_URL ?>/modules/reports/generate.php?<?= $exportParams ?>" class="btn btn-secondary">📥 CSV</a>
        <button onclick="window.print()" class="btn btn-secondary">🖨️ Afdrukken</button>
        <a href="<?= BASE_URL ?>/modules/reports/" class="btn btn-secondary">← Terug</a>
    </div>
</div>

<!-- Filter paneel -->
<div class="card" style="margin-bottom:15px;">
    <div class="card-body">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
            <input type="hidden" name="type" value="<?= $type ?>">

            <?php if (count($userLocations) > 1 || getRole() === 'superadmin'): ?>
            <div>
                <label style="font-size:0.8rem;font-weight:600;display:block;margin-bottom:3px;">Locatie</label>
                <select name="location_id" class="form-control" style="width:170px;">
                    <option value="">Alle locaties</option>
                    <?php foreach ($userLocations as $loc): ?>
                    <option value="<?= $loc['id'] ?>" <?= $filterLocation == $loc['id'] ? 'selected':'' ?>>
                        <?= htmlspecialchars($loc['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <?php if ($type !== 'per_status'): ?>
            <div>
                <label style="font-size:0.8rem;font-weight:600;display:block;margin-bottom:3px;">Status</label>
                <select name="status" class="form-control" style="width:160px;">
                    <option value="">Alle statussen</option>
                    <?php foreach ($allStatuses as $val => $lbl): ?>
                    <option value="<?= $val ?>" <?= $filterStatus===$val?'selected':'' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div>
                <label style="font-size:0.8rem;font-weight:600;display:block;margin-bottom:3px;">Soort</label>
                <select name="asset_type" class="form-control" style="width:150px;">
                    <option value="">Alle soorten</option>
                    <?php foreach ($allTypes as $t): ?>
                    <option value="<?= htmlspecialchars($t['name']) ?>" <?= $filterType===$t['name']?'selected':'' ?>>
                        <?= htmlspecialchars($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($type !== 'per_location'): ?>
            <div>
                <label style="font-size:0.8rem;font-weight:600;display:block;margin-bottom:3px;">Ruimte</label>
                <select name="room" class="form-control" style="width:160px;">
                    <option value="">Alle ruimtes</option>
                    <?php foreach ($allRooms as $r): ?>
                    <option value="<?= htmlspecialchars($r['name']) ?>" <?= $filterRoom===$r['name']?'selected':'' ?>>
                        <?= htmlspecialchars($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <?php if ($type === 'replacement'): ?>
            <div>
                <label style="font-size:0.8rem;font-weight:600;display:block;margin-bottom:3px;">Periode</label>
                <select name="months" class="form-control" style="width:130px;">
                    <?php foreach ([3=>'3 maanden',6=>'6 maanden',12=>'12 maanden',24=>'24 maanden',36=>'36 maanden'] as $m=>$l): ?>
                    <option value="<?= $m ?>" <?= $filterMonths===$m?'selected':'' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <?php if (in_array($type, ['per_room','per_location','per_status'])): ?>
            <div>
                <label style="font-size:0.8rem;font-weight:600;display:block;margin-bottom:3px;">Weergave</label>
                <select name="details" class="form-control" style="width:170px;">
                    <option value="0" <?= !$showDetails?'selected':'' ?>>Samenvatting</option>
                    <option value="1" <?= $showDetails?'selected':'' ?>>Details per asset</option>
                </select>
            </div>
            <?php endif; ?>

            <div style="display:flex;gap:6px;">
                <button type="submit" class="btn btn-primary">Toepassen</button>
                <a href="<?= BASE_URL ?>/modules/reports/generate.php?type=<?= $type ?>" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Samenvatting tabel -->
<?php if (!empty($summaryRows)): ?>
<div class="card" style="margin-bottom:15px;">
    <div class="card-body">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
            <strong style="color:#374151;"><?= count($summaryRows) ?> groep(en)</strong>
            <span style="color:#6b7280;font-size:0.8rem;">Gegenereerd op <?= date('d-m-Y H:i') ?></span>
        </div>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead><tr>
                    <?php foreach (array_keys($summaryRows[0]) as $col): ?>
                    <th><?= htmlspecialchars($col) ?></th>
                    <?php endforeach; ?>
                    <th>Details</th>
                </tr></thead>
                <tbody>
                    <?php foreach ($summaryRows as $row): ?>
                    <tr>
                        <?php foreach ($row as $key => $val): ?>
                        <td>
                            <?php
                            $statusBg = ['In gebruik'=>['#d1fae5','#065f46'],'Beschikbaar'=>['#dbeafe','#1e40af'],
                                         'In reparatie'=>['#fef3c7','#92400e'],'Buiten gebruik'=>['#fee2e2','#991b1b'],
                                         'Afgevoerd'=>['#f3f4f6','#374151']];
                            if (isset($statusBg[$key]) && (int)$val > 0):
                                [$bg,$fg] = $statusBg[$key];
                            ?>
                                <span style="background:<?= $bg ?>;color:<?= $fg ?>;padding:2px 8px;
                                             border-radius:4px;font-weight:600;"><?= $val ?></span>
                            <?php else: ?>
                                <?= htmlspecialchars((string)($val ?? '-')) ?>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                        <td>
                            <?php
                            $dp = ['type'=>$type,'details'=>'1'];
                            if ($filterLocation) $dp['location_id'] = $filterLocation;
                            if ($filterType)     $dp['asset_type']  = $filterType;
                            if ($type==='per_room'     && isset($row['Ruimte']))   $dp['room']        = $row['Ruimte'];
                            if ($type==='per_status'   && isset($row['Status']))   $dp['status']      = $row['Status'];
                            if ($type==='per_location' && isset($row['Locatie'])) {
                                foreach ($userLocations as $loc) {
                                    if ($loc['name']===$row['Locatie']) { $dp['location_id']=$loc['id']; break; }
                                }
                            }
                            ?>
                            <a href="<?= BASE_URL ?>/modules/reports/generate.php?<?= http_build_query($dp) ?>"
                               class="btn btn-sm btn-secondary">Details →</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Detail tabel -->
<?php if (!empty($rows)): ?>
<div class="card">
    <div class="card-body">
        <?php if (!empty($summaryRows)): ?>
        <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:8px;">Details per asset</h3>
        <?php endif; ?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
            <strong style="color:#374151;"><?= count($rows) ?> asset(s)</strong>
            <span style="color:#6b7280;font-size:0.8rem;">Gegenereerd op <?= date('d-m-Y H:i') ?></span>
        </div>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead><tr>
                    <?php foreach (array_keys($rows[0]) as $col): ?><th><?= htmlspecialchars($col) ?></th><?php endforeach; ?>
                    <th></th>
                </tr></thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach ($row as $key => $val): ?>
                        <td>
                        <?php
                        $v = (string)($val ?? '');
                        if ($v && preg_match('/^\d{4}-\d{2}-\d{2}/', $v)) {
                            $ts = strtotime($v);
                            $past = $ts < time();
                            $soon = !$past && $ts <= strtotime('+6 months');
                            $dateStr = date('d-m-Y', $ts);
                            $warningKeys = ['Garantie vervallen','Einde garantie','Vervangingsdatum','Advies vervanging','Advies vervangingsdatum'];
                            if ($past && in_array($key, $warningKeys)) echo '<span style="color:#dc2626;font-weight:600;">'.$dateStr.'</span>';
                            elseif ($soon && $key === 'Vervangingsdatum') echo '<span style="color:#d97706;font-weight:600;">'.$dateStr.'</span>';
                            else echo $dateStr;
                        } elseif ($key === 'Status') {
                            $c = ['In gebruik'=>'badge-success','Beschikbaar'=>'badge-info',
                                  'In reparatie'=>'badge-warning','Buiten gebruik'=>'badge-danger','Afgevoerd'=>'badge-secondary'];
                            echo '<span class="badge '.($c[$v]??'badge-secondary').'">'.htmlspecialchars($v).'</span>';
                        } else {
                            echo htmlspecialchars($v);
                        }
                        ?>
                        </td>
                        <?php endforeach; ?>
                        <td>
                            <?php $a = queryOne("SELECT id FROM assets WHERE asset_number = ?", [$row['Assetnummer']??'']); ?>
                            <?php if ($a): ?>
                            <a href="<?= BASE_URL ?>/modules/assets/view.php?id=<?= $a['id'] ?>"
                               class="btn btn-sm btn-secondary" target="_blank">↗</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php elseif (empty($summaryRows)): ?>
<div class="card">
    <div class="card-body" style="text-align:center;padding:40px;color:#6b7280;">
        Geen gegevens gevonden voor de geselecteerde filters.
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
