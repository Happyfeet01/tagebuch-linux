# Signiertes Release für Tagebuch

Die Nextcloud-App-ID lautet `tagebuch`. Hinterlege in **Happyfeet01/tagebuch-linux → Settings → Secrets and variables → Actions** die beiden Repository-Secrets:

- `APP_PRIVATE_KEY` enthält den privaten PEM-Schlüssel von tagebuch.
- `APP_PUBLIC_CRT` enthält das von Nextcloud für tagebuch ausgestellte Zertifikat.

Die App wird aus `nc-app/` gebaut. Erhöhe die Version in `nc-app/appinfo/info.xml` und `nc-app/package.json` und lege `nc-app/docs/releases/<Version>.md` an. Pull Requests testen den Build ohne Signierschlüssel.

Auf `main` wird das Paket automatisch gebaut. Zur Veröffentlichung auf GitHub starte unter **Actions → Build and sign Tagebuch release → Run workflow** den Workflow für `main`. Der Release-Job prüft die App-Signatur mit `occ` und die separate SHA-512-Archivsignatur mit OpenSSL, bevor das GitHub-Release öffentlich wird. Für den Nextcloud App Store reichst du anschließend die Download-URL des Archivs und den Inhalt der zugehörigen `.signature` ein.
