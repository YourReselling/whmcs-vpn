# VPN

Bestellung: `POST /products/vpn/accounts`. ID: `data.id`.

Kundenfelder: keine.

| Feld | Typ | Pflicht | Hinweis |
|---|---|---|---|
| `dns_option` | string | Nein | DNS option |

API-Key-Rechte für alle Modulansichten und Aktionen: `vpn:create`, `vpn:delete`, `vpn:read`, `vpn:update`.

Verwaltungsaktionen: DNS-Einstellungen.

Hinweise:
- Optional dns_option standard/no_ads/no_ads_no_scam
- DELETE /products/vpn/accounts/{account} also deletes with deprovisioning
- Optional suspend reason string max255
