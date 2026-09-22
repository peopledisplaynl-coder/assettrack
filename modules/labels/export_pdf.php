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

// 'qr' = QR-code (scan met telefoon -> opent assetpagina), 'barcode' = Code128 streepjescode
// (te lezen met oudere streepjescodescanners; die geven het assetnummer als tekst door)
$codeType = ($_POST['code_type'] ?? 'qr') === 'barcode' ? 'barcode' : 'qr';

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

$company     = queryOne("SELECT name, scan_base_url FROM companies WHERE active = 1 ORDER BY id LIMIT 1");
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
    // Dymo 11355: "19 x 51mm (BxL)" -- de rol is fysiek maar 19mm breed (net als
    // de 99012-rol 36mm breed is), dus net als bij dymo_99012 hieronder wordt de
    // inhoud breed opgemaakt (51x19) en via force_rotate altijd gedraaid om op
    // de smalle rol te passen -- zie print.php voor de volledige uitleg.
    'dymo_11355'    => ['w'=>'51mm',   'h'=>'19mm',   'font'=>'6pt', 'a4'=>false, 'name'=>'Dymo 11355 (51x19mm)', 'force_rotate'=>true],
    // Dymo 99012/99017: inhoud bewust "breed" opgemaakt (89x36, net als 'large')
    // voor voldoende schrijfruimte, en via force_rotate altijd gedraaid om op de
    // fysiek 36mm-brede rol te passen — zie print.php voor de volledige uitleg.
    'dymo_99012'    => ['w'=>'89mm',   'h'=>'36mm',   'font'=>'8pt', 'a4'=>false, 'name'=>'Dymo 99012 (36x89mm)', 'force_rotate'=>true],
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
    // Avery J8651 (en gelijkwaardige "65 per vel"-producten van andere merken, bv. Label
    // Planet LP65/38): officiële template-specificatie is GEEN 0mm horizontale tussenruimte
    // tussen de kolommen, maar 2,5mm (kolom-pitch 40,6mm i.p.v. de labelbreedte van 38,1mm) --
    // geverifieerd bij twee onafhankelijke bronnen (Label Planet en MR-Label) na Tons melding
    // dat een testafdruk niet overeenkwam met zijn fysieke Avery J8651-vel. Verticaal is er
    // wél geen tussenruimte (rij-pitch = labelhoogte = 21,2mm), dat klopte al. De CSS
    // "gap"-shorthand hieronder is "gap: <rij-gap> <kolom-gap>", dus "0mm 2,5mm". Marges
    // (10,7mm boven, 4,75mm links) kwamen al overeen met de officiële specificatie.
    'a4_65'         => ['w'=>'38.1mm', 'h'=>'21.2mm', 'font'=>'5pt', 'a4'=>true, 'cols'=>5, 'pt'=>'10.7mm', 'pl'=>'4.75mm', 'gap'=>'0mm 2.5mm', 'rows_per_page'=>13, 'name'=>'A4 65/vel'],
];

if ($format === 'custom') {
    $cw = max(20, min(200, (int)($_POST['custom_w'] ?? 62)));
    $ch = max(15, min(200, (int)($_POST['custom_h'] ?? 29)));
    $formats['custom'] = ['w'=>$cw.'mm','h'=>$ch.'mm','font'=>'7pt','a4'=>false,'name'=>"Aangepast {$cw}x{$ch}mm"];
}

$size = $formats[$format] ?? $formats['medium'];
$isA4 = $size['a4'];

// Afdrukrichting voor losse labelprinters — zie print.php voor de uitleg.
// $size['w']/$size['h'] blijven de normale, leesbare labelafmeting; alleen de
// fysieke pagina ($pageW x $pageH) wordt omgewisseld, en .label-inner draait
// de content via CSS weer terug naar de juiste leesrichting.
$rotate = !$isA4 && (isset($_POST['rotate_label']) || !empty($size['force_rotate']));
$pageW  = $rotate ? $size['h'] : $size['w'];
$pageH  = $rotate ? $size['w'] : $size['h'];

