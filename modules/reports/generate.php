<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requirePermission('view_reports');

$type   = $_GET['type']   ?? 'all';
$export = $_GET['export'] ?? '';

// ── Locatietoegang bepalen ───────────────────────────────────────
$userLocationsAll   = getUserLocations();
$allowedLocationIds = array_column($userLocationsAll, 'id');

// Filters
$filterStatus   = $_GET['status']      ?? '';
$filterRoom     = $_GET['room']        ?? '';
$filterType     = $_GET['asset_type']  ?? '';
$filterBrand    = $_GET['brand']       ?? '';
$filterMonths   = (int)($_GET['months'] ?? 12);
$showDetails    = isset($_GET['details']) && $_GET['details'] === '1';

// Locatiefilter — alleen als die in de toegestane lijst zit
$requestedLoc   = $_GET['location_id'] ?? '';
if ($requestedLoc && !in_array((int)$requestedLoc, $allowedLocationIds)) {
    $requestedLoc = ''; // Geen toegang → negeer
}
// Standaard: huidige sessielocatie
$filterLocation = $requestedLoc ?: (string)getLocationId();
// Als alleen 1 locatie beschikbaar: forceer die
if (count($allowedLocationIds) === 1) {
    $filterLocation = (string)$allowedLocationIds[0];
}

// ── Custom velden (voor filter + kolomkeuze) ─────────────────────
$allCustomFields    = query("SELECT id, field_name, field_label FROM custom_fields WHERE active = 1 ORDER BY sort_order");
$customFieldsByName = [];
foreach ($allCustomFields as $cf) { $customFieldsByName[$cf['field_name']] = $cf; }

// Filter op custom veld (eenvoudig: 1 veld + "bevat tekst")
$filterCfField = trim($_GET['cf_filter']   ?? '');
$filterCfValue = trim($_GET['cf_filter_q'] ?? '');
if ($filterCfField !== '' && !isset($customFieldsByName[$filterCfField])) {
    $filterCfField = ''; // Onbekend/inactief veld → filter negeren
}

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

// Placeholder-tekst voor een lege ruimte in de "per ruimte"-samenvatting (zie de COALESCE in de
// per_room-query hieronder) + een losse marker om vanuit de "Details"-link op die groep (of vanuit
// de ruimte-keuzelijst) ook daadwerkelijk op "geen ruimte" te kunnen filteren. De placeholder-TEKST
// zelf mag nooit als filterwaarde de WHERE in gaan (die matcht dan letterlijk niets, want geen asset
// heeft "— Geen ruimte —" als ruimtenaam) -- vandaar de losse marker. Let op: als de placeholder-
// tekst in de per_room-query hieronder ooit verandert, moet ROOM_EMPTY_LABEL mee veranderen.
define('ROOM_EMPTY_LABEL',  '— Geen ruimte —');
define('ROOM_EMPTY_MARKER', '__geen_ruimte__');

// Helper: basis WHERE clausule bouwen
// Altijd beperkt tot toegestane locaties van de ingelogde gebruiker
// $cfField/$cfValue: optioneel filteren op een custom veld ("bevat tekst")
function buildWhere(string $loc, string $status, string $room, string $atype, string $brand, string $p = 'a', string $cfField = '', string $cfValue = ''): array {
    global $allowedLocationIds;
    $where = []; $params = [];
    if ($loc) {
        // Specifieke locatie gevraagd
        $where[]  = "$p.location_id = ?";
        $params[] = (int)$loc;
    } elseif (!empty($allowedLocationIds)) {
        // Geen specifieke locatie: toon alleen toegestane locaties
        $ph       = implode(',', array_fill(0, count($allowedLocationIds), '?'));
        $where[]  = "$p.location_id IN ($ph)";
        $params   = array_merge($params, $allowedLocationIds);
    } else {
        $where[]  = '1=0'; // Geen locaties: niets tonen
    }
    if ($status) { $where[] = "$p.status = ?";      $params[] = $status; }
    if ($room) {
        if ($room === ROOM_EMPTY_MARKER) {
            // "Geen ruimte" -- komt alleen voor via de Details-link op de samenvattingsrij
            // "— Geen ruimte —" (zie hieronder) of via het bijpassende keuzelijst-item.
            $where[] = "($p.room IS NULL OR $p.room = '')";
        } else {
            $where[] = "$p.room = ?";
            $params[] = $room;
        }
    }
    if ($atype)  { $where[] = "$p.type = ?";        $params[] = $atype; }
    if ($brand)  { $where[] = "$p.brand LIKE ?";    $params[] = "%$brand%"; }
    if ($cfField !== '' && $cfValue !== '') {
        $where[]  = "$p.id IN (SELECT cfv.asset_id FROM custom_field_values cfv
                                JOIN custom_fields cf ON cf.id = cfv.field_id
                                WHERE cf.field_name = ? AND cfv.value LIKE ?)";
        $params[] = $cfField;
        $params[] = '%' . $cfValue . '%';
    }
    return [$where, $params];
}

