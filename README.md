# tagebuch-linux

Privates Tagebuch für Linux – ein Funktions-Nachbau von **Diarium** (iOS/macOS/Android/Windows).
Die Einträge werden als lesbare **Markdown-Dateien (`*.md`)** gespeichert und optional über
**WebDAV mit der eigenen Nextcloud synchronisiert** – genau wie bei der Diarium-Einrichtung auf dem iPhone.
Es wird kein Diarium-Code verwendet, nur das gleiche Funktionsprinzip.

## Funktionen (Diarium-Funktionsumfang)

- Tägliche Einträge mit Datum, Uhrzeit und optionaler Überschrift; mehrere Einträge pro Tag möglich
- Text als Markdown (Listen, Fett/Kursiv, Links, Überschriften)
- Anhänge: Fotos, Audio, beliebige Dateien (liegen als Dateien neben der Eintrags-Datei)
- Tags und Tracker-Tags (z. B. Stimmung, Schlaf, Sport) pro Eintrag
- Sterne-Bewertung (1–5) pro Eintrag – färbt die Kalendertage ein
- Orte (Name + Koordinaten) pro Eintrag
- Ansichten: Kalender, Timeline, „An diesem Tag" (Erinnerungen aus Vorjahren)
- Volltextsuche über alle Einträge, Filter nach Tag/Datum/Bewertung
- Statistiken: Einträge, Wörter, Schreib-Streak (aktuell/längste), Ø Bewertung, Stimmungsverlauf
- Vorlagen (Templates) zum Einfügen in neue Einträge
- Tägliche Schreib-Erinnerung (Desktop-Benachrichtigung zur Wunsch-Uhrzeit)
- Sperre mit PIN/Passwort inkl. Auto-Lock nach Inaktivität (Biometrie entfällt unter Linux)
- Sync über WebDAV (Nextcloud, Synology, …) mit App-Passwort; Konflikte landen als `*.conflict-*`-Kopie
- Export des ganzen Tagebuchs als JSON, Markdown, TXT oder HTML; Export einzelner Einträge
- Import aus JSON (eigenes Format), CSV und vorhandenen Markdown-Ordnern
- Hell/Dunkel/System-Design, Akzentfarbe und Schriftart einstellbar

## Speicherformat

Ein Eintrag = eine Datei im Tagebuch-Ordner:

```
Tagebuch/
├── 2026-11-18.md                 # Eintrag des Tages
├── 2026-11-18_20-15.md           # weiterer Eintrag am selben Tag
├── 2026-11-18-assets/            # Anhänge des Tages
│   └── foto.jpg
└── .templates/
    └── dankbarkeit.md            # Vorlagen
```

Aufbau einer Eintrags-Datei (YAML-Frontmatter + Markdown-Text):

```markdown
---
version: 1
date: 2026-11-18
time: "20:15"
created: 2026-11-18T08:12:00+01:00
modified: 2026-11-18T20:16:41+01:00
heading: Abendrückblick
rating: 4
tags: [Familie, Spaziergang]
trackers:
  Schlaf: 7
location:
  name: Zuhause
  lat: 47.3769
  lon: 8.5417
attachments:
  - 2026-11-18-assets/foto.jpg
---

Heute war ein guter Tag. **Wichtig:** Termin am Freitag nicht vergessen.

- Einkaufen
- Anruf bei Mama
```

Weil es reines Markdown ist, sind die Einträge direkt in der Nextcloud-Web-Oberfläche,
in der Nextcloud-Notes-App, in Obsidian oder jedem Editor les- und bearbeitbar.

## Installation (Debian/Ubuntu)

```bash
sudo apt install python3-gi python3-gi-cairo gir1.2-adw-1 gir1.2-gtk-4.0 gir1.2-gtksource-5
python3 -m venv --system-site-packages .venv && . .venv/bin/activate
pip install -r requirements-dev.txt   # oder nur: pip install .
tagebuch-gui                          # GUI starten
tagebuch --help                       # CLI
```

**Wichtig:** `--system-site-packages` ist nötig, damit das venv das systemweit installierte
`gi` (PyGObject aus `python3-gi`) sieht. Ohne apt-Pakete meldet `tagebuch-gui` statt eines
Tracebacks einen Installationshinweis.

Nur CLI ohne GUI: `pip install .` genügt (requests + PyYAML), kein GTK nötig.

Optional Desktop-Datei und Icon installieren:

```bash
sudo install -Dm644 data/io.github.happyfeet01.tagebuchlinux.desktop \
  /usr/share/applications/io.github.happyfeet01.tagebuchlinux.desktop
sudo install -Dm644 data/io.github.happyfeet01.tagebuchlinux.svg \
  /usr/share/icons/hicolor/scalable/apps/io.github.happyfeet01.tagebuchlinux.svg
```