$cols = $size['cols'] ?? 1;
$labelsPerPage = $cols * ($size['rows_per_page'] ?? 999);
$pages = $isA4 && $labelsPerPage > 0 ? array_chunk($assets, $labelsPerPage) : [$assets];
$hMm  = (float)$size['h'];
$wMm  = (float)$size['w'];

// Portrait (smal-en-lang) labels — zie print.php voor de uitleg.
$isPortrait = $hMm > $wMm;

// Piepklein formaat (op dit moment alleen A4 65/vel, 38,1x21,2mm): een echte
// testprint van Ton (2026-09-22) liet zien dat de QR-code op dit formaat met
// de gewone lay-out (code naast de tekst, in een smalle kolom) te klein/dicht
// werd om nog te scannen -- ook al stond er verder niets anders dan het
// assetnummer bij. Net als bij de Dymo 11355/Fichero-formaten (zie index.php,
// $maxFields) is er op zo'n klein label domweg geen ruimte voor code NAAST
// tekst. Voor deze formaten forceren we daarom (a) altijd de gestapelde
// lay-out (tekst op één regel boven, code eronder over de volle breedte) en
// (b) alleen assetnummer + code, ook als er per ongeluk toch een extra veld
// werd meegepost -- exact Tons eigen conclusie na zijn testprint.
$tinyFormats = ['a4_65'];
$isTiny = in_array($format, $tinyFormats, true);
if ($isTiny) {
    foreach (['show_company','show_location','show_brand_model','show_serial','show_room','show_status','show_ip'] as $tf) {
        $showFields[$tf] = false;
    }
}

$stacked = $isTiny || $codeType === 'barcode' || $isPortrait;

// Lettergrootte in mm, gebaseerd op de krapste afmeting — zie print.php.
$fontBasisMm = min($wMm, $hMm);
$fontMm = max(2.2, min(4.5, $fontBasisMm * 0.13));

// Veilige rand rondom de content in mm (i.p.v. de vorige px-waarden, die op
// deze kleine labelmaten nog geen halve mm marge gaven) — zie print.php voor
// de volledige uitleg. Fix n.a.v. een echte printtest (2026-09-10, Dymo
// 11355) waarbij tekst en QR-code er net af werden gesneden.
// AANVULLING (2026-09-10, tweede testronde): 0,8-1,5mm bleek nog niet
// helemaal genoeg -- marge opgehoogd naar 1,5-2,2mm.
$safeEdgeMm = max(1.5, min(2.2, $fontBasisMm * 0.1));

// QR op hoge resolutie genereren, pas daarna via CSS verkleinen — zie print.php.
$qrPx = (int)max(300, min(900, round($fontBasisMm * 14)));

// Barcode (Code128): vaste lage brongrootte zodat de beeldverhouding
// breed-en-laag blijft — zie print.php.
$barcodeHeightPx = 55;
// Aandeel van de labelhoogte gereserveerd voor de code bij een gestapelde
// layout: een streepjescode heeft meer ruimte nodig dan een QR-code. Voor een
// piepklein formaat ($isTiny) is dit een voorlopige schatting -- hieronder
// (na het berekenen van $assetNrFontMm) wordt dit voor een QR-code op zo'n
// formaat vervangen door een preciezere berekening.
$codeFlexPct = $isTiny
    ? ($codeType === 'barcode' ? 82 : 78)
    : ($codeType === 'barcode' ? 58 : 42);
// Aandeel van de labelBREEDTE gereserveerd voor de code bij de NIET-gestapelde
// (naast-elkaar) layout -- fix voor de te-grote-QR/afgekapte-tekst bug, zie
// de uitgebreide toelichting in print.php bij dezelfde variabele. AANVULLING
// (2026-09-08): schaalt af op basis van de daadwerkelijke lettergrootte
// ($fontMm), niet alleen de breedte -- zie de uitgebreide toelichting in
// print.php bij dezelfde variabele voor waarom breedte-alleen niet volstond.
$assetNrFontMm = $fontMm * 1.15; // .asset-nr is 1.15em (was 1.25em, iets kleiner na testronde 2026-09-10)
$estCharWidthMm = $assetNrFontMm * 0.62;
$neededTextMm = 12 * $estCharWidthMm + 2;
$maxCodeMm = max(8, $wMm - $neededTextMm);
$sideCodeFlexPct = (int) max(20, min(38, round($maxCodeMm / $wMm * 100)));

