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

**EAN-Scanner:** „Scannen“ öffnet ein Fenster mit Kameravorschau und einer sichtbaren Statusmeldung. Der mitgelieferte ZXing-Decoder erkennt EAN-13, EAN-8, UPC-A und Code 128 direkt im Browser, auch wenn `BarcodeDetector` dort fehlt. Ab 0.1.4 wird kontinuierlicher Autofokus aktiviert, sofern die Kamera ihn dem Browser anbietet. „Fokus neu“ und „Blitz ein“ erscheinen nur, wenn die jeweilige Fähigkeit gemeldet wird. Auf iPhones, deren Browser diese Steuerung nicht freigibt, öffnet „Mit Kamera fotografieren“ die native Fotoaufnahme; das Bild wird anschließend lokal auf einen Barcode geprüft. Die Aufnahme wird nicht als Produktfoto gespeichert. Nach dem Scan sucht die App zuerst im eigenen Bestand und dann über die Nextcloud-Serververbindung in Open Products Facts. Dafür wird die EAN an diesen Dienst übermittelt. Ein vorhandenes Produkt lässt sich öffnen; externe Treffer zeigen Name, Marke und Quellenlink. Die Übernahme ins Formular erfolgt erst nach einem Tipp auf „Vorschlag übernehmen“. Die frei gepflegte Produktdatenbank kann lückenhafte oder falsche Angaben enthalten. Gefahrstoffdaten werden nicht automatisch übernommen. Der Scanner-Build `js/scanner.js` liegt bereits bei.

Ab 0.1.6 gilt auch ein HTTP-404 der externen Datenbank als „kein Treffer“. Da Open Products Facts nur einen kleinen Teil der am Markt verfügbaren Produkte enthält, gibt es dann einen Link zur Websuche mit der exakten EAN. Die Websuche startet erst nach Antippen und ist ebenfalls nur ein Recherchehinweis, keine geprüfte Produktquelle.

Ab 0.1.7 lässt sich aus der Produktmaske eine Websuche nach Produktname, Artikelnummer und Hersteller öffnen. Wird eine passende Herstellerseite gefunden, ihren Link in „Link zur Herstellerseite“ einfügen und „Herstellerseite einlesen“ wählen. Derzeit werden aus öffentlichen Seiten von `witty.eu` und `flamingo-group.de` Vorschläge für Name und Hersteller gelesen. Bei Witty werden außerdem Artikelnummer, Gebindegröße und der Link zum dortigen Sicherheitsdatenblatt vorgeschlagen, falls vorhanden. Erst „Stammdaten übernehmen“ schreibt den Vorschlag in das Formular; beim anschließenden Speichern bleiben Herstellerseite und SDB-Link im Produktdatensatz und im Excel-Export erhalten. Die Herstellerseite wird nur bei diesem ausdrücklichen Aufruf abgerufen. Eine allgemeine automatische Websuch-API ist noch nicht angebunden. Das verlinkte SDB ist kein Ersatz für den geprüften PDF-Upload der passenden Version. GHS, H-Sätze und Schutzmaßnahmen werden nie aus der Produktseite übernommen.

Ab 0.1.8 erscheint die Rückmeldung zum Einlesen unmittelbar unter dem Button in der Herstellersektion. In 0.1.7 wurde sie weiter oben beim EAN-Feld angezeigt und konnte beim Scrollen unbemerkt bleiben.

Ab 0.1.9 können das SDB und das Produktmerkblatt als getrennte Links direkt beim Hersteller hinterlegt werden. Bei Witty schlägt das Einlesen der Produktseite beide Dokumentlinks vor, sofern sie dort verlinkt sind. In der Übersicht zählt ein gespeicherter SDB-Link als vorhandener Nachweis und wird als „SDB als Herstellerlink“ gekennzeichnet. Im Excel-Export stehen Hersteller-SDB-Link, Produktmerkblatt-Link und ein optional hochgeladenes PDF in getrennten Spalten. Ein Herstellerlink kann sich später ändern; für einen belegbaren Jahresstand empfiehlt sich weiterhin zusätzlich die geprüfte PDF-Version mit Datum.