Die GUI (`tagebuch-gui`) bietet: Kalender mit farbigen Tagen (Bewertung), Timeline,
„An diesem Tag“, Editor mit Markdown-Text, Sterne-Bewertung, Tags, Ort, Anhängen,
Vorlagen, Suche, PIN-Sperre mit Auto-Lock, tägliche Erinnerung als Desktop-Benachrichtigung,
Sync-Schaltfläche und Einstellungen (WebDAV, Design, Export/Import).

## Nextcloud/WebDAV einrichten

1. In der Nextcloud ein **App-Passwort** anlegen (Profil → Sicherheit → Geräte & Sitzungen).
2. In den Einstellungen der App hinterlegen:
   - Server: `https://deine-cloud.example/remote.php/dav/files/<benutzer>/Tagebuch`
   - Benutzername und App-Passwort (wird im System-Schlüsselbund abgelegt, falls verfügbar,
     sonst in der Config-Datei mit Rechten `0600`)
3. Sync auslösen (GUI-Schaltfläche oder `tagebuch sync`).

Lokaler Ordner und Remote-Ordner spiegeln sich 1:1; Anhänge werden mit synchronisiert.

### Achtung: Diariums Sync-Ordner nicht verwenden
Diariums eigener WebDAV-Sync-Ordner enthält eine **proprietäre, verschlüsselte Datenbank**
(plus `README.txt`) – **keine `*.md`-Dateien**. Lesbare Markdown-Dateien entstehen bei Diarium
nur über die **Export-Funktion** der App.

- Richte tagebuch-linux auf einen **eigenen Ordner** in der Nextcloud (z. B. `Tagebuch-linux`),
  nie auf Diariums Sync-Ordner.
- Der Sync erkennt Diarium-Ordner am README und **bricht mit Fehlermeldung ab, ohne etwas zu
  verändern**.
- Remote-Dateien werden nur gelöscht, wenn diese App sie selbst hochgeladen hat – fremde
  Dateien (z. B. Diariums Datenbank) bleiben unangetastet.
- Bestehende Diarium-Einträge übernehmen: in Diarium **Export** (JSON oder Markdown-ZIP)
  → `tagebuch import <datei>` (bei ZIP: entpacken, dann Ordner importieren).

## CLI

```bash
tagebuch init /pfad/zum/tagebuch          # lokales Tagebuch anlegen
tagebuch new "Heute war ein guter Tag" --tag Familie --rating 4
tagebuch list --days 14
tagebuch show 2026-11-18
tagebuch search "Termin"
tagebuch stats
tagebuch on-this-day
tagebuch sync                             # WebDAV-Sync (pull + push)
tagebuch export out.json --format json    # json | md | txt | html
tagebuch import backup.json               # json | csv | md-ordner
tagebuch lock set-pin                     # PIN setzen/ändern
tagebuch template add dankbarkeit.md
```

## Entwicklung

```bash
python3 -m venv .venv && . .venv/bin/activate
pip install -r requirements-dev.txt
ruff check src tests
python -m compileall -q src
pytest -q
```

Aufbau: `src/tagebuch/` – Core (Modell, Store, WebDAV, Sync, Suche, Statistiken, Austausch,
Konfig, Sperre, Erinnerungen, CLI) ist bewusst GUI-frei und per Unit-Tests prüfbar
(WebDAV-Tests gegen einen lokalen Fake-Server auf 127.0.0.1). Die GTK4/Libadwaita-GUI
liegt in `tagebuch/app.py` und `tagebuch/ui/`.

## Sicherheit

- PIN/Passwort wird als PBKDF2-Hash (SHA-256, 600.000 Runden, eigener Salt) gespeichert, nie im Klartext.
- WebDAV-Zugangsdaten liegen im System-Schlüsselbund (falls `keyring` verfügbar) oder in
  `~/.config/tagebuch-linux/config.json` mit Dateirechten `0600`.
- Die Daten verlassen das Gerät nur Richtung eigener Nextcloud (HTTPS, WebDAV).

## Lizenz

MIT – siehe [LICENSE](LICENSE).

## Nextcloud-App (nc-app/)

Unter [nc-app/](nc-app/README.md) liegt die Nextcloud-App „Tagebuch“ (PHP + Vue, PR #2):
Einträge in der NC-Datenbank (optional clientseitig AES-GCM-verschlüsselt), Anhänge als Dateien,
Erinnerungs-Mail täglich 20:00 Uhr + Mail-in (IMAP), PWA fürs iPhone.