// AANVULLING (2026-09-22, tweede testronde op A4 65/vel): een echte
// test-print van Ton liet zien dat de code met de vaste 78% hierboven juist
// veel TE GROOT werd en buiten het label viel. Oorzaak: een aparte CSS-bug
// (zie de ".label-stacked .label-right > div"-regel verderop) waardoor de
// QR-afbeelding zich alleen naar de BREEDTE van zijn vak voegde, niet naar
// de (veel kleinere) hoogte -- op een liggend label als dit (breder dan
// hoog) werd de code daardoor zo groot als de volle breedte toestond, ver
// voorbij de hoogte die er nog over was. Die CSS-bug is hieronder opgelost,
// maar daarmee wordt een vast percentage voor dit extreme formaat ook
// meteen te KLEIN: er wordt dan twee keer een "veilige rand" afgetrokken
// (rond het hele label, én rond het code-vak zelf), wat op een label van
// maar 21,2mm hoog zwaar doorweegt. Daarom hier, alleen voor een QR-code
// (geen streepjescode) op een piepklein formaat, een berekening zoals bij
// $sideCodeFlexPct hierboven: hoeveel hoogte heeft de ENE tekstregel (het
// assetnummer) daadwerkelijk nodig, en geef de code al het overige -- met
// een veel kleinere eigen rand rond het code-vak zelf ($tinyCodePadMm),
// want het label heeft via $safeEdgeMm al een veilige rand aan de buitenkant.
$tinyCodePadMm = $safeEdgeMm; // standaard: net als ieder ander gestapeld formaat
if ($isTiny && $codeType !== 'barcode') {
    $tinyTextRowMm   = $assetNrFontMm * 1.1 + 0.6; // regelhoogte (line-height 1.1) + kleine marge
    $tinyAvailableMm = $hMm - 2 * $safeEdgeMm;
    $codeFlexPct     = (int) max(50, min(90, round((($tinyAvailableMm - $tinyTextRowMm) / $tinyAvailableMm) * 100)));
    $tinyCodePadMm   = 0.3;
}

// Streepjescode op een portrait-label: draai alleen de code-afbeelding 90°
// binnen zijn vak — zie print.php.
$barcodeRotate = $stacked && $codeType === 'barcode' && $isPortrait;
$codeBoxWMm = max(8, $wMm - 3);
$codeBoxHMm = max(8, $hMm * ($codeFlexPct / 100) - 3);

// Op verzoek van Ton (2026-09-18): een vast, door de superadmin ingesteld domein
// gebruiken voor de QR-code/streepjescode als dat is ingevuld bij Instellingen ->
// Scan-domein (modules/settings/scan_domain.php) -- zo blijven NIEUW afgedrukte
// labels naar het juiste domein verwijzen, ook als AssetTrack ondertussen op een
// ander domein draait dan waar deze pagina nu toevallig op wordt geopend. Zonder
// ingesteld domein: zoals voorheen automatisch het domein van dit verzoek.
$baseUrl = !empty($company['scan_base_url'])
    ? rtrim($company['scan_base_url'], '/') . BASE_URL
    : (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . BASE_URL;
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.5/JsBarcode.all.min.js"></script>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:Arial,Helvetica,sans-serif; font-size:<?= $fontMm ?>mm; background:white; }
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
    width:<?= $pageW ?>; height:<?= $pageH ?>;
    border:1px solid #333; background:white;
    display:flex; align-items:center; justify-content:center;
    overflow:hidden;
    page-break-inside:avoid;
}
.label-inner {
    width:<?= $size['w'] ?>; height:<?= $size['h'] ?>;
    flex-shrink:0;
    display:flex; align-items:stretch;
    padding:<?= $safeEdgeMm ?>mm;
    <?php if ($rotate): ?>transform:rotate(90deg);<?php endif; ?>
}
.label-left { flex:1; min-width:0; display:flex; flex-direction:column; justify-content:space-evenly; overflow:hidden; padding-right:2px; }
.label-right { display:flex; align-items:center; justify-content:center; flex:0 0 <?= $sideCodeFlexPct ?>%; max-width:<?= $sideCodeFlexPct ?>%; max-height:calc(<?= $size['h'] ?> - <?= $safeEdgeMm * 2 ?>mm); padding:<?= $safeEdgeMm ?>mm; overflow:hidden; }
/* .label-right heeft nu een VASTE, beperkte breedte (flex-basis + max-width)
   EN een hoogtelimiet, zodat de QR-afbeelding er nooit meer uit kan groeien
   dan bedoeld -- fix voor de te-grote-QR/afgekapte-tekst bug. Zie print.php
   voor de volledige toelichting. Omdat het vak nu een DEFINITIEVE breedte
   heeft (i.p.v. "fit-content"), resolvet 100% op de afbeelding erin nu wel
   betrouwbaar. */