// SQL-alias veilig als string literal wegschrijven (kolomnamen met spatie/leestekens)
function sqlAlias(string $label): string {
    return "'" . str_replace("'", "''", $label) . "'";
}

$detailJoin = "LEFT JOIN locations l ON a.location_id = l.id";

// ── Extra velden op het rapport (kolomkeuze) ──────────────────────
// Alleen voor de rapporten die één rij per asset tonen — de samenvattingen
// (per locatie/ruimte/status) blijven bij hun vaste totalenkolommen.
$reportTypesWithFieldPicker = ['all', 'warranty', 'replacement', 'depreciation', 'critical'];

$standardFieldCatalog = [
    // key => [label, sql-expressie, groep]
    'location'                 => ['Locatie',                    'l.name',               'Algemeen'],
    'serial_number'             => ['Serienummer',                 'a.serial_number',      'Algemeen'],
    'manufacturer_url'          => ['Fabrikant URL',                'a.manufacturer_url',   'Algemeen'],
    'business_critical'         => ['Bedrijfskritisch',             "CASE WHEN a.business_critical=1 THEN 'Ja' ELSE 'Nee' END", 'Algemeen'],
    'assigned_to'                => ['In gebruik bij',               'a.assigned_to',        'Gebruik'],
    'most_recent_user'          => ['Meest recente gebruiker',      'a.most_recent_user',  'Gebruik'],
    'installed_date'            => ['Geïnstalleerd op',             'a.installed_date',    'Gebruik'],
    'registration_date'         => ['Registratiedatum',             'a.registration_date', 'Gebruik'],
    'purchase_date'              => ['Aankoopdatum',                 'a.purchase_date',      'Financieel'],
    'warranty_end_date'         => ['Einde garantie',               'a.warranty_end_date', 'Financieel'],
    'depreciation_years'        => ['Afschrijving (jaren)',         'a.depreciation_years','Financieel'],
    'advised_replacement_date'  => ['Advies vervangingsdatum',      'a.advised_replacement_date', 'Financieel'],
    'replacement_due_date'      => ['Vervangingsdatum (handmatig)', 'a.replacement_due_date', 'Financieel'],
    'autoupdate_expiry'         => ['Autoupdate vervalt',           'a.autoupdate_expiry', 'Financieel'],
    'mac_address'                => ['MAC-adres',                    'a.mac_address',        'Netwerk'],
    'lan_ip_address'             => ['LAN IP-adres',                 'a.lan_ip_address',     'Netwerk'],
    'management_ip'             => ['Management IP',                'a.management_ip',     'Netwerk'],
    'access_point_number'       => ['Access Point nr',              'a.access_point_number','Netwerk'],
    'operating_system'          => ['Besturingssysteem',            'a.operating_system',  'Hardware'],
    'ram'                        => ['RAM',                          'a.ram',                'Hardware'],
    'cpu'                        => ['CPU',                          'a.cpu',                'Hardware'],
    'touchscreen_monitor_type'  => ['Monitor type',                 'a.touchscreen_monitor_type', 'Hardware'],
    'monitor_count'              => ['Aantal monitoren',             'a.monitor_count',      'Hardware'],
    'monitor_serial'            => ['Serienummer monitor',          'a.monitor_serial',    'Hardware'],
    'phone_number'               => ['Telefoonnummer',               'a.phone_number',       'Hardware'],
    'in_repair_since'            => ['In reparatie sinds',           'a.in_repair_since',   'Overig'],
    'out_of_service_since'      => ['Buiten gebruik sinds',         'a.out_of_service_since', 'Overig'],
    'notes'                       => ['Opmerking',                    'a.notes',              'Overig'],
];

