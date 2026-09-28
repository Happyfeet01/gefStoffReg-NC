# gefStoffReg-NC · Gefahrstoffkataster für Nextcloud 35

Diese **erste native App-Version** integriert das Inventar direkt in die Nextcloud-Navigation. Sie nutzt Nextcloud-Konten statt eines eigenen App-Passworts. Produkte, Lagerorte und Buchungen liegen in der Nextcloud-Datenbank; Fotos und PDFs in der privaten AppData-Ablage dieser Nextcloud-Instanz. Die Dokumente sind über die App für berechtigte Personen zugänglich, aber nicht automatisch im normalen Nextcloud-Dateibereich sichtbar.

## Installation auf Lars' Testinstanz (wie MediaFetch)

Die App wird im vorhandenen Nextcloud-App-Pfad `/var/www/nextcloud/apps` erkannt. **`occ app:enable` installiert sie nicht aus diesem GitHub-Repository:** Fehlt der lokale Ordner, sucht Nextcloud im App-Store und meldet „Could not download app … not found on the appstore“.

```sh
cd /root
git clone https://github.com/Happyfeet01/gefStoffReg-NC.git
install -d /var/www/nextcloud/apps/gefahrstoffkataster
rsync -a --exclude='.git/' /root/gefStoffReg-NC/ /var/www/nextcloud/apps/gefahrstoffkataster/
chown -R www-data:www-data /var/www/nextcloud/apps/gefahrstoffkataster
test -f /var/www/nextcloud/apps/gefahrstoffkataster/appinfo/info.xml
cd /var/www/nextcloud
sudo -u www-data php occ app:enable gefahrstoffkataster
```

Ist `/root/gefStoffReg-NC` bereits geklont, statt `git clone` ein `git -C /root/gefStoffReg-NC pull --ff-only` ausführen. Für diese App sind **kein** `composer install`, `npm install` oder Build nötig; das JavaScript einschließlich Scanner ist bereits im Repository.

Nach einem Update des geklonten Ordners den `rsync`- und `chown`-Befehl erneut ausführen, dann im Nextcloud-Verzeichnis `sudo -u www-data php occ upgrade` aufrufen. Anschließend die App-Seite auf dem Smartphone neu laden.

Seit 0.1.3 ist der lesende Startabruf `/api/bootstrap` von Nextclouds CSRF- und Strict-Cookie-Prüfung ausgenommen; die App prüft weiterhin die Anmeldung und Gruppenzugehörigkeit. Dies behebt HTTP 412 beim Laden der Produktübersicht. Bei anderen Ladefehlern zeigt die App den HTTP-Status und die Serverantwort dauerhaft an und schreibt einen Datenbankfehler ins Nextcloud-Log. Mit `sudo -u www-data php occ migrations:status gefahrstoffkataster` prüfen, ob die App-Migration gelaufen ist.

Die Nextcloud-Gruppe `freibad-gefahrstoffe` für Mitarbeitende anlegen und sie zuordnen. Administratoren haben ebenfalls Zugriff. Anschließend als Administrator ein Testprodukt anlegen, einen Bestand buchen, ein Foto aus der Galerie und ein SDB-PDF hochladen und den Excel-Export prüfen. Bei einer anderen Nextcloud-Installation zuerst den tatsächlichen App-Pfad in `apps_paths` ermitteln.

**Wichtig:** Dies ist ein Entwicklungsstand für eine Testinstanz. PHP und eine laufende Nextcloud 35 sind in der Erstellungsumgebung nicht verfügbar; die App wurde dort noch nicht installiert oder mit Nextcloud ausgeführt. Vor dem Einsatz mit echten Gefahrstoffdaten sind PHP-Syntaxprüfung (`php -l` auf den PHP-Dateien), Aktivierung, Berechtigungen, Upload und Export an deiner Testinstanz zu prüfen. Ein signiertes App-Store-Paket ist es nicht.

## Nutzung auf dem iPhone

Nach dem Login in deine Nextcloud „Gefahrstoffe“ öffnen. In der Produktmaske gibt es **Bilder aus der Galerie** (mehrere Bilder auswählbar) und **Direkt fotografieren** (öffnet die Kamera). Ein SDB kann separat als PDF aus „Dateien“ ausgewählt werden. Die Nextcloud muss für Kameranutzung über HTTPS erreichbar sein. Ein Bild darf maximal 8 MB, ein PDF maximal 15 MB groß sein; PHPs `upload_max_filesize` und `post_max_size` müssen dazu passen. HEIC, JPEG, PNG und WebP werden anhand des tatsächlichen Dateityps geprüft.

**EAN-Scanner:** „Scannen“ öffnet ein Fenster mit Kameravorschau und einer sichtbaren Statusmeldung. Der mitgelieferte ZXing-Decoder erkennt EAN-13, EAN-8, UPC-A und Code 128 direkt im Browser, auch wenn `BarcodeDetector` dort fehlt. Ab 0.1.4 wird kontinuierlicher Autofokus aktiviert, sofern die Kamera ihn dem Browser anbietet. „Fokus neu“ und „Blitz ein“ erscheinen nur, wenn die jeweilige Fähigkeit gemeldet wird. Auf iPhones, deren Browser diese Steuerung nicht freigibt, öffnet „Mit Kamera fotografieren“ die native Fotoaufnahme; das Bild wird anschließend lokal auf einen Barcode geprüft. Die Aufnahme wird nicht als Produktfoto gespeichert oder zum Server hochgeladen. Der Scan übernimmt nur die Nummer in das Formular und fragt keine externe Produktdatenbank ab. Bei fehlender Kameraberechtigung lässt sich die Nummer von Hand eingeben. Die Scannerdatei `js/scanner.js` liegt bereits bei; für eine Änderung an `js/scanner-source.js` ist `npm ci && npm run build:scanner` nötig. Die Lizenztexte der eingebundenen Bibliotheken liegen unter `docs/third-party`.

## Funktionen und Grenzen

- Produkte, Gebindegröße, Menge je Lagerort, Zugänge/Entnahmen/Korrekturen, Buchungsprotokoll in der Datenbank, mehrere Bilder und SDB-PDF.
- Excel `.xlsx` mit „Gesamtbestand“ und „Gefahrstoffverzeichnis“ sowie CSV. Der XLSX-Export benötigt die PHP-Erweiterung `zip`.
- Keine automatische Bilderkennung, Produktdatenbank oder SDB-Suche. Eingetragene Einstufungen und SDB müssen fachlich abgeglichen werden.
- Die App verwendet **einen gemeinsamen Bestand** für Administratoren und Mitglieder der Gruppe `freibad-gefahrstoffe`. Sie besitzt noch keine gesonderten Freigaberollen oder eine UI für das Buchungsprotokoll.
- Die frühere Docker-Web-App und diese Nextcloud-App verwenden getrennte Datenbanken. Die neue App startet leer; ein Import aus der Docker-Version ist noch nicht implementiert.

Für eine Sicherung sind **Nextcloud-Datenbank und AppData** gemeinsam nötig. Der Tabellenexport ist keine vollständige Datensicherung für Fotos, PDFs und Buchungshistorie.

Quellcode der App: AGPL-3.0-or-later. Das beigefügte Gemeindewappen wurde vom Nutzer als Bildvorlage geliefert; eine Weiterverbreitung der Grafik außerhalb des Projekts bedarf eigener Prüfung.
