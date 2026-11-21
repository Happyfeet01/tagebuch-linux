# Changelog

## 0.3.4

- Diarium-Sicherungen (.diary) lassen sich direkt im Browser einlesen und über die vorhandene Importvorschau übernehmen.

## 0.3.3

- Lange Ansichten wie die Einstellungen lassen sich innerhalb der Nextcloud-Oberfläche vertikal scrollen.

## 0.3.2

- Das Template lädt die gebauten JavaScript- und CSS-Dateien direkt. Der Produktionsbuild prüft, dass der Eintrag im Template zur App-Version passt.

## 0.3.1

- JavaScript und CSS tragen die App-Version im Dateinamen. Der Service Worker räumt beim Aktualisieren die bisherigen Offline-Caches des Tagebuchs auf.

## 0.2.7

- Der Editor nutzt die OCS-Routen der App. Eine Nextcloud-Migration legt fehlende Tagebuchtabellen an und ergänzt bei älteren Eintragstabellen das Verschlüsselungskennzeichen.
- Einträge behalten beim Bearbeiten ihre ID, Uhrzeit und Tracker. Änderungen an bestehenden Feldern, einschließlich Entfernen eines Orts, werden in der Datenbank gespeichert.
- Im Editor lässt sich ein Erinnerungsort eingeben oder der aktuelle Standort nach Browserfreigabe übernehmen. Gesperrte Einträge sind vor Überschreiben geschützt.

## 0.2.4

- Fix: `OC\BackgroundJob\JobList::has()/add()` verlangen in NC 35 zwingend 2 Argumente („Too few arguments … exactly 2 expected“, App konnte nicht booten). `Application::boot()` übergibt jetzt explizit `null` als zweites Argument; IJobList-Stub entsprechend strenger, ApplicationTest assertet die 2-Argumnent-Aufrufe

## 0.2.3

- Fix PWA/Service-Worker: bisher cache-first mit festem Cache `tagebuch-v1` – Updates kamen auf dem iPhone nie an. Jetzt network-first (`fetch` zuerst, Cache nur als Offline-Fallback), beim Activate werden alle alten Caches gelöscht, Cache-Name `tagebuch-v2`; `sw.js` und `manifest.webmanifest` mit `Cache-Control: no-cache`
- SW-JS ausgelagert nach `PwaScript::js()` + neuer `PwaScriptTest` (network-first-Reihenfolge, Cache-Purge, Scope)

## 0.2.2

- Fix: `Application::register()` rief `IRegistrationContext::registerBackgroundJob()` auf – auf NC 35 stellt der Coordinator dort ein anonymes Context-Objekt ohne diese Methode bereit („Call to undefined method …@anonymous::registerBackgroundJob()“ im Log). Jetzt per `method_exists()` guarded; die Jobs werden zusätzlich in `boot()` über `OCP\BackgroundJob\IJobList::add()` registriert (funktioniert auf NC 31–35, dedupliziert via `has()`)
- Neuer `ApplicationTest` (3 Fälle: register() ohne registerBackgroundJob, boot() registriert beide Jobs, boot() überspringt vorhandene)

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
