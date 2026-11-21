# tagebuch-nc

**Tagebuch als Nextcloud-App** – privates Journal. Die Einträge liegen in der
**app-eigenen Datenbanktabelle** `*dbprefix*tagebuch_entries` (nicht als lesbare Dateien;
Vorlagen in `*dbprefix*tagebuch_templates`). Anhänge (Fotos/Videos/Audio) liegen als Dateien
unter `Tagebuch/<datum>-assets/` in den NC-Dateien. Import/Export im Markdown-Format
(kompatibel zur Desktop-App [tagebuch-linux](../tagebuch-linux)) ist über den Codec möglich.

Funktionsumfang (Nachbau der Diarium-Features, siehe diariumapp.com):

- Tägliche Einträge (Markdown-Text, Überschrift), mehrere Einträge pro Tag möglich
- Anhänge: Fotos, Videos, Audio, beliebige Dateien (`<datum>-assets/` neben der `.md`)
- Tags, Tracker, Sterne-Bewertung (1–5), Ort pro Eintrag
- Kalender (blaue Tageszahlen bei vorhandenen Einträgen), Timeline, „An diesem Tag“ (Vorjahre + Fotos/Videos aus dem konfigurierten Foto-Ordner), Suche, Statistiken (Streak, Ø Bewertung, pro Monat)
- Vorlagen unter `Tagebuch/.templates/`
- **Tägliche Schreib-Erinnerung per E-Mail um 20:00 Uhr** an die im NC-Benutzer hinterlegte E-Mail-Adresse,
  Versand über die **SMTP-Einstellungen der Nextcloud-Systemeinstellungen** (`config.php`, `mail_*`)
- **Mail-in:** Antwort auf die Erinnerungs-Mail an `tagebuch+<token>@<domain>` wird als
  Eintrag gespeichert (Background-Job pollt per IMAP das in den App-Einstellungen
  hinterlegte Postfach; Zugangsdaten liegen nur in der App-Konfig, nie im Repo)
- **Foto-Ordner für „An diesem Tag“:** in den Einstellungen konfigurierbar (z. B. `Fotos`),
  Struktur `<Ordner>/<Jahr>/<Monat>/` (optional mit Tages-Unterordner `<Tag>/`); passende
  Fotos/Videos früherer Jahre werden in der Ansicht „An diesem Tag“ als Galerie angezeigt
- iPhone: Web-App im Safari + „Zum Home-Bildschirm“ (PWA mit Manifest + Service Worker)

Für Dateien ohne Datum im Namen liest die App im Monatsordner das Aufnahmedatum mit ExifTool (zuerst DateTimeOriginal, danach CreationDate und CreateDate). Unter Debian installiert `apt install libimage-exiftool-perl` das Programm nach `/usr/bin/exiftool`. Es muss in der PHP-Laufzeit der Nextcloud verfügbar sein, bei Docker also im App-Container. PHP benötigt `proc_open`. Dateien ohne passendes Datum bleiben ausgeblendet. Ein konfigurierter verteilter Nextcloud-Cache speichert das Ergebnis pro Dateiversion bis zu sieben Tage.

Die Rückblickgalerie erlaubt dem Browser, geladene Aufnahmen bis zu 24 Stunden privat zwischenzuspeichern. Nach neuem Abruf der Jahresübersicht erhalten geänderte Dateien eine neue URL.

## Installation

App nach `/var/www/nextcloud/apps/tagebuch` kopieren (oder als git-Checkout),
dann in Nextcloud unter Apps aktivieren. Voraussetzungen: PHP >= 8.1, `ext-imap`
für den Mail-in (optional), SMTP in den NC-Systemeinstellungen konfiguriert.

## Admin-Einstellungen (Mail-in)

Als Admin in den App-Einstellungen (oder per `occ config:app:set`):

```bash
occ config:app:set tagebuch imap_host --value="mail.example.com"
occ config:app:set tagebuch imap_port --value="993"
occ config:app:set tagebuch imap_ssl --value="1"
occ config:app:set tagebuch imap_user --value="tagebuch@example.com"
occ config:app:set tagebuch imap_password --value="..."
```

Der Versand nutzt automatisch die `mail_*`-Werte aus `config/config.php`
(über `OCP\Mail\IMailer`); die Domain für die Reply-Adresse kommt aus `mail_domain`.

## Entwicklung

```bash
composer install          # PHP-Dev-Abhängigkeiten (OCP, PHPUnit)
composer run lint         # php -l
composer run test:unit    # PHPUnit (tests/unit)
npm ci && npm run build   # Frontend (Vite) -> js/
npm run tsc               # TypeScript-Check
```

CI (GitHub Actions) führt php -l, PHPUnit und den Node-Build aus.

## Datenbankschema

- `*dbprefix*tagebuch_entries`: id, user_id, entry_date, entry_time, heading, body,
  rating, tags (JSON), trackers (JSON), location (JSON), attachments (JSON), created_at, modified_at
  – eindeutiger Index auf (user_id, entry_date, entry_time)
- `*dbprefix*tagebuch_templates`: id, user_id, name, body, updated_at

**Verschlüsselung (clientseitig):** In den App-Einstellungen kann eine AES-GCM-Verschlüsselung
mit App-Passwort aktiviert werden (PBKDF2, 100.000 Runden, Schlüssel nur im Browser). Dabei werden
Überschrift, Text, Tags, Tracker und Ort als ein JSON-Payload verschlüsselt in der DB gespeichert
(`encrypted=1`) – die Datenbank enthält dann nur Ciphertext. Datum/Uhrzeit/Bewertung bleiben als
Metadaten im Klartext (Kalender/Streaks), serverseitige Volltextsuche entfällt dafür (Suche läuft dann
clientseitig über die entschlüsselten Einträge). Bei aktiver Verschlüsselung werden Anhänge ebenfalls clientseitig verschlüsselt und als
`<name>.enc` (AES-GCM, Nonce vorangestellt) in `Tagebuch/<datum>-assets/` abgelegt; ohne
Verschlüsselung bleiben sie Klartext-Dateien. Ohne App-Passwort sind verschlüsselte Einträge
und Anhänge nicht wiederherstellbar.

## Lizenz

AGPL-3.0-or-later – siehe [LICENSE](LICENSE).
