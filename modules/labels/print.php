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

// ─── FORMAAT DEFINITIES ───────────────────────────────────────────────────────
// Losse labelprinters: w, h, font, a4=false
// A4 vellen: w=labelbreedte, h=labelhoogte, cols, pt/pl=pagina padding, gap, a4=true

$formats = [
    // Losse labels
    'small'         => ['w'=>'38mm',   'h'=>'25mm',   'font'=>'6px', 'a4'=>false],
    'medium'        => ['w'=>'62mm',   'h'=>'29mm',   'font'=>'7px', 'a4'=>false],
    'large'         => ['w'=>'89mm',   'h'=>'36mm',   'font'=>'8px', 'a4'=>false],
    'dymo_small'    => ['w'=>'57mm',   'h'=>'32mm',   'font'=>'7px', 'a4'=>false],
    'dymo_medium'   => ['w'=>'89mm',   'h'=>'28mm',   'font'=>'7px', 'a4'=>false],
    'brother_small' => ['w'=>'29mm',   'h'=>'62mm',   'font'=>'6px', 'a4'=>false],
    'zebra_50x25'   => ['w'=>'50mm',   'h'=>'25mm',   'font'=>'6px', 'a4'=>false],

    // Avery Zweckform A4 — exacte marges
    'avery_l7160'   => ['w'=>'63.5mm', 'h'=>'38.1mm', 'font'=>'8px', 'a4'=>true, 'cols'=>3, 'pt'=>'15.1mm', 'pl'=>'7.2mm',  'gap'=>'0mm',   'rows_per_page'=>7],
    'avery_l7159'   => ['w'=>'63.5mm', 'h'=>'33.9mm', 'font'=>'7px', 'a4'=>true, 'cols'=>3, 'pt'=>'13.1mm', 'pl'=>'7.2mm',  'gap'=>'0mm',   'rows_per_page'=>8],
    'avery_l7162'   => ['w'=>'99.1mm', 'h'=>'33.9mm', 'font'=>'7px', 'a4'=>true, 'cols'=>2, 'pt'=>'13.1mm', 'pl'=>'4.6mm',  'gap'=>'2.8mm', 'rows_per_page'=>8],
    'avery_l7163'   => ['w'=>'99.1mm', 'h'=>'38.1mm', 'font'=>'8px', 'a4'=>true, 'cols'=>2, 'pt'=>'15.1mm', 'pl'=>'4.6mm',  'gap'=>'2.8mm', 'rows_per_page'=>7],
    'avery_l7165'   => ['w'=>'99.1mm', 'h'=>'67.7mm', 'font'=>'9px', 'a4'=>true, 'cols'=>2, 'pt'=>'13.5mm', 'pl'=>'4.6mm',  'gap'=>'2.8mm', 'rows_per_page'=>4],
    'avery_l7166'   => ['w'=>'99.1mm', 'h'=>'93.1mm', 'font'=>'9px', 'a4'=>true, 'cols'=>2, 'pt'=>'13.5mm', 'pl'=>'4.6mm',  'gap'=>'2.8mm', 'rows_per_page'=>3],
    'avery_l7173'   => ['w'=>'48.5mm', 'h'=>'25.4mm', 'font'=>'6px', 'a4'=>true, 'cols'=>4, 'pt'=>'21.2mm', 'pl'=>'7.2mm',  'gap'=>'0mm',   'rows_per_page'=>10],
    'avery_l7636'   => ['w'=>'48.9mm', 'h'=>'29.6mm', 'font'=>'7px', 'a4'=>true, 'cols'=>4, 'pt'=>'13.4mm', 'pl'=>'4.5mm',  'gap'=>'0mm',   'rows_per_page'=>9],

    // Generiek A4
    'a4_10'         => ['w'=>'99.1mm', 'h'=>'57mm',   'font'=>'9px', 'a4'=>true, 'cols'=>2, 'pt'=>'13.5mm', 'pl'=>'4.6mm',  'gap'=>'2.8mm', 'rows_per_page'=>5],
    'a4_21'         => ['w'=>'70mm',   'h'=>'42.3mm', 'font'=>'8px', 'a4'=>true, 'cols'=>3, 'pt'=>'0mm',    'pl'=>'0mm',    'gap'=>'0mm',   'rows_per_page'=>7],
    'a4_24'         => ['w'=>'63.5mm', 'h'=>'33.9mm', 'font'=>'7px', 'a4'=>true, 'cols'=>3, 'pt'=>'13.1mm', 'pl'=>'7.2mm',  'gap'=>'0mm',   'rows_per_page'=>8],
    'a4_40'         => ['w'=>'48.5mm', 'h'=>'25.4mm', 'font'=>'6px', 'a4'=>true, 'cols'=>4, 'pt'=>'10.7mm', 'pl'=>'4.5mm',  'gap'=>'0mm',   'rows_per_page'=>10],
    'a4_65'         => ['w'=>'38.1mm', 'h'=>'21.2mm', 'font'=>'5px', 'a4'=>true, 'cols'=>5, 'pt'=>'10.7mm', 'pl'=>'4.7mm',  'gap'=>'0mm',   'rows_per_page'=>13],
];

