# Signiertes Release für Gefahrstoffkataster

Die Nextcloud-App-ID lautet `gefahrstoffkataster`. Hinterlege in **Happyfeet01/gefStoffReg-NC → Settings → Secrets and variables → Actions** die beiden Repository-Secrets:

- `APP_PRIVATE_KEY` enthält den privaten PEM-Schlüssel von gefahrstoffkataster.
- `APP_PUBLIC_CRT` enthält das von Nextcloud für gefahrstoffkataster ausgestellte Zertifikat.

Erhöhe die Version in `appinfo/info.xml` und lege `docs/releases/<Version>.md` an. Pull Requests testen den Build ohne Signierschlüssel.

Auf `main` wird das Paket automatisch gebaut. Zur Veröffentlichung auf GitHub starte unter **Actions → Build and sign Gefahrstoffkataster release → Run workflow** den Workflow für `main`. Der Release-Job prüft die App-Signatur mit `occ` und die separate SHA-512-Archivsignatur mit OpenSSL, bevor das GitHub-Release öffentlich wird. Für den Nextcloud App Store reichst du anschließend die Download-URL des Archivs und den Inhalt der zugehörigen `.signature` ein.