**Etikettfoto:** „Etikett fotografieren“ sendet das Bild nur an den eigenen Nextcloud-Server und liest dort Zeilen mit Tesseract. Dazu müssen auf dem Server `tesseract-ocr`, `tesseract-ocr-deu` und `tesseract-ocr-eng` installiert und `proc_open` in PHP verfügbar sein. Auf Debian: `apt install tesseract-ocr tesseract-ocr-deu tesseract-ocr-eng`. Seit 0.1.10 vergleicht die App erkannte Namen, Artikelnummern und Barcodes mit den bereits gespeicherten Produkten. Einen passenden Treffer kann man direkt zur Bestandsbuchung öffnen und dort die Gebindeanzahl eintragen. Bei neuen Produkten werden ein erkennbarer Produktname und bekannte Hersteller (Witty, Flamingo, AquaTec) ins Formular vorausgefüllt; eine Gebindegröße wird nur aus einer als Inhalt oder Füllmenge gekennzeichneten oder einzeln stehenden Angabe übernommen. Eine erkennbare Marke „ja!“ ergänzt den Produktnamen, wird aber nicht als Hersteller eingetragen. Treffer und Stammdaten am Gebinde prüfen, bevor gespeichert oder gebucht wird. Erkannten Text kann man weiterhin aufklappen und manuell auswählen. „Produktnamen suchen“ sucht auf ausdrücklichen Aufruf in Open Products Facts. Das Etikettfoto wird durch diese Funktion nicht dauerhaft gespeichert; dafür den separaten Foto-Upload verwenden. HEIC hängt vom Tesseract-Build des Servers ab; bei Problemen JPEG aufnehmen. Gefahrstoffangaben und ein optional hochgeladenes SDB-PDF bleiben manuell zu prüfen.

Seit 0.1.11 wird ein JPEG/PNG/WebP-Etikettfoto vor der Texterkennung im Browser auf den mittleren Etikettbereich zugeschnitten, vergrößert und kontrastreicher gemacht. Wenn die Bildverarbeitung fehlschlägt, wird das Original gesendet. Ein deutlich lesbares „Geschirrspülmittel Zitrone“ und eine einzeln stehende Füllmenge wie „1 L“ können so als Stammdaten vorgeschlagen werden. Die Erkennung ist weiterhin OCR, keine Google-Lens-Bildsuche: Sie kann Logos und Schriften falsch lesen; den Treffer und die genaue Variante immer vor dem Speichern prüfen. Die Ergebnisbox nutzt auf schmalen Displays die volle Formularbreite.

## Optionale KI-Websuche ab 0.1.12

Nach Eingabe eines Produktnamens startet **„Mit KI im Web suchen“** eine Suche über die OpenAI Responses API mit Websuche. Bis zu drei Varianten werden mit anklickbarer Fundstelle und separat zu prüfender Artikelnummer/Gebindegröße angeboten. Erst **„Diese Variante übernehmen“** schreibt Stammdaten ins Formular. **„SDB mit KI suchen“** sucht gesondert nach einem offiziellen Sicherheitsdatenblatt und übernimmt den gefundenen Link erst nach Öffnen und Bestätigung. Das PDF lässt sich unabhängig davon weiter manuell hochladen. Ein erstplatziertes Suchergebnis gilt nicht automatisch als richtiges SDB. UFI, GHS, H-Sätze und Einstufung werden nie aus dieser Suche übernommen.

Die Suche ist standardmäßig deaktiviert. Sie benötigt einen eigenen OpenAI-API-Schlüssel mit separater API-Abrechnung; ein ChatGPT-Abo stellt keinen API-Schlüssel bereit. Nur der eingegebene Produktname und ein optionaler Herstellerhinweis werden bei einem Klick an die API gesendet. Der Schlüssel bleibt serverseitig und darf nicht ins Git-Repository oder Browser-JavaScript. Auf einem Debian-Server als root einrichten:

