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
    // Dymo 11355: "19 x 51mm (BxL)" volgens Dymo's eigen opgave -- dat is Breedte
    // x Lengte, dus de ROL is fysiek maar 19mm breed (net als de 99012-rol 36mm
    // breed is), niet een los, standaand-breed label zoals dymo_small/medium.
    // Daarom hier -- net als bij dymo_99012 -- de INHOUD toch breed opmaken
    // (51x19, royale 51mm schrijfruimte) en via force_rotate altijd 90° draaien
    // om op de smalle rol te passen. Zonder force_rotate probeert de browser/
    // printerdriver dit 51mm-brede label op een 19mm-brede rol te persen, met
    // een afgekapte QR-code en tekst tot gevolg (bevestigd door een testprint
    // van de gebruiker op 2026-09-08).
    'dymo_11355'    => ['w'=>'51mm',   'h'=>'19mm',   'font'=>'6px', 'a4'=>false, 'force_rotate'=>true],
    // Dymo 99012/99017: de rol is fysiek 36mm breed x 89mm lang, maar de INHOUD
    // wordt bewust "breed" (89x36, net als 'large') opgemaakt -- net zoveel
    // schrijfruimte voor tekst als bij de andere Dymo-formaten -- en daarna via
    // force_rotate altijd 90° gedraaid om op de rol te passen. Puur een label in
    // portrait-vorm DEFINIËREN (36x89) zou de tekst juist in de smalle 36mm
    // proppen; dat is precies wat we hier vermijden.
    'dymo_99012'    => ['w'=>'89mm',   'h'=>'36mm',   'font'=>'8px', 'a4'=>false, 'force_rotate'=>true],
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

// Afdrukrichting voor losse labelprinters (rollen zoals Dymo/Brother/Zebra).
// Sommige printerdrivers verwachten de paginagrootte in de richting van de rol
// (rolbreedte x looplengte) i.p.v. hoe het label inhoudelijk is opgemaakt, en
// draaien de afdruk daarom 90°. $size['w']/$size['h'] blijven daarom altijd de
// NORMALE, leesbare labelafmeting -- de inhoud (tekst/QR/streepjescode) wordt
// dus nooit door elkaar gepropt. Alleen de fysieke PAGINA/het voetprint van het
// label ($pageW x $pageH, hieronder) wordt omgewisseld zodat die overeenkomt
// met wat de printer verwacht; de content zelf wordt via CSS (.label-inner)
// vervolgens weer 90° teruggedraaid naar de juiste leesrichting.
// Sommige formaten (zoals de Dymo 99012, hierboven) hebben een rol die altijd
// smaller is dan de leesbare content -- daar staat 'force_rotate' aan, en
// draaien we sowieso, los van het aan/uit-vinkje van de gebruiker.
$rotate = !$isA4 && (isset($_POST['rotate_label']) || !empty($size['force_rotate']));
$pageW  = $rotate ? $size['h'] : $size['w'];
$pageH  = $rotate ? $size['w'] : $size['h'];

$cols = $size['cols'] ?? 1;
$labelsPerPage = $cols * ($size['rows_per_page'] ?? 999);

// Splits assets in pagina's voor A4
$pages = [];
if ($isA4 && $labelsPerPage > 0) {
    $pages = array_chunk($assets, $labelsPerPage);
} else {
    $pages = [$assets];
}

$hMm = (float)$size['h'];
$wMm = (float)$size['w'];

// Portrait (smal-en-lang) labels -- zoals de Dymo 99012 (36x89mm) -- hebben een
// andere lay-out nodig dan brede/lage labels: tekst en code passen niet naast
// elkaar op maar 36mm breedte. $isPortrait bepaalt of we stapelen i.p.v. naast
// elkaar zetten.
$isPortrait = $hMm > $wMm;

// Stapel-layout (tekst boven, code eronder, code over de volle breedte):
// altijd bij een streepjescode (van nature breed-en-laag, past niet naast een
// tekstkolom), en ook bij een portrait-label (te smal voor twee kolommen naast
// elkaar, ongeacht codetype).
$stacked = $codeType === 'barcode' || $isPortrait;

// Lettergrootte in mm, gebaseerd op de KRAPSTE afmeting van het label (bij de
// meeste bestaande formaten is dat de hoogte, bij een portrait-label zoals de
// 99012 is dat juist de breedte) -- schaalt zo altijd mee met de werkelijk
// beschikbare ruimte, met een ondergrens voor leesbaarheid.
$fontBasisMm = min($wMm, $hMm);
$fontMm = max(2.2, min(4.5, $fontBasisMm * 0.13));

