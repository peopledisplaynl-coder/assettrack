# 📦 AssetTrack

**Gratis IT Asset Management voor scholen en organisaties**

AssetTrack is een gratis en open source webapplicatie voor het beheren van IT-apparatuur. Speciaal ontwikkeld voor scholen, non-profit organisaties en kleine bedrijven die geen dure enterprise software willen.

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.x-blue)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange)](https://mysql.com)
[![Version](https://img.shields.io/badge/versie-3.1-green)](https://github.com/peopledisplaynl-coder/assettrack/releases)

---

## ✨ Functies

- **Asset registratie** — Laptops, desktops, Chromebooks, printers en meer met alle relevante velden
- **Multi-locatie** — Meerdere locaties en ruimtes vanuit één systeem
- **Labels & QR-codes** — Avery, Dymo, Brother, Zebra of aangepast formaat
- **Rapporten** — 8 ingebouwde rapporten, exporteerbaar naar CSV
- **CSV import** — Importeer honderden assets tegelijk vanuit Excel
- **Kennisbank** — Handleidingen gekoppeld aan asset types
- **Asset koppelingen** — Desktop aan monitor, server aan UPS, etc.
- **QR Scanner** — Ingebouwde camera scanner via de browser
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
Download de [nieuwste release](https://github.com/peopledisplaynl-coder/assettrack/releases/latest/download/assettrack.zip) als ZIP bestand.

### Stap 2 — Upload
Upload en pak de bestanden uit in een map op je webhosting (bijv. `/assettrack`).

> **Strato gebruikers:** Maak de doelmap aan via de Strato File Manager, upload de ZIP en pak hem daar uit.

### Stap 3 — Database
Maak een MySQL database aan in je hostingpaneel en noteer de hostnaam, databasenaam, gebruiker en wachtwoord.

### Stap 4 — Installatie wizard
Open de browser en ga naar: `https://jouwdomein.nl/assettrack/install/`

Doorloop de 4 stappen:
1. **Database** — vul de database gegevens in
2. **Beheerder** — kies organisatienaam en superadmin account
3. **Weergave** — stel kleuren en lettertype in
4. **Gereed** — AssetTrack is live! 🎉

### Na de installatie
- Verwijder of beveilig de `/install/` map
- Bewaar de database gegevens op een veilige plek

---

## 📁 Bestandsstructuur

```
assettrack/
├── index.php                 # Loginpagina
├── dashboard.php             # Dashboard
├── select_location.php       # Locatiekeuze
├── switch_location.php       # Locatie wisselen
├── logout.php                # Uitloggen
├── manifest.json             # PWA manifest
├── assets/
│   ├── css/style.css         # Stylesheets
│   ├── js/app.js             # JavaScript
│   ├── sw.js                 # Service Worker
│   └── img/                  # PWA iconen
├── includes/
│   ├── db.php                # Database verbinding
│   ├── auth.php              # Authenticatie
│   ├── functions.php         # Hulpfuncties
│   └── config.php            # ⚠️ Niet in Git!
├── templates/
│   ├── header.php            # Header template
│   ├── footer.php            # Footer template
│   └── sidebar.php           # Sidebar
├── modules/
│   ├── assets/               # Asset beheer
│   ├── users/                # Gebruikersbeheer
│   ├── reports/              # Rapporten
│   ├── labels/               # Labels & QR
│   ├── settings/             # Instellingen
│   └── kb/                   # Kennisbank
└── install/
    ├── index.php             # Installatie wizard
    └── install.sql           # Database schema
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
