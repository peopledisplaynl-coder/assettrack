# 📦 AssetTrack

**Gratis IT Asset Management voor scholen en organisaties**

AssetTrack is een gratis en open source webapplicatie voor het beheren van IT-apparatuur. Speciaal ontwikkeld voor scholen, non-profit organisaties en kleine bedrijven die geen dure enterprise software willen.

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.x-blue)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange)](https://mysql.com)
[![Version](https://img.shields.io/badge/versie-4.2.0-green)](https://github.com/peopledisplaynl-coder/assettrack/releases)

---

## ✨ Functies

- **Asset registratie** — Laptops, desktops, Chromebooks, printers en meer met alle relevante velden
- **Multi-locatie** — Meerdere locaties en ruimtes vanuit één systeem
- **Labels & QR-codes** — Labels — QR-code of streepjescode** — Avery, Dymo, Brother, Zebra of aangepast formaat
- **Rapporten** — 8 ingebouwde rapporten, eigen kolomkeuze, export naar CSV en PDF
- **CSV import** — Importeer honderden assets tegelijk vanuit Excel
- **Kennisbank** — Handleidingen gekoppeld aan asset types
- **Asset koppelingen** — Desktop aan monitor, server aan UPS, etc.
- **Scanner** — Ingebouwde camera scanner via de browser, leest zowel QR-codes als streepjescodes (Code128)
- **PWA** — Installeer als app op mobiel
- **Thema instellingen** — Eigen kleuren, logo en lettertype per installatie

---

## 📋 Systeemvereisten

| Vereiste | Details |
|----------|---------|
| Webserver | PHP 8.x |
| Database | MySQL 5.7+ of MariaDB 10.x+ |
| Webhosting | Strato shared hosting ondersteund |
| Browser | Chrome, Firefox, Edge, Safari |

---

## 🚀 Installatie

### Stap 1 — Download
Download de [nieuwste release](https://github.com/peopledisplaynl-coder/assettrack/releases/latest) (`assettrack-X.Y.Z.zip`).
Zelf maken kan ook: `npm run package` maakt `dist/assettrack-X.Y.Z.zip`.

### Stap 2 — Upload
Upload en pak de bestanden uit in een map op je webhosting (bijv. `/assettrack`).

> **Strato gebruikers:** Maak de doelmap aan via de Strato File Manager, upload de ZIP en pak hem daar uit.

### Stap 3 — Database
Maak een MySQL/MariaDB-database aan in je hostingpaneel en noteer de hostnaam, databasenaam, gebruiker en wachtwoord.

### Stap 4 — Installatie wizard
Open de browser en ga naar: `https://jouwdomein.nl/assettrack/install/`

Doorloop de 4 stappen:
1. **Database** — vul de database gegevens in
2. **Beheerder** — kies organisatienaam en superadmin account
3. **Weergave** — stel kleuren en lettertype in
4. **Gereed** — AssetTrack is live! 🎉

Na afloop schakelt de installatiewizard zichzelf uit. Verwijder voor de zekerheid de map `/install/` als die nog bestaat.

---

## 🔄 Bijwerken

Database-updates gaan automatisch: na het uploaden van een nieuwe versie past de eerste paginaweergave de database aan (tabel `schema_migrations`). Je hoeft nooit zelf SQL uit te voeren.

Met de ontwikkelomgeving (Node.js 18+):

```bash
npm install                       # eenmalig
cp deploy.config.example.json deploy.config.json   # eenmalig, gegevens invullen
npm run deploy -- productie --compare --stale  # vergelijkt met de server, uploadt niets
npm run deploy -- test --dry-run  # laat zien wat er geüpload wordt
npm run deploy -- productie       # uploadt alleen gewijzigde bestanden via SFTP
```

`includes/config.php`, `assets/uploads/` en `install/` worden door het deploy-script nooit geüpload of overschreven, en er wordt nooit iets op de server verwijderd.

---

## 🧪 Ontwikkelen

| Commando | Wat |
|---|---|
| `npm run demo` | Lokale demo met verzonnen schoolgegevens (MariaDB + PHP, Linux/macOS/WSL), inloggen met `demo` / `demo1234` |
| `npm test` | Lint (`php -l`), versiecontrole en PHP-tests |
| `npm run test:e2e` | End-to-end tests met Playwright tegen de demo |
| `npm run package` | ZIP voor een nieuwe installatie |
| `npm run deploy` | Bijwerken van een server via SFTP |

Zie `CLAUDE.md` en `docs/ARCHITECTURE.md` voor de opbouw en afspraken.

---

## 📁 Bestandsstructuur

```
assettrack/
├── index.php, dashboard.php, select_location.php, switch_location.php, logout.php
├── manifest.json, favicon.ico   # PWA
├── assets/                      # css, js, sw.js, iconen, uploads/ (niet in Git)
├── includes/
│   ├── version.php              # Versienummer (één plek)
│   ├── db.php                   # Database + automatische migraties
│   ├── migrations.php           # Migratie-runner
│   ├── auth.php, functions.php
│   └── config.php               # ⚠️ Per server, niet in Git
├── migrations/                  # Genummerde database-updates
├── modules/                     # assets, users, reports, labels, settings, kb
├── templates/                   # header, footer, sidebar
├── install/                     # Installatiewizard (alleen voor nieuwe installaties)
├── docs/                        # Architectuur en handleidingen
├── tests/                       # PHP-tests en Playwright e2e
└── tools/                       # deploy, demo, lint, package
```

---

## 🔐 Rollen en rechten

| Rol | Beschrijving |
|-----|-------------|
| Superadmin | Volledige toegang tot alle functies |
| Admin | Beheerderstoegang inclusief gebruikersbeheer |
| Gebruiker | Standaard medewerker met bewerkrechten |
| Bezoeker | Alleen leesrechten |

---

## 📸 Screenshots

*Screenshots volgen binnenkort*

---

## 🤝 Bijdragen

Bijdragen zijn welkom! Open een [Issue](https://github.com/peopledisplaynl-coder/assettrack/issues) voor bugs of feature verzoeken.

---

## ☕ Doneren

AssetTrack is gratis en open source. Een kleine donatie helpt het project alive te houden!

[![Ko-Fi](https://img.shields.io/badge/Doneer-Ko--Fi-ff5f5f?logo=ko-fi)](https://ko-fi.com/peopledisplay)

---

## 📄 Licentie

MIT License — zie [LICENSE](LICENSE) voor details.

---

*Gemaakt door [Peopledisplay](https://peopledisplay.nl)*
