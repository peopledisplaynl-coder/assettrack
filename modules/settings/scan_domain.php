<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

// Superadmin-only, net als system.php: dit is een systeeminstelling die voor de
// HELE installatie (alle locaties, alle gebruikers) geldt, niet iets wat per
// locatie of gebruiker verschilt. Op verzoek van Ton (2026-09-18): "alleen als
// instelling voor de administrator die geldt voor alle gebruikers".
if (getRole() !== 'superadmin') {
    die('Alleen superadmin heeft toegang tot deze instelling.');
}

$company   = queryOne("SELECT * FROM companies WHERE active = 1 ORDER BY id LIMIT 1");
$companyId = $company['id'] ?? 1;
$error     = '';
$success   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $raw = trim($_POST['scan_base_url'] ?? '');

    if ($raw === '') {
        // Leeg = terug naar automatisch gedrag (huidige domein van het verzoek).
        execute("UPDATE companies SET scan_base_url = NULL WHERE id = ?", [$companyId]);
        $success = 'Vast scan-domein uitgeschakeld. Nieuwe labels gebruiken weer automatisch het huidige domein.';
        $company = queryOne("SELECT * FROM companies WHERE id = ?", [$companyId]);
    } else {
        // Trailing slash(es) weghalen -- wordt hierna altijd zelf weer aan BASE_URL geplakt.
        $raw = rtrim($raw, '/');
        $parts = parse_url($raw);
        $validScheme = isset($parts['scheme']) && in_array(strtolower($parts['scheme']), ['http', 'https'], true);
        $validHost   = !empty($parts['host']);
        // Geen pad/query/fragment toestaan -- dit veld is alleen het domein
        // (protocol + host + eventueel poort), BASE_URL wordt er in print.php/
        // export_pdf.php altijd zelf achteraan geplakt.
        $hasExtra = isset($parts['path']) && $parts['path'] !== '' || isset($parts['query']) || isset($parts['fragment']);

        if (!$validScheme || !$validHost || $hasExtra) {
            $error = 'Ongeldige invoer. Gebruik alleen het domein met http(s), bijvoorbeeld https://assettrack.mijnbedrijf.nl (zonder pad erachter).';
        } else {
            execute("UPDATE companies SET scan_base_url = ? WHERE id = ?", [$raw, $companyId]);
            $success = 'Vast scan-domein opgeslagen. Nieuwe labels gebruiken dit domein in de QR-code/streepjescode.';
            $company = queryOne("SELECT * FROM companies WHERE id = ?", [$companyId]);
        }
    }
}

$currentAutoDomain = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');

$pageTitle = 'Scan-domein (QR/streepjescode)';
include __DIR__ . '/../../templates/header.php';
?>
<div class="page-header">
    <h1>🔗 Scan-domein voor labels</h1>
    <a href="<?= BASE_URL ?>/modules/settings/" class="btn btn-secondary">← Terug</a>
</div>

<?php if ($success): ?><div class="alert alert-success">✓ <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
    <div class="card">
        <div class="card-body">
            <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">
                Vast domein instellen
            </h3>
            <p style="color:#6b7280;font-size:0.875rem;">
                Elke QR-code en streepjescode op een geprint label verwijst naar dit domein.
                Verhuist AssetTrack later naar een ander domein of een andere domeinnaam, dan blijven
                al geprinte labels naar het <strong>oude</strong> domein verwijzen — die kunnen niet
                achteraf worden aangepast. Dit veld bepaalt alleen welk domein er in
                <strong>nieuw</strong> af te drukken labels komt te staan.
            </p>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <div class="form-group">
                    <label>Vast domein (leeg = automatisch huidige domein gebruiken)</label>
                    <input type="text" name="scan_base_url" class="form-control"
                           value="<?= htmlspecialchars($company['scan_base_url'] ?? '') ?>"
                           placeholder="https://assettrack.mijnbedrijf.nl">
                    <small style="color:#6b7280;">
                        Alleen het domein, geen pad erachter. Huidig automatisch domein op basis van
                        dit verzoek: <code><?= htmlspecialchars($currentAutoDomain) ?></code>
                    </small>
                </div>
                <button type="submit" class="btn btn-primary">💾 Opslaan</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h3 style="margin-top:0;color:#1a2332;border-bottom:1px solid #e5e7eb;padding-bottom:10px;">
                ℹ️ Hoe dit werkt bij een domeinverhuizing
            </h3>
            <ul style="color:#374151;font-size:0.875rem;line-height:1.6;padding-left:20px;">
                <li>
                    <strong>Scannen met de ingebouwde AssetTrack-scanner (📷 Scanner):</strong>
                    werkt altijd, ook op oude, al geprinte labels — die haalt alleen het
                    assetnummer uit de code en negeert daarbij zelf welk domein er in de code staat.
                </li>
                <li>
                    <strong>Scannen met een losse/algemene camera- of QR-app:</strong>
                    die opent altijd letterlijk het domein dat in de code staat. Bestaat dat domein
                    niet meer (bijv. na een naamswijziging zonder dat het oude domein nog
                    geregistreerd staat), dan kan geen enkele instelling in AssetTrack dat verzoek
                    nog onderscheppen — de aanvraag bereikt de server dan simpelweg niet. Zet in dat
                    geval het oude domein (tijdelijk) door naar het nieuwe, of blijf de ingebouwde
                    Scanner gebruiken voor al bestaande labels.
                </li>
                <li>
                    Print daarom bij een geplande domeinverhuizing bij voorkeur <strong>nieuwe</strong>
                    labels pas <em>nadat</em> dit veld is bijgewerkt naar het nieuwe domein.
                </li>
            </ul>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