// Veilige rand rondom de content (in mm, niet px). Vrijwel elke losse
// labelprinter (Dymo/Brother/Zebra) heeft een klein stukje NIET-bedrukbare
// rand rond een los, gestanst label -- zeker aan de rand waar het label van
// de rol wordt afgesneden. De vorige padding stond in px (2-4px), wat op
// deze kleine labelmaten (mm-schaal) omgerekend nog geen halve mm was --
// te weinig marge, met als gevolg dat tekst/QR-code er bij een echte
// printtest (2026-09-10, Dymo 11355) net af werd gesneden. Nu een expliciete
// mm-marge, meeschalend met de labelgrootte (klein label = kleine marge,
// groter label = iets meer marge).
// AANVULLING (2026-09-10, tweede testronde): 0,8-1,5mm bleek bij een echte
// Dymo 11355-print nog niet helemaal genoeg -- er viel nog net een randje
// van de eerste letter af. Marge opgehoogd naar 1,5-2,2mm.
$safeEdgeMm = max(1.5, min(2.2, $fontBasisMm * 0.1));

// QR-code: altijd op hoge resolutie genereren (ruim boven wat er fysiek nodig is)
// en pas daarna via CSS verkleinen naar de labelgrootte. Dat geeft een scherpe,
// goed scanbare code -- de oude vaste 60px werd juist uitgerekt en dus wazig.
$qrPx = (int)max(300, min(900, round($fontBasisMm * 14)));

// Barcode (Code128): de gegenereerde bron blijft bewust LAAG (vaste hoogte, NIET
// meeschalend met de labelgrootte) zodat de eigen beeldverhouding altijd
// "breed-en-laag" blijft. De CSS hierna laat de SVG proportioneel passen, en
// gebruikt daardoor altijd zoveel mogelijk van de beschikbare BREEDTE i.p.v.
// beperkt te worden door de hoogte -- cruciaal op smalle labels, anders lopen
// de streepjes tegen elkaar aan tot een zwart vlak (wat er eerst gebeurde).
$barcodeHeightPx = 55;

// Aandeel van de labelhoogte gereserveerd voor de code bij een gestapelde
// layout: een streepjescode heeft meer ruimte nodig dan een QR-code.
$codeFlexPct = $codeType === 'barcode' ? 58 : 42;

// Aandeel van de labelBREEDTE gereserveerd voor de code bij de NIET-gestapelde
// (naast-elkaar) layout -- dit gebeurt alleen bij een QR-code op een liggend
// (niet-portrait) label, zie $stacked hierboven. BUG (opgelost op 2026-09-08):
// .label-right had voorheen GEEN eigen breedtebeperking, alleen de <img> erin
// had een max-height. Omdat .label-right als flex-item zonder eigen breedte
// zich naar zijn INHOUD voegt ("fit-content"), en de QR-afbeelding vierkant is,
// werd .label-right dus net zo BREED als hij mocht worden HOOG (bijna de volle
// labelhoogte) -- op een liggend label (breder dan hoog) at de QR-code zo
// veruit meer dan de helft van de labelbreedte op, met een veel te grote/
// afgesneden QR-code en afgekapte tekst tot gevolg. Dit trad op bij VRIJWEL
// ALLE liggende formaten (alle A4-rasters, en de meeste losse labels), precies
// zoals de gebruiker meldde ("de QR code is te groot, welk label je ook
// kiest"). Fix: .label-right krijgt nu een VASTE, beperkte breedte (percentage
// van de labelbreedte, net als $codeFlexPct dat al deed voor de hoogte bij de
// gestapelde layout) zodat er altijd ruim plek overblijft voor de tekst.
//
// AANVULLING (2026-09-08, na een echte testprint met echte assetnummers zoals
// "NUW-BEST-13"): op het KLEINSTE formaat (a4_65, 38,1mm breed) bleek zelfs
// met alleen het assetnummer erop (geen enkel ander veld) een deel van de
// nummers nog afgekapt te worden -- 38% is prima op een ruim label, maar op
// zo'n smal label eet elke extra mm voor de QR-code direct in bij de tekst.
//
// Een eerste poging om dit alleen op basis van de labelBREEDTE af te laten
// schalen bleek niet genoeg: de lettergrootte ($fontMm hierboven) is namelijk
// gebaseerd op de KRAPSTE afmeting (breedte ÉN hoogte), dus een format met een
// vergelijkbare breedte maar een grotere hoogte (bv. 'small', 38x25mm) krijgt
// juist een GROTERE letter -- die heeft dus MEER breedte nodig, niet minder.
// Een op-breedte-alleen-formule hield daar geen rekening mee en loste dat
// geval niet op. Daarom nu een schatting die uitgaat van de daadwerkelijke
// lettergrootte: hoeveel mm heeft een assetnummer van ~12 tekens (ruim
// genoeg voor bv. "NUW-BEST-144") nodig bij DEZE $fontMm, en hoeveel blijft
// er dan over voor de QR-code? 0,62 is een vuistregel voor de gemiddelde
// tekenbreedte-verhouding van een vet, hoofdletter-zwaar lettertype (Arial
// bold) t.o.v. de fontgrootte -- empirisch getoetst aan echte gerenderde
// tekstbreedtes in de testomgeving.
$assetNrFontMm = $fontMm * 1.15; // .asset-nr is 1.15em (was 1.25em, iets kleiner na testronde 2026-09-10)
$estCharWidthMm = $assetNrFontMm * 0.62;
$neededTextMm = 12 * $estCharWidthMm + 2; // +2mm speling voor padding/rand
$maxCodeMm = max(8, $wMm - $neededTextMm); // nooit kleiner dan 8mm, anders onscanbaar
$sideCodeFlexPct = (int) max(20, min(38, round($maxCodeMm / $wMm * 100)));