```sh
install -d -o root -g www-data -m 750 /etc/nextcloud
umask 077
read -rsp 'OpenAI-API-Schlüssel: ' GSK_KEY; echo
printf '%s' "$GSK_KEY" > /etc/nextcloud/gefahrstoffkataster-openai.key
unset GSK_KEY
chown root:www-data /etc/nextcloud/gefahrstoffkataster-openai.key
chmod 640 /etc/nextcloud/gefahrstoffkataster-openai.key
```

Alternativ kann PHP-FPM die Umgebungsvariable `GSK_OPENAI_API_KEY` erhalten. Für die Dateivariante muss `www-data` die Schlüsseldatei lesen können. Die App verwendet `gpt-5.4-mini`, erzwingt einen Websuchaufruf, überträgt keine Fotos oder SDB-PDFs an OpenAI und setzt `store: false`. OpenAI berechnet Modellnutzung und Websuche nach den gültigen API-Preisen. Bei fehlendem Schlüssel zeigt die App eine verständliche Meldung und bietet weiterhin den manuellen Weblink. Nach Einrichtung zunächst mit einem klar benannten Produkt auf der Testinstanz prüfen; in dieser Entwicklungsumgebung stand kein API-Schlüssel und keine laufende Nextcloud zur Verfügung.

## Funktionen und Grenzen

0.1.14 korrigiert die Schreibzugriffe auf die Datenbank: Einfügen und Produktänderungen laufen über Nextclouds öffentlichen QueryBuilder. Damit funktionieren auch die Initialisierung und das Anlegen von Lagerorten auf Nextcloud 35. Bereits vorhandene Lagerorte werden beim erneuten Eingeben erkannt; technische Fehler werden getrennt gemeldet und im Nextcloud-Protokoll dokumentiert.

Seit 0.1.13 nutzt die Desktopübersicht bis zu 1680 Pixel Breite mit größerer Schrift. Die Produktmaske öffnet ab 900 Pixel Bildschirmbreite nahezu bildschirmfüllend, mit zwei Spalten für Formularabschnitte und einer dauerhaft sichtbaren Speicherleiste. Auf dem Smartphone bleibt die kompakte Formularansicht erhalten.

- Produkte, Gebindegröße, Menge je Lagerort, Zugänge/Entnahmen/Korrekturen, Buchungsprotokoll in der Datenbank, mehrere Bilder und SDB-PDF.
- Excel `.xlsx` mit „Gesamtbestand“ und „Gefahrstoffverzeichnis“ sowie CSV. Der XLSX-Export benötigt die PHP-Erweiterung `zip`.
- EAN-Abfrage mit Open Products Facts, optionale Etikett-Texterkennung mit lokalem Tesseract, bestätigbare Stammdaten von Witty-/Flamingo-Herstellerseiten sowie optional eine KI-Websuche mit separatem API-Schlüssel. Eingetragene Einstufungen und SDB müssen fachlich abgeglichen werden.
- Die App verwendet **einen gemeinsamen Bestand** für Administratoren und Mitglieder der Gruppe `freibad-gefahrstoffe`. Sie besitzt noch keine gesonderten Freigaberollen oder eine UI für das Buchungsprotokoll.
- Die frühere Docker-Web-App und diese Nextcloud-App verwenden getrennte Datenbanken. Die neue App startet leer; ein Import aus der Docker-Version ist noch nicht implementiert.

Für eine Sicherung sind **Nextcloud-Datenbank und AppData** gemeinsam nötig. Der Tabellenexport ist keine vollständige Datensicherung für Fotos, PDFs und Buchungshistorie.

Quellcode der App: AGPL-3.0-or-later. Das beigefügte Gemeindewappen wurde vom Nutzer als Bildvorlage geliefert; eine Weiterverbreitung der Grafik außerhalb des Projekts bedarf eigener Prüfung.
