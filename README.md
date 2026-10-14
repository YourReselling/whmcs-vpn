# Your-Reselling WHMCS · VPN

Dieses Repository enthält genau **ein eigenständig installierbares Modul**. PHP **8.1+**, cURL, JSON, OpenSSL und eine damit kompatible WHMCS-Version mit MySQL/MariaDB werden benötigt. Alle Modulklassen und API-Routendaten liegen im eigenen Modulordner. Kein anderes Your-Reselling-Modul, zentrales Common-Verzeichnis, SDK oder Composer-Installationsschritt ist erforderlich.

## Installation

1. Nur `modules/servers/yourresellingvpn/` in das gleichnamige Verzeichnis der WHMCS-Installation kopieren. Alternativ das mit `python3 tools/package.py` erzeugte ZIP entpacken und dessen `modules/` in das WHMCS-Hauptverzeichnis kopieren.
2. Einen WHMCS-Server mit **Your-Reselling · VPN** anlegen; den API-Key im **Server-Passwortfeld** hinterlegen.
3. Beim Produkt dieses Modul und den Server auswählen. HTTPS-Basis-URL inklusive `/api/v1` und produktbezogene Tarif-/Standortwerte setzen. Pflichtfelder, Rechte und Einheiten stehen in [Produktkonfiguration](docs/product.md). `TestConnection` liest `/account`; dafür das entsprechende Leserecht vergeben.
4. Kundenfelder mit exakt den dort genannten API-Namen als Produkt-Custom-Fields einrichten. Ressourcen und Tarife stammen ausschließlich aus der administrativen Konfiguration. Weitere Vorgaben können als JSON eingetragen werden.
5. Create bzw. **Bereitstellung prüfen** verwenden. Solange die API den aktiven Zustand nicht bestätigt, bleibt die Bereitstellung ausstehend. Wiederholungen prüfen die bereits gespeicherte Anbieter-ID.

Das Modul ist für eigenständige WHMCS-Produktdienste mit Service Properties vorgesehen. Addons und automatisch durchgereichte, kostenpflichtige Configurable Options werden nicht unterstützt.

## Zustand und Wiederaufnahme

`YourReselling ID` und `YourReselling State` werden als administrative Service Properties gespeichert und im Bestellformular ausgeblendet. Der Zustand wird vor dem Kauf geschrieben. Parallele Prozesse werden mit MySQL-Advisory-Locks serialisiert. Ein Netzfehler nach dem Absenden blockiert weitere automatische Käufe. Nach Abgleich im Anbieterpanel kann ein Administrator die vorhandene ID in `YourReselling ID` zuordnen. Den `creating`-Marker nur zurücksetzen, wenn zweifelsfrei keine Bestellung existiert.

Einmalig gelieferte Zugangsdaten werden mit WHMCS `encrypt()` verschlüsselt gespeichert. Die zugehörige WHMCS-Verschlüsselungskonfiguration muss beim Umzug erhalten bleiben. Kunden können Zugangsdaten per CSRF-geschützter POST-Aktion anzeigen. Das funktioniert nur bei einem aktiven, zugeordneten Dienst. Übersichten und Fehlerausgaben enthalten keine Passwörter oder API-Keys.

Suspend und Kündigung stehen nur zur Verfügung, wenn die Produkt-API sie unterstützt; siehe [Produktkonfiguration](docs/product.md). Eine angenommene Kündigung kann später physisch umgesetzt werden.

## Unabhängig entwickeln, testen und veröffentlichen

Dieses Verzeichnis ist die Repository-Wurzel. Es kann separat geklont, verschoben und versioniert werden. Es gibt keine Symlinks oder Pfadabhängigkeiten zu Nachbar-Repositories. Der private Namespace `YourReselling\Whmcs\Vpn` verhindert Klassenkonflikte mit anderen Produktmodulen und deren Versionen.

```sh
php tests/run.php
python3 tools/package.py
```

Die Tests verwenden einen lokalen cURL-Testadapter und lösen keine API-Bestellungen aus. Sie benötigen keine WHMCS-Installation und keinen Composer-Vendor. Das Build-Skript verwendet nur die Python-Standardbibliothek und Dateien dieses Repositories. Es erzeugt `dist/whmcs-vpn-3.0.0.zip` sowie eine SHA-256-Prüfsumme. Die Version für eigene Releases in `module.json` ändern. Für Updates ausschließlich den eigenen Modulordner ersetzen; Anbieter-IDs und Status liegen in der WHMCS-Datenbank.

[API-Vertrag](docs/api.json) und [Produktkonfiguration](docs/product.md) sind Bestandteil dieses Repositories. Bei API-Änderungen dieses Modul separat anpassen, testen und veröffentlichen. Andere Module benötigen kein gleichzeitiges Update.

Die lokalen Tests ersetzen keine Abnahme in einer lizenzierten WHMCS-Testinstallation. Dort insbesondere Theme, Service Properties, CSRF, Berechtigungen und Modulqueue prüfen.

Referenzen: [WHMCS Provisioning](https://developers.whmcs.com/provisioning-modules/), [Registrar-Module](https://developers.whmcs.com/domain-registrars/).
