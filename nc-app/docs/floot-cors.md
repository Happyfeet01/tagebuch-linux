# Floot/iPhone-Client: CORS für Tagebuch-OCS

Die Browser-App muss direkt zur **eigenen Nextcloud** sprechen. Sie benötigt ein **separates App-Passwort**; das Passwort darf nie in Floot-Server-Endpunkte, Floot-Datenbank oder Logs gelangen.

## Aktivieren

Standardmäßig sind fremde Browser-Origins gesperrt. Trage den tatsächlichen Origin der Floot-App ein (nicht die Nextcloud-URL und nicht die `mcp-preview`-Linkadresse; bei der Sandbox typischerweise `https://<projekt-id>.sandbox.floot.app`):

```bash
sudo -u www-data php occ config:app:set tagebuch cors_allowed_origins --value="https://DEIN-PROJEKT.sandbox.floot.app"
```

Bei mehreren **vertrauenswürdigen** Origins trenne sie durch Kommas. Keine Wildcards! Nach Veröffentlichung ist die definitive Veröffentlichungsdomain ebenfalls einzutragen; Sandbox-Origins danach entfernen. Immer zuerst im Safari-Netzwerk-Inspector den tatsächlichen `Origin` prüfen.

Implementierte cross-origin Routen:
- `GET /ocs/v2.php/apps/tagebuch/api/state/vault?format=json`
- `GET /ocs/v2.php/apps/tagebuch/api/entries?format=json`
- `POST /ocs/v2.php/apps/tagebuch/api/entry?format=json`

OPTIONS-Preflights auf denselben Pfaden erlauben nur diese Methoden und vier notwendige Header. Browser nutzen `Authorization: Basic …` mit separatem Nextcloud-App-Passwort und `OCS-APIRequest: true`; **keine Cookies**. Ohne `Origin` bleibt die authentifizierte Nextcloud-Nutzung unverändert. Andere API-Routen werden nicht freigeschaltet.

Beispiel (Origin durch tatsächliche Adresse ersetzen):

```bash
curl -i -X OPTIONS 'https://files.bitsplash.de/ocs/v2.php/apps/tagebuch/api/entries?format=json' \
 -H 'Origin: https://DEIN-PROJEKT.sandbox.floot.app' \
 -H 'Access-Control-Request-Method: GET' \
 -H 'Access-Control-Request-Headers: authorization,ocs-apirequest'
```

Der Response muss `Access-Control-Allow-Origin` mit exakt diesem Origin enthalten. Danach authentifiziert `GET /entries` testen (keine privaten Inhalte in Logs posten), dann erst einen Testeintrag über POST erstellen. Preflights ohne Freigabe erhalten 403.

**Sicherheit:** CORS ist keine Authentifizierung und ersetzt nie das App-Passwort. Nur die erlaubte Browser-Origin kann Antworten lesen; ein native/CLI-Client ohne Origin bleibt weiterhin durch Nextcloud-Authentifizierung geschützt. Der verschlüsselte Tagebuch-Tresor ist ein separates Thema: Einträge dürfen ohne kompatible AES-GCM-Implementierung nicht geschrieben werden. Die Floot-Vorschau selbst ist keine geprüfte native iOS-App. Bei Reverse-Proxy-Konfiguration auf zusätzliche OPTIONS-Weiterleitungen achten.
