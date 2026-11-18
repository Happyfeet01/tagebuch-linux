# Changelog

## 0.2.1

- UI-Fix: Klick auf einen Tab (Timeline/Kalender/An diesem Tag/Suche/Statistik/Einstellungen) schliesst jetzt den offenen Editor
- UI-Fix: App-Container hat eigenen Milchglas-Hintergrund ( halbtransparentes `var(--color-main-background)` + `backdrop-filter: blur`), damit Text ueber einem Hintergrundbild lesbar bleibt; Fallback ohne color-mix/backdrop-filter ist ein deckender Hintergrund

## 0.2.0

- Anhänge werden bei aktiver clientseitiger Verschlüsselung ebenfalls verschlüsselt (`<name>.enc`, AES-GCM) hochgeladen und in der App entschlüsselt angezeigt (Bild-/Video-Vorschau, Download)
- Neuer Download-Endpunkt für Anhänge: `GET /api/entry/{date}/attachment/{name}`
- „An diesem Tag“ zeigt jetzt Fotos/Videos aus einem konfigurierbaren Foto-Ordner (`photo_folder`, Struktur `<Jahr>/<Monat>/`, optional `<Tag>/`) – Endpunkte `GET /api/on-this-day/photos` und `GET /api/photo?path=...`
- Einstellungsfeld für den Foto-Ordner

## 0.1.0

- Erstversion: Nextcloud-App „Tagebuch“
- Einträge als `*.md` im Dateiordner `Tagebuch/` (Frontmatter kompatibel zu tagebuch-linux)
- Kalender, Timeline, „An diesem Tag“, Suche, Statistiken, Vorlagen
- Anhänge (Fotos/Videos/Audio/Dateien) pro Eintrag
- Tägliche Erinnerungs-Mail an die NC-Nutzer-E-Mail über die System-SMTP-Einstellungen, mit Token-Link und Reply-Adresse
- Mail-in: IMAP-Polling (Admin-App-Einstellungen), Antwort-Mails werden zu Einträgen
- PWA (Manifest + Service Worker) für iPhone („Zum Home-Bildschirm“)
