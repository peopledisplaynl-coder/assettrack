# Changelog

Alle noemenswaardige wijzigingen in AssetTrack. Opmaak volgens
[Keep a Changelog](https://keepachangelog.com/nl/1.1.0/), versies volgens
[Semantic Versioning](https://semver.org/lang/nl/).

## [4.2.1] - 2026-10-24
### Beveiliging
- Deploy-script: het SFTP-wachtwoord werd in Windows PowerShell leesbaar getoond tijdens
  het intypen. Het wachtwoord wordt nu verborgen ingelezen (elke toets als `*`), en zonder
  interactieve terminal vraagt het script er niet om (gebruik dan `AT_DEPLOY_PASSWORD`).

## [4.2.0] - 2026-10-23
Fundament voor verder bouwen. De werking van de app blijft gelijk.

### Toegevoegd
- Automatische database-updates: genummerde migraties in `migrations/` worden bij het
  eerste paginabezoek na een update uitgevoerd (tabel `schema_migrations`). De losse
  `migratie_*.sql`-bestanden zijn vervangen; ze zijn veilig voor databases waarop die
  SQL al met de hand is uitgevoerd.
- Deploy-script `npm run deploy` (SFTP): uploadt alleen gewijzigde bestanden, overschrijft
  nooit `includes/config.php` of `assets/uploads/`, uploadt `install/` nooit naar een
  bestaande server en verwijdert niets. `--compare` vergelijkt elk bestand met de server,
  `--dry-run` en `--stale` om vooraf te controleren.
- `npm run package`: ZIP voor een nieuwe installatie.
- Demomodus met een verzonnen schoolstichting (3 scholen, ±220 apparaten) en een
  verzonnen import-CSV (`tools/demo/`).
- Tests: `php -l` op alle bestanden, PHP-tests (validatie, demo-gegevens, migraties) en
  Playwright end-to-end tests (inloggen, zoeken, asset toevoegen, rapporten, alle
  hoofdpagina's, automatische migratie, telefoonbreedte, nieuwe installatie).
- `CLAUDE.md`, `docs/ARCHITECTURE.md` en dit changelog.

### Gewijzigd
- Versienummer staat op één plek (`includes/version.php`) en wordt gecontroleerd tegen
  `package.json`, README en changelog.

### Beveiliging
- Installatiewizard: `?reinstall=1` kon zonder inloggen `includes/config.php` verwijderen.
  Opnieuw installeren kan nu alleen door `config.php` zelf via FTP te verwijderen, en de
  wizard schakelt zichzelf uit na een geslaagde installatie.

## [4.1.0] - 2026-10-23
### Gewijzigd
- De werkmap `D:\PeopleDisplay_ASSET_install\assettrack` is leidend gemaakt en GitHub is
  daarmee gelijkgetrokken. Op GitHub ontbraken: "30 dagen ingelogd blijven",
  MAC-adres zonder scheidingstekens, ontkoppelknop bij gekoppelde assets, eigen
  PWA-iconen en favicon, organisaties/locaties verwijderen met link naar het
  locatie-overzicht, en bulk bewerken met filter op elk veld, bulk verwijderen en zonder
  limiet van 200.
- Versienummer centraal in `includes/version.php`; werkende `.gitignore`.

### Verwijderd
- Losse hulpbestanden uit de repository (icoon-generatoren, PWA-testpagina's, oude
  migratie-SQL, oude handleiding v3.1) en `demo_import.csv` met gegevens van een echte
  school.

## [4.0] - 2026-09-11
- Rapporten met kolomkeuze, custom-veldfilter en PDF-export; bulk bewerken uitgebreid;
  Mijn profiel; Dymo 11355-labels; centrale versie. Zie de git-historie voor details.
