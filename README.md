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

Ist `/root/gefStoffReg-NC` bereits geklont, statt `git clone` ein `git -C /root/gefStoffReg-NC pull --ff-only` ausführen. Für diese App sind **kein** `composer install`, `npm install` oder Build nötig; das JavaScript ist bereits im Repository.

Die Nextcloud-Gruppe `freibad-gefahrstoffe` für Mitarbeitende anlegen und sie zuordnen. Administratoren haben ebenfalls Zugriff. Anschließend als Administrator ein Testprodukt anlegen, einen Bestand buchen, ein Foto aus der Galerie und ein SDB-PDF hochladen und den Excel-Export prüfen. Bei einer anderen Nextcloud-Installation zuerst den tatsächlichen App-Pfad in `apps_paths` ermitteln.

**Wichtig:** Dies ist ein Entwicklungsstand für eine Testinstanz. PHP und eine laufende Nextcloud 35 sind in der Erstellungsumgebung nicht verfügbar; die App wurde dort noch nicht installiert oder mit Nextcloud ausgeführt. Vor dem Einsatz mit echten Gefahrstoffdaten sind PHP-Syntaxprüfung (`php -l` auf den PHP-Dateien), Aktivierung, Berechtigungen, Upload und Export an deiner Testinstanz zu prüfen. Ein signiertes App-Store-Paket ist es nicht.

## Nutzung auf dem iPhone

Nach dem Login in deine Nextcloud „Gefahrstoffe“ öffnen. In der Produktmaske gibt es **Bilder aus der Galerie** (mehrere Bilder auswählbar) und **Direkt fotografieren** (öffnet die Kamera). Ein SDB kann separat als PDF aus „Dateien“ ausgewählt werden. Die Nextcloud muss für Kameranutzung über HTTPS erreichbar sein. Ein Bild darf maximal 8 MB, ein PDF maximal 15 MB groß sein; PHPs `upload_max_filesize` und `post_max_size` müssen dazu passen. HEIC, JPEG, PNG und WebP werden anhand des tatsächlichen Dateityps geprüft.

## Funktionen und Grenzen

- Produkte, Gebindegröße, Menge je Lagerort, Zugänge/Entnahmen/Korrekturen, Buchungsprotokoll in der Datenbank, mehrere Bilder und SDB-PDF.
- Excel `.xlsx` mit „Gesamtbestand“ und „Gefahrstoffverzeichnis“ sowie CSV. Der XLSX-Export benötigt die PHP-Erweiterung `zip`.
- Keine automatische Bilderkennung, Produktdatenbank oder SDB-Suche. Eingetragene Einstufungen und SDB müssen fachlich abgeglichen werden.
- Die App verwendet **einen gemeinsamen Bestand** für Administratoren und Mitglieder der Gruppe `freibad-gefahrstoffe`. Sie besitzt noch keine gesonderten Freigaberollen oder eine UI für das Buchungsprotokoll.
- Die frühere Docker-Web-App und diese Nextcloud-App verwenden getrennte Datenbanken. Die neue App startet leer; ein Import aus der Docker-Version ist noch nicht implementiert.

Für eine Sicherung sind **Nextcloud-Datenbank und AppData** gemeinsam nötig. Der Tabellenexport ist keine vollständige Datensicherung für Fotos, PDFs und Buchungshistorie.

Quellcode der App: AGPL-3.0-or-later. Das beigefügte Gemeindewappen wurde vom Nutzer als Bildvorlage geliefert; eine Weiterverbreitung der Grafik außerhalb des Projekts bedarf eigener Prüfung.
