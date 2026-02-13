# IPWest

[![Release](https://img.shields.io/github/v/release/gentpan/ipwest?label=release)](https://github.com/gentpan/ipwest/releases)
[![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)](https://www.php.net/releases/8.5/en.php)
[![License](https://img.shields.io/github/license/gentpan/ipwest)](https://github.com/gentpan/ipwest/blob/main/LICENSE)
[![Last Commit](https://img.shields.io/github/last-commit/gentpan/ipwest)](https://github.com/gentpan/ipwest/commits/main)
[![Stars](https://img.shields.io/github/stars/gentpan/ipwest?style=social)](https://github.com/gentpan/ipwest/stargazers)

A modern IP inspection website powered by `ip.sb` API.

## Version

- Current version: **v0.1.0**
- Versioning: **SemVer** (`MAJOR.MINOR.PATCH`)

## Features

- Auto-detect visitor IP on homepage
- Dual-stack detection for both IPv4 and IPv6
- Manual query for any IPv4 / IPv6 address
- Geo data from `ip.sb` (`ASN`, organization, location, ISP, timezone)
- Localized location display (Chinese country/city)
- Flag display strategy:
  - Windows: `flagcdn` SVG
  - macOS / iOS / Android / others: emoji flag
- Cookie consent banner with persisted choice
- Process-manager style UI inspired by pm3 design language

## Tech Stack

- **PHP 8.5**
- Vanilla JavaScript
- Plain CSS (no framework)

## Project Structure

```text
.
├── index.php          # Main entry + backend API proxy logic
└── assets/
    ├── style.css      # All page styles
    └── app.js         # Cookie + auto IP detection logic
```

## Requirements

- PHP `>= 8.5`
- `curl` extension recommended (fallback available)
- Public outbound access to:
  - `https://api.ip.sb`
  - `https://api-ipv4.ip.sb`
  - `https://api-ipv6.ip.sb`
  - `https://flagcdn.com`

## Run Locally

```bash
php -S 127.0.0.1:8080
```

Open: [http://127.0.0.1:8080](http://127.0.0.1:8080)

## API Usage

This project uses `ip.sb` endpoints:

- `https://api.ip.sb/geoip/{ip}?lang=zh-CN`
- `https://api-ipv4.ip.sb/ip`
- `https://api-ipv6.ip.sb/ip`

## Deployment

You can deploy with any standard PHP runtime:

- Nginx + PHP-FPM
- Apache + mod_php / PHP-FPM
- Caddy + PHP FastCGI

Ensure `index.php` is the web root entry and outbound API access is allowed.

## Security Notes

- Input IP is validated server-side
- Geo lookup is requested server-side (no direct third-party API key exposure)
- Cookie consent is stored in both `localStorage` and cookie (`SameSite=Lax`)

## Roadmap

- [ ] Add JSON copy button
- [ ] Add multi-language UI
- [ ] Add response cache layer
- [ ] Add CI workflow (lint + basic smoke test)

## License

MIT License.