// Velden die per rapport al standaard in de query zitten (voorkomt dubbele kolommen)
$coreFieldKeysByType = [
    'all'          => ['location','assigned_to','serial_number','purchase_date','warranty_end_date','operating_system','lan_ip_address','mac_address'],
    'warranty'     => ['location','assigned_to','warranty_end_date'],
    'replacement'  => ['location','assigned_to','purchase_date','depreciation_years','advised_replacement_date'],
    'depreciation' => ['location','purchase_date','depreciation_years','warranty_end_date','advised_replacement_date'],
    'critical'     => ['location','assigned_to','serial_number','lan_ip_address','phone_number','warranty_end_date'],
];

$selectedExtra  = [];
$selectedCf     = [];
$extraSelectSql = '';
$extraJoinSql   = '';

if (in_array($type, $reportTypesWithFieldPicker, true)) {
    $core = $coreFieldKeysByType[$type] ?? [];

    $reqExtra = $_GET['extra'] ?? [];
    if (!is_array($reqExtra)) $reqExtra = [];
    foreach ($reqExtra as $k) {
        if (is_string($k) && isset($standardFieldCatalog[$k]) && !in_array($k, $core, true) && !in_array($k, $selectedExtra, true)) {
            $selectedExtra[] = $k;
        }
    }

    $reqCf = $_GET['cf'] ?? [];
    if (!is_array($reqCf)) $reqCf = [];
    foreach ($reqCf as $name) {
        if (is_string($name) && isset($customFieldsByName[$name]) && !in_array($name, $selectedCf, true)) {
            $selectedCf[] = $name;
        }
    }

    foreach ($selectedExtra as $k) {
        [$label, $expr] = $standardFieldCatalog[$k];
        $extraSelectSql .= ", $expr as " . sqlAlias($label);
    }

    $cfIndex = 0;
    foreach ($selectedCf as $name) {
        $cfIndex++;
        $cfInfo = $customFieldsByName[$name];
        $alias  = "cfv$cfIndex";
        $extraJoinSql   .= " LEFT JOIN custom_field_values $alias ON $alias.asset_id = a.id AND $alias.field_id = " . (int)$cfInfo['id'];
        $extraSelectSql .= ", $alias.value as " . sqlAlias($cfInfo['field_label']);
    }
}

$summaryRows = [];
$rows        = [];

