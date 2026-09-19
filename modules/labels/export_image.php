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

// Op verzoek van Ton (2026-09-19): labels kunnen printen op een Fichero Mini
// Pocket Printer type 5836 (Bluetooth-labelprinter, 30x14mm labels), die alleen
// via de eigen Fichero-telefoon-app bediend kan worden -- er bestaat geen
// publieke/officiële koppel-API (alleen een niet-officiële, reverse-engineered
// Bluetooth-protocollisting van een derde, te fragiel/onbetrouwbaar om hier op
// te bouwen), en de app is alleen via Bluetooth aan te spreken, niet via de
// normale browser-printdialoog die de rest van deze module gebruikt. Deze
// pagina genereert daarom, anders dan print.php/export_pdf.php, GEEN
// printbare/HTML-pagina maar losse PNG-AFBEELDINGEN (één per label, exact op
// het formaat van het label) die de gebruiker zelf in de Fichero-app importeert
// (via "importeren vanuit galerij", een functie die de meeste vergelijkbare
// mini-Bluetooth-labelprinter-apps van deze familie bieden) om te printen.
// Bewust minimalistisch gehouden (alleen assetnummer + code, geen extra
// velden) -- 30x14mm is fysiek te klein voor meer, net als bij de Dymo 11355
// hiernaast in de labelformaten-lijst.
$ids = array_map('intval', $_POST['asset_ids'] ?? []);
if (empty($ids)) {
    header('Location: ' . BASE_URL . '/modules/labels/?error=Geen+assets+geselecteerd');
    exit;
}

$codeType = ($_POST['code_type'] ?? 'qr') === 'barcode' ? 'barcode' : 'qr';

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$assets = query("SELECT id, asset_number FROM assets WHERE id IN ($placeholders) ORDER BY asset_number", $ids);

if (empty($assets)) {
    header('Location: ' . BASE_URL . '/modules/labels/?error=Geen+assets+gevonden');
    exit;
}