// Aangepast formaat
if ($format === 'custom') {
    $cw = max(20, min(200, (int)($_POST['custom_w'] ?? 62)));
    $ch = max(15, min(200, (int)($_POST['custom_h'] ?? 29)));
    $formats['custom'] = ['w'=>$cw.'mm', 'h'=>$ch.'mm', 'font'=>'7px', 'a4'=>false];
}

$size = $formats[$format] ?? $formats['medium'];
$isA4 = $size['a4'];
$cols = $size['cols'] ?? 1;
$labelsPerPage = $cols * ($size['rows_per_page'] ?? 999);

// Splits assets in pagina's voor A4
$pages = [];
if ($isA4 && $labelsPerPage > 0) {
    $pages = array_chunk($assets, $labelsPerPage);
} else {
    $pages = [$assets];
}

// QR grootte — max 75% van labelhoogte in pixels (1mm = 3.78px)
// Dit zorgt dat QR altijd binnen het label blijft
// QR pixels — klein houden, CSS schaalt mee
$hMm  = (float)$size['h'];
$qrPx = 60; // Vaste kleine grootte, CSS max-height begrenst de rest

$baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . BASE_URL;
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<title>Labels afdrukken</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: Arial, Helvetica, sans-serif; background:#f3f4f6; font-size: <?= $size['font'] ?>; }