switch ($type) {

    case 'per_room':
        // BUGFIX 2026-09-18: hier stond een hardgecodeerde '' i.p.v. $filterRoom, waardoor de
        // samenvatting altijd ALLE ruimtes liet zien, ook als je een specifieke ruimte had
        // gekozen in het filter -- de ruimtefilter werkte toen alleen nog op de detailtabel.
        [$w, $p] = buildWhere($filterLocation, $filterStatus, $filterRoom, $filterType, $filterBrand, 'a', $filterCfField, $filterCfValue);
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
            [$w2,$p2] = buildWhere($filterLocation,$filterStatus,$filterRoom,$filterType,$filterBrand, 'a', $filterCfField, $filterCfValue);
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
        [$w,$p] = buildWhere($filterLocation,$filterStatus,$filterRoom,$filterType,$filterBrand, 'a', $filterCfField, $filterCfValue);
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
            [$w2,$p2] = buildWhere($filterLocation,$filterStatus,$filterRoom,$filterType,$filterBrand, 'a', $filterCfField, $filterCfValue);
            $wc2 = $w2 ? 'WHERE '.implode(' AND ',$w2) : '';
            $rows = query("SELECT a.asset_number as Assetnummer, l.name as Locatie,
                a.brand as Merk, a.model as Model, a.type as Soort, a.room as Ruimte,
                a.status as Status, a.assigned_to as 'In gebruik bij',
                a.purchase_date as Aankoopdatum, a.warranty_end_date as 'Einde garantie'
                FROM assets a $detailJoin $wc2 ORDER BY l.name, a.room, a.asset_number", $p2);
        }
        break;

    case 'per_status':
        [$w,$p] = buildWhere($filterLocation,'',$filterRoom,$filterType,$filterBrand, 'a', $filterCfField, $filterCfValue);
        $wc = $w ? 'WHERE '.implode(' AND ',$w) : '';
        $summaryRows = query("SELECT COALESCE(a.status,'— Onbekend —') as Status,
            COUNT(*) as Totaal,
            GROUP_CONCAT(DISTINCT a.type ORDER BY a.type SEPARATOR ', ') as 'Soorten'
            FROM assets a $detailJoin $wc GROUP BY a.status ORDER BY a.status", $p);
        if ($filterStatus || $showDetails) {
            [$w2,$p2] = buildWhere($filterLocation,$filterStatus,$filterRoom,$filterType,$filterBrand, 'a', $filterCfField, $filterCfValue);
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
        [$w,$p] = buildWhere($filterLocation,$filterStatus,$filterRoom,$filterType,$filterBrand, 'a', $filterCfField, $filterCfValue);
        $base = "a.warranty_end_date < CURDATE() AND a.warranty_end_date IS NOT NULL";
        $wc = $w ? "WHERE $base AND ".implode(' AND ',$w) : "WHERE $base";
        $rows = query("SELECT a.asset_number as Assetnummer, l.name as Locatie,
            a.brand as Merk, a.model as Model, a.type as Soort, a.room as Ruimte,
            a.status as Status, a.assigned_to as 'In gebruik bij',
            a.warranty_end_date as 'Garantie vervallen',
            DATEDIFF(CURDATE(), a.warranty_end_date) as 'Dagen verlopen'
            $extraSelectSql
            FROM assets a $detailJoin $extraJoinSql $wc ORDER BY a.warranty_end_date", $p);
        break;

    case 'replacement':
        [$w,$p] = buildWhere($filterLocation,$filterStatus,$filterRoom,$filterType,$filterBrand, 'a', $filterCfField, $filterCfValue);
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
            $extraSelectSql
            FROM assets a $detailJoin $extraJoinSql $wc
            ORDER BY DATE_ADD(a.purchase_date, INTERVAL a.depreciation_years YEAR)", $p);
        break;

    case 'depreciation':
        [$w,$p] = buildWhere($filterLocation,$filterStatus,$filterRoom,$filterType,$filterBrand, 'a', $filterCfField, $filterCfValue);
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
            $extraSelectSql
            FROM assets a $detailJoin $extraJoinSql $wc
            ORDER BY DATE_ADD(a.purchase_date, INTERVAL a.depreciation_years YEAR) ASC", $p);
        break;

    case 'critical':
        [$w,$p] = buildWhere($filterLocation,$filterStatus,$filterRoom,$filterType,$filterBrand, 'a', $filterCfField, $filterCfValue);
        $base = "a.business_critical = 1";
        $wc = $w ? "WHERE $base AND ".implode(' AND ',$w) : "WHERE $base";
        $rows = query("SELECT a.asset_number as Assetnummer, l.name as Locatie,
            a.brand as Merk, a.model as Model, a.type as Soort, a.room as Ruimte,
            a.status as Status, a.assigned_to as 'In gebruik bij',
            a.serial_number as Serienummer, a.lan_ip_address as IP,
            a.phone_number as Telefoon, a.warranty_end_date as 'Einde garantie'
            $extraSelectSql
            FROM assets a $detailJoin $extraJoinSql $wc ORDER BY a.status, l.name, a.room, a.brand", $p);
        break;

    default: // all
        [$w,$p] = buildWhere($filterLocation,$filterStatus,$filterRoom,$filterType,$filterBrand, 'a', $filterCfField, $filterCfValue);
        $wc = $w ? 'WHERE '.implode(' AND ',$w) : '';
        $rows = query("SELECT a.asset_number as Assetnummer, l.name as Locatie,
            a.brand as Merk, a.model as Model, a.type as Soort, a.room as Ruimte,
            a.status as Status, a.assigned_to as 'In gebruik bij',
            a.serial_number as Serienummer, a.purchase_date as Aankoopdatum,
            a.warranty_end_date as 'Einde garantie', a.operating_system as OS,
            a.lan_ip_address as IP, a.mac_address as MAC
            $extraSelectSql
            FROM assets a $detailJoin $extraJoinSql $wc ORDER BY a.asset_number", $p);
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

// PDF export — toont alle kolommen die ook op het scherm staan (incl. gekozen
// extra velden en custom velden), met automatische regelterugloop en herhaalde
// kolomkoppen per pagina, zodat niets buiten de pagina afgekapt wordt.
if ($export === 'pdf') {
    require_once __DIR__ . '/../../includes/lib/fpdf/fpdf.php';

    // FPDF verwacht CP1252; onze data komt als UTF-8 uit de database.
    $cp1252 = function ($s) {
        if ($s === null) return '';
        $s = (string)$s;
        $conv = @iconv('UTF-8', 'CP1252//TRANSLIT', $s);
        return $conv !== false ? $conv : $s;
    };

    class ReportPDF extends FPDF
    {
        public array $colWidths  = [];
        public array $colHeaders = [];
        public string $reportTitle = '';
        public string $genDate     = '';
        public float $bodyFontSize = 8;
        public float $lineH        = 4;

        public function Header(): void
        {
            if ($this->page === 1) {
                $this->SetFont('Helvetica', 'B', 13);
                $this->Cell(0, 8, $this->reportTitle, 0, 1);
                $this->SetFont('Helvetica', '', 8);
                $this->SetTextColor(100, 100, 100);
                $this->Cell(0, 5, $this->genDate, 0, 1);
                $this->SetTextColor(0, 0, 0);
                $this->Ln(1);
            }
            // Kopregel: zelfde regelterugloop-logica als de databodycellen, zodat
            // lange kolomlabels netjes over meerdere regels lopen i.p.v. elkaar
            // te overlappen (wat bij plat, niet-terugvallend tekst zou gebeuren
            // als er veel/smalle kolommen zijn).
            $this->SetFont('Helvetica', 'B', $this->bodyFontSize);
            $this->SetFillColor(37, 99, 235);
            $this->SetTextColor(255, 255, 255);
            $this->DrawRow($this->colHeaders, true);
            $this->SetTextColor(0, 0, 0);
            $this->SetFont('Helvetica', '', $this->bodyFontSize);
        }

        public function Footer(): void
        {
            $this->SetY(-12);
            $this->SetFont('Helvetica', '', 7);
            $this->SetTextColor(120, 120, 120);
            $this->Cell(0, 8, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }

        // Aantal regels dat $txt nodig heeft binnen breedte $w (officiële FPDF-techniek)
        public function NbLines(float $w, string $txt): int
        {
            $cw = $this->CurrentFont['cw'];
            $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
            $s = str_replace("\r", '', $txt);
            $nb = strlen($s);
            if ($nb > 0 && $s[$nb - 1] === "\n") $nb--;
            $sep = -1; $i = 0; $j = 0; $l = 0; $nl = 1;
            while ($i < $nb) {
                $c = $s[$i];
                if ($c === "\n") { $i++; $sep = -1; $j = $i; $l = 0; $nl++; continue; }
                if ($c === ' ') $sep = $i;
                $l += $cw[ord($c)] ?? 600;
                if ($l > $wmax) {
                    if ($sep === -1) { if ($i === $j) $i++; } else { $i = $sep + 1; }
                    $sep = -1; $j = $i; $l = 0; $nl++;
                } else {
                    $i++;
                }
            }
            return $nl;
        }

        // Tekent één rij (header of data) met per-cel regelterugloop; alle cellen
        // in de rij krijgen dezelfde hoogte (op basis van de cel met de meeste regels).
        public function DrawRow(array $values, bool $fill = false): void
        {
            $n = count($values);
            $maxLines = 1;
            for ($i = 0; $i < $n; $i++) {
                $maxLines = max($maxLines, $this->NbLines($this->colWidths[$i], $values[$i]));
            }
            $rowH = $maxLines * $this->lineH;
            $x = $this->GetX(); $y = $this->GetY();
            for ($i = 0; $i < $n; $i++) {
                $this->Rect($x, $y, $this->colWidths[$i], $rowH, $fill ? 'DF' : 'D');
                $this->MultiCell($this->colWidths[$i], $this->lineH, $values[$i], 0, 'L');
                $x += $this->colWidths[$i];
                $this->SetXY($x, $y);
            }
            $this->SetXY($this->lMargin, $y + $rowH);
        }

        // Rekent vooraf de benodigde hoogte uit om te bepalen of er een
        // pagina-einde nodig is (dat roept Header() aan om de kop te herhalen),
        // en tekent de rij daarna pas op de (eventueel nieuwe) pagina.
        public function TableRow(array $values): void
        {
            $n = count($values);
            $maxLines = 1;
            for ($i = 0; $i < $n; $i++) {
                $maxLines = max($maxLines, $this->NbLines($this->colWidths[$i], $values[$i]));
            }
            if ($this->GetY() + $maxLines * $this->lineH > $this->PageBreakTrigger) {
                $this->AddPage($this->CurOrientation);
            }
            $this->DrawRow($values);
        }
    }

    if (!empty($exportRows)) {
        $columns = array_keys($exportRows[0]);
        $n       = count($columns);

        if     ($n <= 8)  { $fontSize = 9; $lineH = 4.3; }
        elseif ($n <= 12) { $fontSize = 8; $lineH = 3.9; }
        elseif ($n <= 16) { $fontSize = 7; $lineH = 3.5; }
        elseif ($n <= 20) { $fontSize = 6; $lineH = 3.1; }
        else              { $fontSize = 5; $lineH = 2.7; }

        $pdf = new ReportPDF('L', 'mm', 'A4');
        $pdf->AliasNbPages();
        $pdf->SetMargins(8, 10, 8);
        $pdf->SetAutoPageBreak(true, 14);
        $pdf->reportTitle  = $cp1252($title);
        $pdf->genDate      = $cp1252('Gegenereerd op ' . date('d-m-Y H:i') . ' — ' . count($exportRows) . ' rij(en)');
        $pdf->bodyFontSize = $fontSize;
        $pdf->lineH        = $lineH;

        $usableWidth   = $pdf->GetPageWidth() - 16; // marges 8+8
        $colW          = $usableWidth / $n;
        $pdf->colWidths  = array_fill(0, $n, $colW);
        $pdf->colHeaders = array_map($cp1252, $columns);

        $pdf->AddPage(); // roept Header() aan, die nu $pdf->lineH/$pdf->colWidths nodig heeft
        $pdf->SetFont('Helvetica', '', $fontSize);

        foreach ($exportRows as $row) {
            $values = [];
            foreach ($row as $v) {
                $v = ($v !== null && preg_match('/^\d{4}-\d{2}-\d{2}/', (string)$v))
                    ? date('d-m-Y', strtotime((string)$v)) : (string)($v ?? '');
                $values[] = $cp1252($v);
            }
            $pdf->TableRow($values);
        }
    } else {
        // Geen resultaten voor deze filters: toch een (leeg) PDF-bestand teruggeven
        // i.p.v. stil terug te vallen op de HTML-pagina — de gebruiker klikte op "PDF".
        $pdf = new ReportPDF('L', 'mm', 'A4');
        $pdf->AliasNbPages();
        $pdf->SetMargins(8, 10, 8);
        $pdf->SetAutoPageBreak(true, 14);
        $pdf->reportTitle  = $cp1252($title);
        $pdf->genDate      = $cp1252('Gegenereerd op ' . date('d-m-Y H:i'));
        $pdf->bodyFontSize = 9;
        $pdf->lineH        = 4.3;
        $pdf->colWidths    = [];
        $pdf->colHeaders   = [];
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(0, 8, $cp1252('Geen gegevens gevonden voor de geselecteerde filters.'));
    }

    $pdf->Output('D', $type . '_' . date('Y-m-d') . '.pdf');
    exit;
}

// Hulpdata
$userLocations = $userLocationsAll; // al geladen bovenaan
$allRooms      = getRoomsByLocation((int)$filterLocation);
$allTypes      = getAssetTypes();
$allStatuses   = getAssetStatuses();

$exportParamsBase = array_filter([
    'type' => $type,
    'location_id' => $filterLocation, 'status' => $filterStatus,
    'room' => $filterRoom, 'asset_type' => $filterType,
    'months' => $filterMonths !== 12 ? $filterMonths : '',
    'details' => $showDetails ? '1' : '',
    'cf_filter' => $filterCfField, 'cf_filter_q' => $filterCfValue,
    'extra' => $selectedExtra, 'cf' => $selectedCf,
]);
$exportParams    = http_build_query($exportParamsBase + ['export' => 'csv']);
$pdfExportParams = http_build_query($exportParamsBase + ['export' => 'pdf']);

$pageTitle = $title;
include __DIR__ . '/../../templates/header.php';
?>

<div class="page-header">
    <h1><?= htmlspecialchars($title) ?></h1>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a href="<?= BASE_URL ?>/modules/reports/generate.php?<?= $exportParams ?>" class="btn btn-secondary">📥 CSV</a>
        <a href="<?= BASE_URL ?>/modules/reports/generate.php?<?= $pdfExportParams ?>" class="btn btn-secondary">📄 PDF</a>
        <button onclick="window.print()" class="btn btn-secondary">🖨️ Afdrukken</button>
        <a href="<?= BASE_URL ?>/modules/reports/" class="btn btn-secondary">← Terug</a>
    </div>
</div>

<!-- Filter paneel -->
<div class="card" style="margin-bottom:15px;">
    <div class="card-body">
        <form method="GET" id="reportFilterForm" style="display:flex;flex-direction:column;gap:14px;">
            <input type="hidden" name="type" value="<?= $type ?>">

            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
                <?php if (count($userLocations) > 1): ?>
                <div>
                    <label style="font-size:0.8rem;font-weight:600;display:block;margin-bottom:3px;">Locatie</label>
                    <select name="location_id" class="form-control" style="width:170px;">
                        <option value="">Alle mijn locaties</option>
                        <?php foreach ($userLocations as $loc): ?>
                        <option value="<?= $loc['id'] ?>" <?= $filterLocation == $loc['id'] ? 'selected':'' ?>>
                            <?= htmlspecialchars($loc['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <input type="hidden" name="location_id" value="<?= htmlspecialchars($filterLocation) ?>">
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
                        <option value="<?= ROOM_EMPTY_MARKER ?>" <?= $filterRoom===ROOM_EMPTY_MARKER?'selected':'' ?>>
                            <?= htmlspecialchars(ROOM_EMPTY_LABEL) ?> (zonder ruimte)</option>
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

                <?php if (!empty($allCustomFields)): ?>
                <div>
                    <label style="font-size:0.8rem;font-weight:600;display:block;margin-bottom:3px;">Custom veld</label>
                    <select name="cf_filter" class="form-control" style="width:170px;">
                        <option value="">— geen filter —</option>
                        <?php foreach ($allCustomFields as $cf): ?>
                        <option value="<?= htmlspecialchars($cf['field_name']) ?>" <?= $filterCfField===$cf['field_name']?'selected':'' ?>>
                            <?= htmlspecialchars($cf['field_label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size:0.8rem;font-weight:600;display:block;margin-bottom:3px;">Bevat tekst</label>
                    <input type="text" name="cf_filter_q" class="form-control" style="width:150px;"
                           value="<?= htmlspecialchars($filterCfValue) ?>" placeholder="zoekterm...">
                </div>
                <?php endif; ?>

                <div style="display:flex;gap:6px;">
                    <button type="submit" class="btn btn-primary">Toepassen</button>
                    <a href="<?= BASE_URL ?>/modules/reports/generate.php?type=<?= $type ?>" class="btn btn-secondary">Reset</a>
                </div>
            </div>

            <?php if (in_array($type, $reportTypesWithFieldPicker, true)): ?>
            <details id="fieldPickerDetails" style="border-top:1px solid #e5e7eb;padding-top:10px;">
                <summary style="cursor:pointer;font-weight:600;font-size:0.85rem;color:#374151;">
                    ➕ Extra velden op dit rapport
                    <?php if (!empty($selectedExtra) || !empty($selectedCf)): ?>
                    <span style="font-weight:400;color:#6b7280;">(<?= count($selectedExtra) + count($selectedCf) ?> geselecteerd)</span>
                    <?php endif; ?>
                </summary>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px;margin-top:10px;">
                    <?php
                    $core = $coreFieldKeysByType[$type] ?? [];
                    $byGroup = [];
                    foreach ($standardFieldCatalog as $key => [$label, $expr, $group]) {
                        if (in_array($key, $core, true)) continue; // al standaard op dit rapport
                        $byGroup[$group][] = [$key, $label];
                    }
                    foreach ($byGroup as $group => $fields):
                    ?>
                    <div>
                        <div style="font-size:0.75rem;font-weight:700;color:#6b7280;text-transform:uppercase;margin-bottom:4px;"><?= htmlspecialchars($group) ?></div>
                        <?php foreach ($fields as [$key, $label]): ?>
                        <label style="display:flex;align-items:center;gap:6px;font-size:0.85rem;margin-bottom:4px;cursor:pointer;">
                            <input type="checkbox" name="extra[]" value="<?= htmlspecialchars($key) ?>"
                                   <?= in_array($key, $selectedExtra, true) ? 'checked' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>

                    <?php if (!empty($allCustomFields)): ?>
                    <div>
                        <div style="font-size:0.75rem;font-weight:700;color:#6b7280;text-transform:uppercase;margin-bottom:4px;">Custom velden</div>
                        <?php foreach ($allCustomFields as $cf): ?>
                        <label style="display:flex;align-items:center;gap:6px;font-size:0.85rem;margin-bottom:4px;cursor:pointer;">
                            <input type="checkbox" name="cf[]" value="<?= htmlspecialchars($cf['field_name']) ?>"
                                   <?= in_array($cf['field_name'], $selectedCf, true) ? 'checked' : '' ?>>
                            <?= htmlspecialchars($cf['field_label']) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </details>
            <?php endif; ?>
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
                            if ($type==='per_room'     && isset($row['Ruimte'])) {
                                $dp['room'] = ($row['Ruimte'] === ROOM_EMPTY_LABEL) ? ROOM_EMPTY_MARKER : $row['Ruimte'];
                            }
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

<?php if (in_array($type, $reportTypesWithFieldPicker, true)): ?>
<script>
// Onthoud de gekozen extra velden/custom velden per rapporttype in de browser (localStorage),
// zodat je ze niet elke keer opnieuw hoeft aan te vinken.
(function () {
    var reportType = <?= json_encode($type) ?>;
    var storageKey = 'assettrack_report_fields_' + reportType;
    var hasFieldParams = <?= (isset($_GET['extra']) || isset($_GET['cf'])) ? 'true' : 'false' ?>;
    var form = document.getElementById('reportFilterForm');

    if (!hasFieldParams) {
        try {
            var saved = JSON.parse(localStorage.getItem(storageKey) || 'null');
            if (saved && ((saved.extra && saved.extra.length) || (saved.cf && saved.cf.length))) {
                var params = new URLSearchParams(window.location.search);
                (saved.extra || []).forEach(function (v) { params.append('extra[]', v); });
                (saved.cf || []).forEach(function (v) { params.append('cf[]', v); });
                window.location.replace(window.location.pathname + '?' + params.toString());
                return;
            }
        } catch (e) { /* localStorage niet beschikbaar — negeren */ }
    }

    if (form) {
        form.addEventListener('submit', function () {
            try {
                var extra = Array.prototype.map.call(
                    form.querySelectorAll('input[name="extra[]"]:checked'), function (el) { return el.value; });
                var cf = Array.prototype.map.call(
                    form.querySelectorAll('input[name="cf[]"]:checked'), function (el) { return el.value; });
                localStorage.setItem(storageKey, JSON.stringify({ extra: extra, cf: cf }));
            } catch (e) { /* localStorage niet beschikbaar — negeren */ }
        });
    }
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