$company = queryOne("SELECT scan_base_url FROM companies WHERE active = 1 ORDER BY id LIMIT 1");
// Zelfde vast-domein-logica als print.php/export_pdf.php (Instellingen -> Scan-domein).
$baseUrl = !empty($company['scan_base_url'])
    ? rtrim($company['scan_base_url'], '/') . BASE_URL
    : (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . BASE_URL;

$pageTitle = 'Fichero pocket printer — labelafbeeldingen';
include __DIR__ . '/../../templates/header.php';
?>
<style>
.fichero-intro {
    background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px;
    padding:14px 16px; margin-bottom:18px; color:#1e3a8a; font-size:0.9rem; line-height:1.6;
}
.fichero-intro strong { color:#1e40af; }
.fichero-grid {
    display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:16px;
}
.fichero-card {
    background:white; border:1px solid #e2e8f0; border-radius:10px; padding:14px;
    display:flex; flex-direction:column; align-items:center; gap:10px;
}
.fichero-card .lbl-title { font-weight:700; color:#0f172a; font-size:0.95rem; }
.fichero-card img.label-preview {
    width:100%; max-width:300px; height:auto; border:1px solid #cbd5e1; border-radius:4px;
    background:white; image-rendering:pixelated;
}
.fichero-card .save-hint { font-size:0.78rem; color:#6b7280; text-align:center; }
.fichero-card a.btn-dl {
    display:inline-block; padding:7px 14px; border-radius:6px; background:#2563eb;
    color:white; text-decoration:none; font-size:0.85rem; font-weight:600;
}
</style>

<div class="page-header">
    <h1>📱 Fichero pocket printer — labelafbeeldingen</h1>
    <a href="<?= BASE_URL ?>/modules/labels/" class="btn btn-secondary">← Terug</a>
</div>

<div class="fichero-intro">
    <strong>Zo print je deze labels:</strong> hieronder staat per asset een losse afbeelding, precies op
    het formaat van een 30×14mm-label. Tik op een afbeelding en kies "Afbeelding opslaan" (of gebruik de
    downloadknop eronder), open daarna de <strong>Fichero-app</strong> en importeer die afbeelding via de
    eigen import-vanuit-galerij-functie om af te drukken. Dit werkt per label afzonderlijk — deze printer
    heeft geen koppeling met AssetTrack zelf (Fichero biedt daar geen officiële mogelijkheid toe), dus dit
    tussenstapje is helaas nodig.
</div>

<div class="fichero-grid" id="ficheroGrid">
    <?php foreach ($assets as $i => $asset): ?>
    <div class="fichero-card" data-idx="<?= $i ?>" data-asset-number="<?= htmlspecialchars($asset['asset_number'], ENT_QUOTES) ?>">
        <div class="lbl-title"><?= htmlspecialchars($asset['asset_number']) ?></div>
        <canvas class="label-canvas" width="720" height="336" style="display:none;"></canvas>
        <img class="label-preview" alt="Label voor <?= htmlspecialchars($asset['asset_number'], ENT_QUOTES) ?>">
        <div class="save-hint">Lang indrukken (mobiel) of rechtsklikken (pc) → "Afbeelding opslaan"</div>
        <a href="#" class="btn-dl" download="<?= htmlspecialchars(preg_replace('/[^A-Za-z0-9_-]/', '_', $asset['asset_number']), ENT_QUOTES) ?>.png">⬇ PNG opslaan</a>
    </div>
    <?php endforeach; ?>
</div>

<!-- Offscreen container waar qrcodejs/JsBarcode hun brontekening in neerzetten,
     voordat we die met drawImage overnemen in ons eigen label-canvas hierboven. -->
<div id="codeScratch" style="position:absolute;left:-9999px;top:-9999px;"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.5/JsBarcode.all.min.js"></script>
<script>
// Labeldata vanuit PHP -- alleen wat hier echt nodig is (geen extra velden,
// dit formaat is bewust minimalistisch, zie de PHP-toelichting bovenaan).
const FICHERO_ASSETS = <?= json_encode(array_map(function ($a) use ($baseUrl) {
    return [
        'number' => $a['asset_number'],
        'url'    => $baseUrl . '/modules/assets/scan.php?id=' . $a['id'],
    ];
}, $assets)) ?>;
const CODE_TYPE = <?= json_encode($codeType) ?>;

// Canvas-afmetingen: 720x336px voor een 30x14mm label = 24px/mm (~610dpi-
// equivalent) -- ruim scherp genoeg, ook nadat de Fichero-app het zelf nog
// een keer terugschaalt naar de daadwerkelijke printerresolutie.
const CANVAS_W = 720, CANVAS_H = 336, SCALE = 24; // px per mm

function drawQrLayout(ctx, codeImg, number) {
    // QR-code links, vierkant, zoveel mogelijk van de hoogte; assetnummer
    // rechts ernaast, verticaal gecentreerd, lettergrootte die automatisch
    // krimpt tot het past.
    const margin = 16;
    const qrSize = CANVAS_H - margin * 2;
    ctx.drawImage(codeImg, margin, margin, qrSize, qrSize);

    const textX0 = margin * 2 + qrSize;
    const textAreaW = CANVAS_W - textX0 - margin;
    let fontPx = 96;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillStyle = '#000000';
    do {
        ctx.font = 'bold ' + fontPx + 'px Arial, Helvetica, sans-serif';
        var w = ctx.measureText(number).width;
        if (w <= textAreaW || fontPx <= 18) break;
        fontPx -= 4;
    } while (true);
    ctx.fillText(number, textX0 + textAreaW / 2, CANVAS_H / 2, textAreaW);
}

function drawBarcodeLayout(ctx, codeCanvas, number) {
    // Streepjescode is van nature breed-en-laag: assetnummer boven, code
    // eronder over de volle breedte (zelfde indeling als print.php bij een
    // streepjescode op een klein label).
    const margin = 14;
    const textH = 100;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillStyle = '#000000';
    let fontPx = 72;
    do {
        ctx.font = 'bold ' + fontPx + 'px Arial, Helvetica, sans-serif';
        var w = ctx.measureText(number).width;
        if (w <= CANVAS_W - margin * 2 || fontPx <= 18) break;
        fontPx -= 4;
    } while (true);
    ctx.fillText(number, CANVAS_W / 2, margin + textH / 2, CANVAS_W - margin * 2);

    const bcAreaY = margin + textH;
    const bcAreaH = CANVAS_H - bcAreaY - margin;
    // Streepjescode-bron behoudt zijn eigen beeldverhouding; schalen op basis
    // van de krapste as zodat hij niet vervormt en toch zo groot mogelijk is.
    const srcRatio = codeCanvas.width / codeCanvas.height;
    let dw = CANVAS_W - margin * 2, dh = dw / srcRatio;
    if (dh > bcAreaH) { dh = bcAreaH; dw = dh * srcRatio; }
    const dx = (CANVAS_W - dw) / 2;
    const dy = bcAreaY + (bcAreaH - dh) / 2;
    ctx.drawImage(codeCanvas, dx, dy, dw, dh);
}

function renderLabel(card, data, index) {
    return new Promise((resolve) => {
        const scratch = document.getElementById('codeScratch');
        scratch.innerHTML = '';
        const canvas = card.querySelector('.label-canvas');
        const img    = card.querySelector('.label-preview');
        const ctx    = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, CANVAS_W, CANVAS_H);

        if (CODE_TYPE === 'barcode') {
            const bcCanvas = document.createElement('canvas');
            scratch.appendChild(bcCanvas);
            try {
                JsBarcode(bcCanvas, data.number, {
                    format: 'CODE128', displayValue: false, height: 200, width: 4, margin: 4,
                });
            } catch (e) { console.error('Barcode fout voor', data.number, e); }
            drawBarcodeLayout(ctx, bcCanvas, data.number);
            finish();
        } else {
            const qrDiv = document.createElement('div');
            scratch.appendChild(qrDiv);
            new QRCode(qrDiv, {
                text: data.url, width: 512, height: 512,
                colorDark: '#000000', colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M,
            });
            // qrcodejs tekent synchroon een <canvas> (en/of <img>) in qrDiv,
            // maar de <img data-src>/toDataURL-afronding kan een tick later
            // klaar zijn -- daarom een korte, veilige polling-wachtlus i.p.v.
            // er blind van uitgaan dat het element bij de volgende regel al
            // een geldige afbeelding bevat.
            const start = Date.now();
            (function waitForQr() {
                const qrCanvas = qrDiv.querySelector('canvas');
                const qrImg    = qrDiv.querySelector('img');
                const src = qrCanvas || (qrImg && qrImg.complete && qrImg.naturalWidth ? qrImg : null);
                if (src) {
                    drawQrLayout(ctx, src, data.number);
                    finish();
                } else if (Date.now() - start > 2000) {
                    console.error('QR-code genereren duurde te lang voor', data.number);
                    finish();
                } else {
                    requestAnimationFrame(waitForQr);
                }
            })();
        }

        function finish() {
            const dataUrl = canvas.toDataURL('image/png');
            img.src = dataUrl;
            const dl = card.querySelector('a.btn-dl');
            dl.href = dataUrl;
            resolve();
        }
    });
}

(async function renderAll() {
    const cards = Array.from(document.querySelectorAll('.fichero-card'));
    for (let i = 0; i < cards.length; i++) {
        await renderLabel(cards[i], FICHERO_ASSETS[i], i);
    }
})();
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