// Zelfs de volle labelbreedte is op een portrait-label (bv. 36mm) vaak te
// weinig voor een leesbare streepjescode. In dat geval draaien we alleen de
// streepjescode-afbeelding 90° binnen zijn eigen vak, zodat hij de langere as
// van het label (de hoogte) als breedte kan gebruiken.
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
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<title>Labels afdrukken</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.5/JsBarcode.all.min.js"></script>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: Arial, Helvetica, sans-serif; background:#f3f4f6; font-size: <?= $fontMm ?>mm; }

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
    width: <?= $pageW ?>;
    height: <?= $pageH ?>;
    border: 1px solid #ccc;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    page-break-inside: avoid;
}
/* .label-inner heeft altijd de NORMALE (leesbare) labelafmeting en bevat de
   bestaande tekst/code-indeling ongewijzigd. Bij "90° gedraaid" wordt alleen
   deze binnenkant gedraaid -- doordat hij precies gecentreerd zit in de (nu
   omgewisselde) buitenste .label, vult hij die na de draai weer exact. */
.label-inner {
    width: <?= $size['w'] ?>;
    height: <?= $size['h'] ?>;
    flex-shrink: 0;
    display: flex;
    align-items: stretch;
    padding: <?= $safeEdgeMm ?>mm;
    <?php if ($rotate): ?>
    transform: rotate(90deg);
    <?php endif; ?>
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
    flex: 0 0 <?= $sideCodeFlexPct ?>%;
    max-width: <?= $sideCodeFlexPct ?>%;
    max-height: calc(<?= $size['h'] ?> - <?= $safeEdgeMm * 2 ?>mm);
    padding: <?= $safeEdgeMm ?>mm;
    overflow: hidden;
}
/* .label-right heeft nu een VASTE, beperkte breedte (flex-basis + max-width,
   percentage van de labelbreedte -- zie $sideCodeFlexPct hierboven) EN een
   hoogtelimiet, zodat de QR-afbeelding er nooit meer uit kan groeien dan
   bedoeld. Omdat het vak zelf nu een DEFINITIEVE breedte heeft (i.p.v.
   "fit-content"), resolvet een percentage op de afbeelding erin (100%) nu wel
   betrouwbaar -- vandaar 100%/100% hieronder i.p.v. de vorige, foutgevoelige
   absolute mm-berekening. */
.label-right img {
    /* Bewust op 90% i.p.v. 100% van het beschikbare vak -- geeft de QR-code
       een extra paar mm eigen witruimte rondom, los van de rand-marge van het
       label zelf. Fix n.a.v. een echte printtest (2026-09-10, Dymo 11355)
       waarbij Ton vroeg om extra zekerheid dat de code niet aan de rand
       hangt, ook al past hij binnen zijn vak. */
    max-width: 90% !important;
    max-height: 90% !important;
    width: auto !important;
    height: auto !important;
    display: block;
}
.label-stacked .label-right svg {
    max-width: 100% !important;
    max-height: 100% !important;
    width: 100% !important;
    height: 100% !important;
    display: block;
}
/* Stapel-layout: tekst boven, code eronder over de volle breedte i.p.v.
   tekst-links/code-rechts. Gebruikt bij een streepjescode (van nature
   breed-en-laag, past niet naast een tekstkolom) en bij een portrait-label
   (te smal voor twee kolommen naast elkaar, ongeacht codetype). De code krijgt
   een VAST aandeel van de labelhoogte i.p.v. zijn eigen grootte af te dwingen,
   en langere tekstvelden mogen nu over twee regels lopen i.p.v. hard af te
   breken -- op een smal label was een enkele regel vaak te kort.*/