/* 90% i.p.v. 100% -- extra paar mm eigen witruimte rond de QR-code, los van
   de rand-marge van het label zelf -- zie print.php voor de uitleg. */
.label-right img { max-width:90% !important; max-height:90% !important; width:auto !important; height:auto !important; display:block; }
/* Iets ruimer dan de algemene 90% hierboven, specifiek voor het piepkleine
   formaat ($isTiny) -- die heeft door $tinyCodePadMm al een veel kleinere
   eigen rand om het code-vak, dus kan de code-afbeelding zelf ook een groter
   deel van dat vak vullen. Zelfde specificiteit als de regel hierboven, dus
   wint via cascade omdat hij later in de bron staat -- alleen van toepassing
   als de img ook de "qrbox-tiny"-klasse heeft (zie de markup verderop). */
.qrbox-tiny img { max-width:96% !important; max-height:96% !important; }
.label-stacked .label-right svg { max-width:100% !important; max-height:100% !important; width:100% !important; height:100% !important; display:block; }
/* De QRCode.js-bibliotheek plaatst de QR-afbeelding in een eigen wrapper-div
   (nodig voor de library, anders dan een streepjescode die direct als <svg>
   in .label-right staat). Die wrapper-div had tot nu toe GEEN eigen
   breedte/hoogte -- de breedte werd impliciet beperkt door flexbox, maar de
   hoogte bleef "auto" (content-based). Daardoor kon het percentage in
   "max-height:90%" op de afbeelding hierboven niet resolven (een
   hoogte-percentage tegen een auto-hoogte ouder telt in CSS als "none"), en
   werd de (vierkante) QR-afbeelding alleen door haar BREEDTE begrensd. Op een
   liggend label (breder dan hoog), zoals het piepkleine A4 65/vel-formaat in
   gestapelde stand, werd de code daardoor veel te GROOT en viel hij buiten
   zijn vak -- exact wat Ton's testprint liet zien. Met een expliciete
   breedte/hoogte van 100% op de wrapper-div werken beide max-width/max-height
   regels op de afbeelding weer betrouwbaar. */
.label-stacked .label-right > div { width:100%; height:100%; }
/* Stapel-layout: tekst boven, code eronder over de volle breedte i.p.v.
   tekst-links/code-rechts -- zie print.php voor de volledige uitleg. */
.label-stacked { flex-direction:column; align-items:stretch; }
.label-stacked .label-left { flex:1 1 auto; padding-right:0; min-height:0; }
.label-stacked .label-left .lbl-main,
.label-stacked .label-left .lbl-sub {
    white-space:normal; overflow-wrap:break-word; text-overflow:clip;
    display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;
}
.label-stacked .label-right { flex:0 0 <?= $codeFlexPct ?>%; max-width:none; max-height:none; width:100%; padding:<?= $tinyCodePadMm ?>mm; }
/* Streepjescode op een portrait-label: de code-afbeelding wordt 90° gedraaid
   binnen zijn vak -- zie print.php voor de uitleg. */
