<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requirePermission('print_labels');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    header('Location: ' . BASE_URL . '/modules/labels/');
    exit;
}

$ids    = array_map('intval', $_POST['asset_ids'] ?? []);
$format = $_POST['format'] ?? 'medium';
if (empty($ids)) {
    header('Location: ' . BASE_URL . '/modules/labels/?error=Geen+assets+geselecteerd');
    exit;
}

$showFields = [
    'show_asset_number' => true,
    'show_qr'           => true,
    'show_company'      => isset($_POST['show_company']),
    'show_location'     => isset($_POST['show_location']),
    'show_brand_model'  => isset($_POST['show_brand_model']),
    'show_serial'       => isset($_POST['show_serial']),
    'show_room'         => isset($_POST['show_room']),
    'show_status'       => isset($_POST['show_status']),
    'show_ip'           => isset($_POST['show_ip']),
];

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$assets = query("SELECT a.*, l.name as location_name FROM assets a
    LEFT JOIN locations l ON a.location_id = l.id WHERE a.id IN ($placeholders)", $ids);

$company     = queryOne("SELECT name FROM companies WHERE active = 1 ORDER BY id LIMIT 1");
$companyName = !empty($company['name']) ? $company['name'] : 'AssetTrack';
$locationNames = [];
foreach (query("SELECT id, name FROM locations WHERE active = 1") as $loc) {
    $locationNames[$loc['id']] = $loc['name'];
}

$formats = [
    'small'         => ['w'=>'38mm',   'h'=>'25mm',   'font'=>'6pt', 'a4'=>false, 'name'=>'Klein 38x25mm'],
    'medium'        => ['w'=>'62mm',   'h'=>'29mm',   'font'=>'7pt', 'a4'=>false, 'name'=>'Middel 62x29mm'],
    'large'         => ['w'=>'89mm',   'h'=>'36mm',   'font'=>'8pt', 'a4'=>false, 'name'=>'Groot 89x36mm'],
    'dymo_small'    => ['w'=>'57mm',   'h'=>'32mm',   'font'=>'7pt', 'a4'=>false, 'name'=>'Dymo 57x32mm'],
    'dymo_medium'   => ['w'=>'89mm',   'h'=>'28mm',   'font'=>'7pt', 'a4'=>false, 'name'=>'Dymo 89x28mm'],
    'brother_small' => ['w'=>'29mm',   'h'=>'62mm',   'font'=>'6pt', 'a4'=>false, 'name'=>'Brother 29x62mm'],
    'zebra_50x25'   => ['w'=>'50mm',   'h'=>'25mm',   'font'=>'6pt', 'a4'=>false, 'name'=>'Zebra 50x25mm'],
    'avery_l7160'   => ['w'=>'63.5mm', 'h'=>'38.1mm', 'font'=>'8pt', 'a4'=>true, 'cols'=>3, 'pt'=>'15.1mm', 'pl'=>'7.2mm',  'gap'=>'0mm',   'rows_per_page'=>7,  'name'=>'Avery L7160 21/vel'],
    'avery_l7159'   => ['w'=>'63.5mm', 'h'=>'33.9mm', 'font'=>'7pt', 'a4'=>true, 'cols'=>3, 'pt'=>'13.1mm', 'pl'=>'7.2mm',  'gap'=>'0mm',   'rows_per_page'=>8,  'name'=>'Avery L7159 24/vel'],
    'avery_l7162'   => ['w'=>'99.1mm', 'h'=>'33.9mm', 'font'=>'7pt', 'a4'=>true, 'cols'=>2, 'pt'=>'13.1mm', 'pl'=>'4.6mm',  'gap'=>'2.8mm', 'rows_per_page'=>8,  'name'=>'Avery L7162 16/vel'],
    'avery_l7163'   => ['w'=>'99.1mm', 'h'=>'38.1mm', 'font'=>'8pt', 'a4'=>true, 'cols'=>2, 'pt'=>'15.1mm', 'pl'=>'4.6mm',  'gap'=>'2.8mm', 'rows_per_page'=>7,  'name'=>'Avery L7163 14/vel'],
    'avery_l7165'   => ['w'=>'99.1mm', 'h'=>'67.7mm', 'font'=>'9pt', 'a4'=>true, 'cols'=>2, 'pt'=>'13.5mm', 'pl'=>'4.6mm',  'gap'=>'2.8mm', 'rows_per_page'=>4,  'name'=>'Avery L7165 8/vel'],
    'avery_l7166'   => ['w'=>'99.1mm', 'h'=>'93.1mm', 'font'=>'9pt', 'a4'=>true, 'cols'=>2, 'pt'=>'13.5mm', 'pl'=>'4.6mm',  'gap'=>'2.8mm', 'rows_per_page'=>3,  'name'=>'Avery L7166 6/vel'],
    'avery_l7173'   => ['w'=>'48.5mm', 'h'=>'25.4mm', 'font'=>'6pt', 'a4'=>true, 'cols'=>4, 'pt'=>'21.2mm', 'pl'=>'7.2mm',  'gap'=>'0mm',   'rows_per_page'=>10, 'name'=>'Avery L7173 40/vel'],
    'avery_l7636'   => ['w'=>'48.9mm', 'h'=>'29.6mm', 'font'=>'7pt', 'a4'=>true, 'cols'=>4, 'pt'=>'13.4mm', 'pl'=>'4.5mm',  'gap'=>'0mm',   'rows_per_page'=>9,  'name'=>'Avery L7636 36/vel'],
    'a4_10'         => ['w'=>'99.1mm', 'h'=>'57mm',   'font'=>'9pt', 'a4'=>true, 'cols'=>2, 'pt'=>'13.5mm', 'pl'=>'4.6mm',  'gap'=>'2.8mm', 'rows_per_page'=>5,  'name'=>'A4 10/vel'],
    'a4_21'         => ['w'=>'70mm',   'h'=>'42.3mm', 'font'=>'8pt', 'a4'=>true, 'cols'=>3, 'pt'=>'0mm',    'pl'=>'0mm',    'gap'=>'0mm',   'rows_per_page'=>7,  'name'=>'A4 21/vel'],
    'a4_24'         => ['w'=>'63.5mm', 'h'=>'33.9mm', 'font'=>'7pt', 'a4'=>true, 'cols'=>3, 'pt'=>'13.1mm', 'pl'=>'7.2mm',  'gap'=>'0mm',   'rows_per_page'=>8,  'name'=>'A4 24/vel'],
    'a4_40'         => ['w'=>'48.5mm', 'h'=>'25.4mm', 'font'=>'6pt', 'a4'=>true, 'cols'=>4, 'pt'=>'10.7mm', 'pl'=>'4.5mm',  'gap'=>'0mm',   'rows_per_page'=>10, 'name'=>'A4 40/vel'],
    'a4_65'         => ['w'=>'38.1mm', 'h'=>'21.2mm', 'font'=>'5pt', 'a4'=>true, 'cols'=>5, 'pt'=>'10.7mm', 'pl'=>'4.7mm',  'gap'=>'0mm',   'rows_per_page'=>13, 'name'=>'A4 65/vel'],
];

if ($format === 'custom') {
    $cw = max(20, min(200, (int)($_POST['custom_w'] ?? 62)));
    $ch = max(15, min(200, (int)($_POST['custom_h'] ?? 29)));
    $formats['custom'] = ['w'=>$cw.'mm','h'=>$ch.'mm','font'=>'7pt','a4'=>false,'name'=>"Aangepast {$cw}x{$ch}mm"];
}

$size = $formats[$format] ?? $formats['medium'];
$isA4 = $size['a4'];
$cols = $size['cols'] ?? 1;
$labelsPerPage = $cols * ($size['rows_per_page'] ?? 999);
$pages = $isA4 && $labelsPerPage > 0 ? array_chunk($assets, $labelsPerPage) : [$assets];
$hMm  = (float)$size['h'];
$qrPx = max(35, min((int)($hMm * 3.78 * 0.75), 80));
$baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . BASE_URL;
$fname = 'labels_' . $format . '_' . date('Y-m-d') . '.html';

header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $fname . '"');
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<title>AssetTrack Labels — <?= htmlspecialchars($size['name'] ?? $format) ?></title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:Arial,Helvetica,sans-serif; font-size:<?= $size['font'] ?>; background:white; }
.info { background:#1a2332; color:white; padding:8px 12px; font-size:11pt; margin-bottom:10px; }
.info strong { color:#60a5fa; }

<?php if ($isA4): ?>
.a4-page {
    width:210mm; height:297mm;
    padding: <?= $size['pt']??'10mm' ?> <?= $size['pl']??'7mm' ?>;
    display:grid;
    grid-template-columns: repeat(<?= $cols ?>, <?= $size['w'] ?>);
    gap: <?= $size['gap']??'0mm' ?>;
    align-content:start;
    overflow:hidden;
    page-break-after:always;
    background:white;
}
.a4-page:last-child { page-break-after:avoid; }
<?php else: ?>
.a4-page { padding:10px; display:flex; flex-wrap:wrap; gap:6px; }
<?php endif; ?>

.label {
    width:<?= $size['w'] ?>; height:<?= $size['h'] ?>;
    border:1px solid #333; background:white;
    display:flex; align-items:stretch;
    padding:2px 2px 2px 3px; overflow:hidden;
    page-break-inside:avoid;
}
.label-left { flex:1; min-width:0; display:flex; flex-direction:column; justify-content:space-evenly; overflow:hidden; padding-right:2px; }
.label-right { display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.asset-nr { font-weight:700; font-size:1.25em; line-height:1.1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.lbl-main { font-size:0.9em; color:#111; line-height:1.25; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.lbl-sub  { font-size:0.8em; color:#333; line-height:1.2;  overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

@media print {
    .info { display:none !important; }
    <?php if ($isA4): ?>
    .a4-page { margin:0 !important; }
    @page { size:A4; margin:0; }
    <?php else: ?>
    .a4-page { padding:0; }
    @page { size:<?= $size['w'] ?> <?= $size['h'] ?>; margin:0; }
    <?php endif; ?>
}
</style>
</head>
<body>
<div class="info">
    <strong>AssetTrack Labels</strong> — <?= htmlspecialchars($size['name'] ?? $format) ?> —
    <?= count($assets) ?> label(s), <?= count($pages) ?> pagina('s) — <?= date('d-m-Y H:i') ?><br>
    Open in browser → <strong>Ctrl+P</strong> → <strong>Opslaan als PDF</strong>
    <?php if (!$isA4): ?> | Paginaformaat: <strong><?= $size['w'] ?> × <?= $size['h'] ?></strong><?php endif; ?>
</div>

<?php
$li = 0;
foreach ($pages as $pageAssets):
?>
<div class="a4-page">
    <?php foreach ($pageAssets as $asset): $qrId='qr_'.$li++; ?>
    <div class="label">
        <div class="label-left">
            <div class="asset-nr"><?= htmlspecialchars($asset['asset_number']) ?></div>
            <?php if ($showFields['show_company']): ?><div class="lbl-sub"><?= htmlspecialchars($companyName) ?></div><?php endif; ?>
            <?php if ($showFields['show_location'] && !empty($asset['location_id'])): ?><div class="lbl-main"><?= htmlspecialchars($locationNames[$asset['location_id']]??'') ?></div><?php endif; ?>
            <?php if ($showFields['show_brand_model']): ?><div class="lbl-main"><?= htmlspecialchars(trim(($asset['brand']??'').' '.($asset['model']??''))) ?></div><?php endif; ?>
            <?php if ($showFields['show_serial'] && $asset['serial_number']): ?><div class="lbl-sub">S/N: <?= htmlspecialchars($asset['serial_number']) ?></div><?php endif; ?>
            <?php if ($showFields['show_room'] && $asset['room']): ?><div class="lbl-sub">📍 <?= htmlspecialchars($asset['room']) ?></div><?php endif; ?>
            <?php if ($showFields['show_status'] && $asset['status']): ?><div class="lbl-sub"><?= htmlspecialchars($asset['status']) ?></div><?php endif; ?>
            <?php if ($showFields['show_ip'] && $asset['lan_ip_address']): ?><div class="lbl-sub"><?= htmlspecialchars($asset['lan_ip_address']) ?></div><?php endif; ?>
        </div>
        <?php if ($showFields['show_qr']): ?><div class="label-right"><div id="<?= $qrId ?>"></div></div><?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>

<script>
<?php $i=0; foreach ($assets as $asset): ?>
new QRCode(document.getElementById("qr_<?= $i++ ?>"),{
    text:"<?= addslashes($baseUrl.'/modules/assets/scan.php?id='.$asset['id']) ?>",
    width:<?= $qrPx ?>,height:<?= $qrPx ?>,
    colorDark:"#000000",colorLight:"#ffffff",correctLevel:QRCode.CorrectLevel.M
});
<?php endforeach; ?>
</script>
</body>
</html>