.label-stacked { flex-direction: column; align-items: stretch; }
.label-stacked .label-left { flex: 1 1 auto; padding-right: 0; min-height: 0; }
.label-stacked .label-left .lbl-main,
.label-stacked .label-left .lbl-sub {
    white-space: normal; overflow-wrap: break-word; text-overflow: clip;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.label-stacked .label-right {
    flex: 0 0 <?= $codeFlexPct ?>%;
    max-width: none;
    max-height: none;
    width: 100%;
    padding: <?= $safeEdgeMm ?>mm;
}
/* Streepjescode op een portrait-label: het vak zelf blijft normaal (volle
   breedte, vast hoogte-aandeel), maar de code-afbeelding erbinnen wordt 90°
   gedraaid zodat hij de langere as (labelhoogte) als breedte kan gebruiken --
   anders is zelfs de volle labelbreedte te smal voor leesbare streepjes. */
.barcode-rotate-wrap {
    width: <?= $codeBoxHMm ?>mm;
    height: <?= $codeBoxWMm ?>mm;
    transform: rotate(90deg);
}
.barcode-rotate-wrap svg {
    max-width: 100% !important;
    max-height: 100% !important;
    width: 100% !important;
    height: 100% !important;
    display: block;
}
.asset-nr { font-weight:700; font-size:1.15em; line-height:1.1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; text-align:center; }
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
    @page { size: <?= $pageW ?> <?= $pageH ?>; margin:0; }
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
        <input type="hidden" name="code_type" value="<?= htmlspecialchars($codeType) ?>">
        <?php if ($rotate): ?>
        <input type="hidden" name="rotate_label" value="1">
        <?php endif; ?>
        <?php foreach ($ids as $aid): ?>
        <input type="hidden" name="asset_ids[]" value="<?= $aid ?>">
        <?php endforeach; ?>
        <?php foreach ($showFields as $k => $v): if ($v): ?>
        <input type="hidden" name="<?= $k ?>" value="1">
        <?php endif; endforeach; ?>
        <?php if ($format === 'custom'): ?>
        <input type="hidden" name="custom_w" value="<?= (int)($_POST['custom_w'] ?? 62) ?>">
        <input type="hidden" name="custom_h" value="<?= (int)($_POST['custom_h'] ?? 29) ?>">
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
    $codeId = 'code_'.$labelIndex++;
    ?>
    <div class="label">
        <div class="label-inner<?= $stacked ? ' label-stacked' : '' ?>">
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
            <?php if ($codeType === 'barcode'): ?>
                <?php if ($barcodeRotate): ?>
                <div class="barcode-rotate-wrap"><svg id="<?= $codeId ?>"></svg></div>
                <?php else: ?>
                <svg id="<?= $codeId ?>"></svg>
                <?php endif; ?>
            <?php else: ?>
            <div id="<?= $codeId ?>"></div>
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
<?php
$i = 0;
foreach ($assets as $asset):
?>
try {
    JsBarcode("#code_<?= $i++ ?>", "<?= addslashes($asset['asset_number']) ?>", {
        format: "CODE128", displayValue: true, fontSize: <?= max(10, (int)round($barcodeHeightPx * 0.16)) ?>,
        height: <?= $barcodeHeightPx ?>, width: 2, margin: 2
    });
} catch (e) { console.error('Barcode fout voor asset <?= (int)$asset['id'] ?>:', e); }
<?php endforeach; ?>
<?php else: ?>
<?php
$i = 0;
foreach ($assets as $asset):
?>
new QRCode(document.getElementById("code_<?= $i++ ?>"), {
    text: "<?= addslashes($baseUrl.'/modules/assets/scan.php?id='.$asset['id']) ?>",
    width: <?= $qrPx ?>, height: <?= $qrPx ?>,
    colorDark:"#000000", colorLight:"#ffffff",
    correctLevel: QRCode.CorrectLevel.M
});
<?php endforeach; ?>
<?php endif; ?>
</script>
</body>
</html>