.toolbar {
    background:#1a2332; color:white; padding:10px 16px;
    display:flex; gap:10px; align-items:center; flex-wrap:wrap;
    position:sticky; top:0; z-index:10;
}
.toolbar button,.toolbar a {
    padding:7px 14px; border-radius:4px; border:none;
    cursor:pointer; font-size:13px; text-decoration:none; font-weight:500;
}
.btn-print { background:#2563eb; color:white; }
.btn-pdf   { background:#059669; color:white; }
.btn-back  { background:#6b7280; color:white; }

<?php if ($isA4): ?>
.a4-page {
    width: 210mm;
    height: 297mm;
    margin: 10px auto;
    background: white;
    padding: <?= $size['pt'] ?? '10mm' ?> <?= $size['pl'] ?? '7mm' ?>;
    display: grid;
    grid-template-columns: repeat(<?= $cols ?>, <?= $size['w'] ?>);
    gap: <?= $size['gap'] ?? '0mm' ?>;
    align-content: start;
    overflow: hidden;
    page-break-after: always;
}
.a4-page:last-child { page-break-after: avoid; }
<?php else: ?>
.a4-page {
    padding: 20px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
<?php endif; ?>

.label {
    width: <?= $size['w'] ?>;
    height: <?= $size['h'] ?>;
    border: 1px solid #ccc;
    background: white;
    display: flex;
    align-items: stretch;
    padding: 2px 4px 2px 3px;
    overflow: hidden;
    page-break-inside: avoid;
}

/* Op scherm: subtiele scheiding zichtbaar maken */
@media screen {
    <?php if ($isA4): ?>
    .a4-page {
        gap: 1px !important;
        background: #ddd;
        padding: <?= $size['pt'] ?? '10mm' ?> <?= $size['pl'] ?? '7mm' ?>;
    }
    .label { border: none; outline: 1px solid #ccc; }
    <?php endif; ?>
}

/* Bij afdrukken: exacte afmetingen zonder gap */
@media print {
    .a4-page { gap: <?= $size['gap'] ?? '0mm' ?> !important; background: white !important; }
    .label { border: 1px solid #333 !important; outline: none !important; }
}
.label-left {
    flex: 1; min-width: 0;
    display: flex; flex-direction: column;
    justify-content: space-evenly;
    overflow: hidden; padding-right: 2px;
}
.label-right {
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    padding: 2px 3px 2px 2px;
    max-height: <?= $size['h'] ?>;
    overflow: hidden;
}
.label-right > div {
    overflow: hidden;
}
.label-right img {
    max-width: 100% !important;
    max-height: calc(<?= $size['h'] ?> - 8px) !important;
    width: auto !important;
    height: auto !important;
    display: block;
}
.asset-nr { font-weight:700; font-size:1.25em; line-height:1.1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.lbl-main { font-size:0.9em;  color:#111; line-height:1.25; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.lbl-sub  { font-size:0.8em;  color:#333; line-height:1.2;  overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

@media print {
    .toolbar { display:none !important; }
    body { background:white; }
    <?php if ($isA4): ?>
    .a4-page { margin:0 !important; }
    @page { size:A4; margin:0; }
    <?php else: ?>
    .a4-page { padding:0; }
    @page { size: <?= $size['w'] ?> <?= $size['h'] ?>; margin:0; }
    <?php endif; ?>
}
</style>
</head>
<body>

<div class="toolbar">
    <button onclick="window.print()" class="btn-print">🖨️ Afdrukken</button>
    <form method="POST" action="<?= BASE_URL ?>/modules/labels/export_pdf.php" style="display:inline;margin:0;">
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
        <input type="hidden" name="format" value="<?= htmlspecialchars($format) ?>">
        <?php foreach ($ids as $aid): ?>
        <input type="hidden" name="asset_ids[]" value="<?= $aid ?>">
        <?php endforeach; ?>
        <?php foreach ($showFields as $k => $v): if ($v): ?>
        <input type="hidden" name="<?= $k ?>" value="1">
        <?php endif; endforeach; ?>
        <?php if ($format === 'custom'): ?>
        <input type="hidden" name="custom_w" value="<?= (int)$size['w'] ?>">
        <input type="hidden" name="custom_h" value="<?= (int)$size['h'] ?>">
        <?php endif; ?>
        <button type="submit" class="btn-pdf">📄 PDF exporteren</button>
    </form>
    <a href="<?= BASE_URL ?>/modules/labels/" class="btn-back">← Terug</a>
    <span style="opacity:0.75;font-size:12px;">
        <?= count($assets) ?> label(s) —
        <?= count($pages) ?> pagina('s)
    </span>
</div>

<?php
$labelIndex = 0;
foreach ($pages as $pageAssets):
?>
<div class="a4-page">
    <?php foreach ($pageAssets as $asset):
    $qrId = 'qr_'.$labelIndex++;
    ?>
    <div class="label">
        <div class="label-left">
            <div class="asset-nr"><?= htmlspecialchars($asset['asset_number']) ?></div>
            <?php if ($showFields['show_company']): ?>
            <div class="lbl-sub"><?= htmlspecialchars($companyName) ?></div>
            <?php endif; ?>
            <?php if ($showFields['show_location'] && !empty($asset['location_id'])): ?>
            <div class="lbl-main"><?= htmlspecialchars($locationNames[$asset['location_id']] ?? '') ?></div>
            <?php endif; ?>
            <?php if ($showFields['show_brand_model']): ?>
            <div class="lbl-main"><?= htmlspecialchars(trim(($asset['brand']??'').' '.($asset['model']??''))) ?></div>
            <?php endif; ?>
            <?php if ($showFields['show_serial'] && $asset['serial_number']): ?>
            <div class="lbl-sub">S/N: <?= htmlspecialchars($asset['serial_number']) ?></div>
            <?php endif; ?>
            <?php if ($showFields['show_room'] && $asset['room']): ?>
            <div class="lbl-sub">📍 <?= htmlspecialchars($asset['room']) ?></div>
            <?php endif; ?>
            <?php if ($showFields['show_status'] && $asset['status']): ?>
            <div class="lbl-sub"><?= htmlspecialchars($asset['status']) ?></div>
            <?php endif; ?>
            <?php if ($showFields['show_ip'] && $asset['lan_ip_address']): ?>
            <div class="lbl-sub"><?= htmlspecialchars($asset['lan_ip_address']) ?></div>
            <?php endif; ?>
        </div>
        <?php if ($showFields['show_qr']): ?>
        <div class="label-right">
            <div id="<?= $qrId ?>"></div>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>

<script>
<?php
$i = 0;
foreach ($assets as $asset):
?>
new QRCode(document.getElementById("qr_<?= $i++ ?>"), {
    text: "<?= addslashes($baseUrl.'/modules/assets/scan.php?id='.$asset['id']) ?>",
    width: <?= $qrPx ?>, height: <?= $qrPx ?>,
    colorDark:"#000000", colorLight:"#ffffff",
    correctLevel: QRCode.CorrectLevel.M
});
<?php endforeach; ?>
</script>
</body>
</html>