.barcode-rotate-wrap { width:<?= $codeBoxHMm ?>mm; height:<?= $codeBoxWMm ?>mm; transform:rotate(90deg); }
.barcode-rotate-wrap svg { max-width:100% !important; max-height:100% !important; width:100% !important; height:100% !important; display:block; }
.asset-nr { font-weight:700; font-size:1.15em; line-height:1.1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; text-align:center; }
.lbl-main { font-size:0.9em; color:#111; line-height:1.25; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.lbl-sub  { font-size:0.8em; color:#333; line-height:1.2;  overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

@media print {
    .info { display:none !important; }
    <?php if ($isA4): ?>
    .a4-page { margin:0 !important; }
    @page { size:A4; margin:0; }
    <?php else: ?>
    .a4-page { padding:0; }
    @page { size:<?= $pageW ?> <?= $pageH ?>; margin:0; }
    <?php endif; ?>
}
</style>
</head>
<body>
<div class="info">
    <strong>AssetTrack Labels</strong> — <?= htmlspecialchars($size['name'] ?? $format) ?> —
    <?= count($assets) ?> label(s), <?= count($pages) ?> pagina('s) — <?= date('d-m-Y H:i') ?><br>
    Open in browser → <strong>Ctrl+P</strong> → <strong>Opslaan als PDF</strong>
    <?php if (!$isA4): ?> | Paginaformaat: <strong><?= $size['w'] ?> × <?= $size['h'] ?></strong><?= $rotate ? ' (90° gedraaid)' : '' ?><?php endif; ?>
    <?php if ($isTiny): ?><br>ℹ️ Klein formaat: er wordt alleen het assetnummer + de <?= $codeType==='barcode'?'streepjescode':'QR-code' ?> getoond (groter afgedrukt), voor een leesbare/scanbare code.<?php endif; ?>
</div>

<?php
$li = 0;
foreach ($pages as $pageAssets):
?>
<div class="a4-page">
    <?php foreach ($pageAssets as $asset): $codeId='code_'.$li++; ?>
    <div class="label">
        <div class="label-inner<?= $stacked ? ' label-stacked' : '' ?>">
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
        <?php if ($showFields['show_qr']): ?>
        <div class="label-right">
            <?php if ($codeType === 'barcode'): ?>
                <?php if ($barcodeRotate): ?>
                <div class="barcode-rotate-wrap"><svg id="<?= $codeId ?>"></svg></div>
                <?php else: ?>
                <svg id="<?= $codeId ?>"></svg>
                <?php endif; ?>
            <?php else: ?>
            <div id="<?= $codeId ?>"<?= ($isTiny && $codeType !== 'barcode') ? ' class="qrbox-tiny"' : '' ?>></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>

<script>
<?php if ($codeType === 'barcode'): ?>
<?php $i=0; foreach ($assets as $asset): ?>
try {
    JsBarcode("#code_<?= $i++ ?>", "<?= addslashes($asset['asset_number']) ?>", {
        format: "CODE128", displayValue: true, fontSize: <?= max(10, (int)round($barcodeHeightPx * 0.16)) ?>,
        height: <?= $barcodeHeightPx ?>, width: 2, margin: 2
    });
} catch (e) { console.error('Barcode fout voor asset <?= (int)$asset['id'] ?>:', e); }
<?php endforeach; ?>
<?php else: ?>
<?php $i=0; foreach ($assets as $asset): ?>
new QRCode(document.getElementById("code_<?= $i++ ?>"),{
    text:"<?= addslashes($baseUrl.'/modules/assets/scan.php?id='.$asset['id']) ?>",
    width:<?= $qrPx ?>,height:<?= $qrPx ?>,
    colorDark:"#000000",colorLight:"#ffffff",correctLevel:QRCode.CorrectLevel.M
});
<?php endforeach; ?>
<?php endif; ?>
</script>
</body>
</html>
