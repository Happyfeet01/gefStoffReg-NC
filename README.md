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

**Wichtig:** Dies ist ein Entwicklungsstand für eine Testinstanz. Lokale PHP-, JavaScript- und Browserprüfungen ersetzen keine laufende Nextcloud 35: Aktivierung, Berechtigungen, Upload und Export sind zusätzlich an deiner Testinstanz zu prüfen. Ein signiertes App-Store-Paket ist es nicht.

## Nutzung auf dem iPhone

Nach dem Login in deine Nextcloud „Gefahrstoffe“ öffnen. In der Produktmaske gibt es **Bilder aus der Galerie** (mehrere Bilder auswählbar) und **Direkt fotografieren** (öffnet die Kamera). Ein SDB kann separat als PDF aus „Dateien“ ausgewählt werden. Die Nextcloud muss für Kameranutzung über HTTPS erreichbar sein. Ein Bild darf maximal 8 MB, ein PDF maximal 15 MB groß sein; PHPs `upload_max_filesize` und `post_max_size` müssen dazu passen. HEIC, JPEG, PNG und WebP werden anhand des tatsächlichen Dateityps geprüft.

**EAN-Scanner:** „Scannen“ öffnet ein Fenster mit Kameravorschau und einer sichtbaren Statusmeldung. Der mitgelieferte ZXing-Decoder erkennt EAN-13, EAN-8, UPC-A und Code 128 direkt im Browser, auch wenn `BarcodeDetector` dort fehlt. Ab 0.1.4 wird kontinuierlicher Autofokus aktiviert, sofern die Kamera ihn dem Browser anbietet. „Fokus neu“ und „Blitz ein“ erscheinen nur, wenn die jeweilige Fähigkeit gemeldet wird. Auf iPhones, deren Browser diese Steuerung nicht freigibt, öffnet „Mit Kamera fotografieren“ die native Fotoaufnahme; das Bild wird anschließend lokal auf einen Barcode geprüft. Die Aufnahme wird nicht als Produktfoto gespeichert. Nach dem Scan sucht die App ausschließlich im eigenen Bestand. Ein vorhandenes Produkt lässt sich direkt öffnen. Bei einem unbekannten Code kann eine Websuche mit der exakten EAN ausdrücklich geöffnet werden; Gefahrstoffdaten werden nicht automatisch übernommen. Der Scanner-Build `js/scanner.js` liegt bereits bei.

Ab 0.1.7 lässt sich aus der Produktmaske eine Websuche nach Produktname, Artikelnummer und Hersteller öffnen. Wird eine passende Herstellerseite gefunden, ihren Link in „Link zur Herstellerseite“ einfügen und „Herstellerseite einlesen“ wählen. Derzeit werden aus öffentlichen Seiten von `witty.eu` und `flamingo-group.de` Vorschläge für Name und Hersteller gelesen. Bei Witty werden außerdem Artikelnummer, Gebindegröße und der Link zum dortigen Sicherheitsdatenblatt vorgeschlagen, falls vorhanden. Erst „Stammdaten übernehmen“ schreibt den Vorschlag in das Formular; beim anschließenden Speichern bleiben Herstellerseite und SDB-Link im Produktdatensatz und im Excel-Export erhalten. Die Herstellerseite wird nur bei diesem ausdrücklichen Aufruf abgerufen. Eine allgemeine automatische Websuch-API ist noch nicht angebunden. Das verlinkte SDB ist kein Ersatz für den geprüften PDF-Upload der passenden Version. GHS, H-Sätze und Schutzmaßnahmen werden nie aus der Produktseite übernommen.

Ab 0.1.8 erscheint die Rückmeldung zum Einlesen unmittelbar unter dem Button in der Herstellersektion. In 0.1.7 wurde sie weiter oben beim EAN-Feld angezeigt und konnte beim Scrollen unbemerkt bleiben.

Ab 0.1.9 können das SDB und das Produktmerkblatt als getrennte Links direkt beim Hersteller hinterlegt werden. Bei Witty schlägt das Einlesen der Produktseite beide Dokumentlinks vor, sofern sie dort verlinkt sind. In der Übersicht zählt ein gespeicherter SDB-Link als vorhandener Nachweis und wird als „SDB als Herstellerlink“ gekennzeichnet. Im Excel-Export stehen Hersteller-SDB-Link, Produktmerkblatt-Link und ein optional hochgeladenes PDF in getrennten Spalten. Ein Herstellerlink kann sich später ändern; für einen belegbaren Jahresstand empfiehlt sich weiterhin zusätzlich die geprüfte PDF-Version mit Datum.

**Etikettfoto:** „Etikett fotografieren“ sendet das Bild nur an den eigenen Nextcloud-Server und liest dort Zeilen mit Tesseract. Dazu müssen auf dem Server `tesseract-ocr`, `tesseract-ocr-deu` und `tesseract-ocr-eng` installiert und `proc_open` in PHP verfügbar sein. Auf Debian: `apt install tesseract-ocr tesseract-ocr-deu tesseract-ocr-eng`. Seit 0.1.10 vergleicht die App erkannte Namen, Artikelnummern und Barcodes mit den bereits gespeicherten Produkten. Einen passenden Treffer kann man direkt zur Bestandsbuchung öffnen und dort die Gebindeanzahl eintragen. Bei neuen Produkten werden ein erkennbarer Produktname und bekannte Hersteller (Witty, Flamingo, AquaTec) ins Formular vorausgefüllt; eine Gebindegröße wird nur aus einer als Inhalt oder Füllmenge gekennzeichneten oder einzeln stehenden Angabe übernommen. Eine erkennbare Marke „ja!“ ergänzt den Produktnamen, wird aber nicht als Hersteller eingetragen. Treffer und Stammdaten am Gebinde prüfen, bevor gespeichert oder gebucht wird. Erkannten Text kann man weiterhin aufklappen und manuell auswählen. Das Etikettfoto wird nicht dauerhaft gespeichert; dafür den separaten Foto-Upload verwenden. HEIC hängt vom Tesseract-Build des Servers ab; bei Problemen JPEG aufnehmen. Gefahrstoffangaben und ein optional hochgeladenes SDB-PDF bleiben manuell zu prüfen.

Seit 0.1.11 wird ein JPEG/PNG/WebP-Etikettfoto vor der Texterkennung im Browser auf den mittleren Etikettbereich zugeschnitten, vergrößert und kontrastreicher gemacht. Wenn die Bildverarbeitung fehlschlägt, wird das Original gesendet. Ein deutlich lesbares „Geschirrspülmittel Zitrone“ und eine einzeln stehende Füllmenge wie „1 L“ können so als Stammdaten vorgeschlagen werden. Die Erkennung ist weiterhin OCR, keine Google-Lens-Bildsuche: Sie kann Logos und Schriften falsch lesen; den Treffer und die genaue Variante immer vor dem Speichern prüfen. Die Ergebnisbox nutzt auf schmalen Displays die volle Formularbreite.

## Produktsuche mit SearXNG und KI (0.1.16)

Unter Profilmenü → Einstellungen → Persönlich → Weitere Einstellungen → Gefahrstoffkataster den Anbieter **Mistral** oder **OpenAI** auswählen und den jeweiligen API-Schlüssel speichern. Vorhandene OpenAI-Schlüssel bleiben erhalten. Schlüssel sind getrennt pro Benutzer und Anbieter mit ICrypto verschlüsselt. Löschen entfernt nur den ausgewählten persönlichen Schlüssel. OpenAI verwendet optional weiterhin `GSK_OPENAI_API_KEY` oder `/etc/nextcloud/gefahrstoffkataster-openai.key`. Mistral hat keinen OpenAI-Fallback. Die Kontingente des eigenen API-Vertrags gelten; kostenlose Verfügbarkeit wird nicht garantiert.

Die App erwartet SearXNG fest unter `http://127.0.0.1:8384/search` auf dem Nextcloud-Host. Docker-Port nur an `127.0.0.1:8384:8080` binden. SearXNG benötigt `json` in `search.formats` und eine gezielte interne Freigabe in `limiter.toml`. Die öffentliche Oberfläche bleibt unter https://suche.dasnetzundich.de hinter Anubis. Die lokale HTTP-Freigabe gilt nur für diesen festen Endpunkt, ohne Weiterleitungen; globale Nextcloud-Sicherheitsoptionen müssen nicht geändert werden. Containerisierte Nextcloud-Installationen werden mit dieser Loopback-Adresse nicht unterstützt.

Jeder Klick führt eine SearXNG-Suche und höchstens einen KI-Aufruf aus (Mistral: `ministral-8b-2512`, OpenAI: `gpt-5.4-nano`, ohne Websearch-Tool). Maximal zwölf Treffer mit begrenzten Textauszügen werden zusammen mit Produktname/Herstellerhinweis an den ausgewählten Anbieter gesendet. Keine Fotos oder PDF-Inhalte. Die KI schlägt bis zu drei Varianten vor. URLs müssen aus den tatsächlichen Suchtreffern stammen. Textauszüge beweisen weder die Aktualität eines SDB noch die exakte Variante: vor Übernahme Originalquelle öffnen und prüfen. Gefahrstoff-Einstufung, UFI, GHS und H-Sätze werden nicht übernommen. PDFs lassen sich weiterhin manuell hochladen.

Suchfehler lösen keinen KI-Aufruf aus. Bei fehlendem Schlüssel oder erschöpftem Kontingent erfolgt kein automatischer Anbieterwechsel. Bestehende Produkte können weiter bearbeitet werden. Es gibt noch keinen Cache für wiederholte Suchaufrufe. Der End-to-End-Test erfordert die lokale SearXNG-Instanz und den persönlichen API-Schlüssel auf dem Zielserver.

## Funktionen und Grenzen

### SDB nachträglich auswerten (0.1.21)

In der Produktbearbeitung steht am SDB-PDF-Feld „SDB auswerten“ zur Verfügung, auch für vorhandene Produkte. Die Reihenfolge ist: neu ausgewählte PDF-Datei, gespeicherte lokale PDF-Kopie, hinterlegter SDB-Link beim Hersteller. Seit 0.1.22 lädt der Server beim Auswerten eines Links dessen PDF als lokale Vorschau. Mit „Produkt speichern“ wird exakt diese Kopie als SDB angehängt. **Der Herstellerlink bleibt dabei erhalten.** „SDB vom Link aktualisieren“ lädt ausdrücklich eine neue Version vom Link, auch wenn bereits eine lokale Kopie vorhanden ist. Alte gespeicherte PDFs werden nicht gelöscht; Übersicht und Export verweisen auf die zuletzt gespeicherte Kopie. Ohne Speichern ändern sich weder Produkt noch dauerhaft zugeordnetes SDB.

Der Abruf unterstützt öffentliche HTTPS-PDF-Links und bis zu drei geprüfte Weiterleitungen. Private/Loopback-Adressen, Zugangsdaten in URLs, abweichende Ports, HTML-Seiten und PDFs über 15 MB werden abgewiesen. Die Verbindung läuft über den Nextcloud-Server, nicht über den Browser. Es werden keine Herstellerseiten in Frames eingebettet und keine CSP-Freigaben aufgeweicht. Herstellerportale mit Anmeldung oder Downloadformular benötigen weiterhin einen manuellen PDF-Upload. Vorschaukopien sind benutzergebunden, 24 Stunden gültig und auf drei pro Benutzer begrenzt; alte Vorschauen werden beim nächsten Linkabruf bereinigt.

Fehlen auf dem Server Werkzeuge zur PDF-/Texterkennung, kann eine bereits vom Link geladene PDF-Kopie trotzdem gespeichert werden. Die Oberfläche zeigt dann ausdrücklich „Textauswertung nicht verfügbar“; es werden keine Gefahrstoffangaben daraus vorgeschlagen. Bei einer neuen PDF-Prüfsumme wird ein alter Prüfvermerk verworfen, bis die neue Version bestätigt wurde.

Die ersten vier PDF-Seiten werden lokal mit `pdftotext` gelesen; Seiten mit weniger als 100 Textzeichen werden zusätzlich über `pdftoppm` und Tesseract erkannt. Servervoraussetzungen: `poppler-utils`, `tesseract-ocr`, `tesseract-ocr-deu`, `tesseract-ocr-eng`, `/usr/bin/timeout` und PHP `proc_open` (Debian: `apt install poppler-utils tesseract-ocr tesseract-ocr-deu tesseract-ocr-eng`). Maximal 15 MB pro PDF, zwölf Sekunden je Werkzeugaufruf; temporäre Dateien werden nach der Anfrage entfernt. Keine Übertragung an KI-Anbieter und kein API-Schlüssel nötig.

**PDF-Auswertung auf dem Server prüfen (0.1.24):** Download und Texterkennung sind getrennte Schritte. Fehlende PDF-Werkzeuge, fehlende Tesseract-Sprachdaten, Zeitlimits und geschützte PDFs erhalten nun getrennte Fehlermeldungen. Die App sucht Programme in `/usr/bin`, `/usr/local/bin`, `/bin` und dem PHP-Prozesspfad; ein eingeschränkter PHP-PATH verdeckt installierte Standardprogramme damit nicht mehr. Werkzeugfehler werden intern nur zur Einordnung gelesen; PDF-Inhalte und rohe Fehlerausgaben werden nicht an Browser oder Log übermittelt.

Auf dem **Nextcloud-Host**, nicht auf dem Arbeitsplatz-PC, bei Debian/Ubuntu installieren:

```sh
sudo apt-get update
sudo apt-get install poppler-utils tesseract-ocr tesseract-ocr-deu tesseract-ocr-eng coreutils
sudo -u www-data /usr/bin/pdftotext -v
sudo -u www-data /usr/bin/pdftoppm -v
sudo -u www-data /usr/bin/tesseract --list-langs
sudo -u www-data /usr/bin/timeout --version
```

`tesseract --list-langs` muss mindestens `deu` und `eng` enthalten. Falls PHP unter einem anderen Konto läuft, dieses statt `www-data` verwenden. Bei containerisierter Nextcloud müssen die Werkzeuge im PHP-/Nextcloud-Container vorhanden sein; eine Installation nur auf dem Docker-Host genügt nicht. Danach erneut „SDB auswerten“ wählen. Eine bereits geladene Kopie und der Herstellerlink bleiben erhalten. Diagnoseprüfungen: `php tests/sds-tool-diagnostics.php` und der PDF-Extraktionstest mit Text-/Scan-Testdateien.

Vorschläge stammen ausschließlich aus dem erkannten Abschnitt 2: ausdrücklich geschriebene Gefahrenklassen/Kategorien, Signalwort, GHS-Codes und H-/EUH-Zeilen. GHS-Piktogramme als Bild werden nicht interpretiert. OCR kann Zeichen verwechseln; umgebrochene Sätze und ungewöhnliche Layouts müssen im Original ergänzt werden. Zu jedem Vorschlag erscheinen Seitenzahl, Textfundstelle und bisheriger Formularwert. Erst nach Bestätigung von Produkt, Hersteller und SDB-Version können einzelne Felder übernommen werden. Ein positiver Einstufungshinweis kann nach Bestätigung die vorhandene Gefahrstoff-Markierung setzen; negative oder unklare Ergebnisse entfernen sie niemals. Fehlende Erkennung ist keine Entwarnung. „Nicht als gefährlich eingestuft“ bedeutet nicht automatisch „kein Gefahrstoff“.

Ein bestätigtes Ergebnis kann als Prüfvermerk mit Status, Dateiname, PDF-Prüfsumme und Zeitpunkt am Produkt gespeichert werden. Die Übersicht zeigt insbesondere „SDB: Prüfung erforderlich“ dauerhaft an. Der Vermerk dokumentiert die bestätigte Auswertung, keine behördliche oder fachliche Freigabe. Bei Auswahl eines anderen PDFs wird der bisherige Vermerk im Formular verworfen.

Prüfungen: `php tests/sds-analysis.php`, `php tests/sds-product.php`, `php tests/sds-download.php`, `php tests/sds-drafts.php`, `php tests/research-response.php`, `node tests/sds-ui.js`, `node --check js/app.js` und PHP-Syntaxprüfung aller PHP-Dateien. Mit `python3 tests/create-sds-fixtures.py /tmp/gsk-test-fixtures` (ReportLab und Pillow erforderlich) synthetische PDF-Testdateien erstellen; anschließend `php tests/sds-extraction.php /tmp/gsk-test-fixtures/text.pdf /tmp/gsk-test-fixtures/scan.pdf` ausführen. `node tests/sds-browser.js` prüft mit Playwright/Chromium die echte Hauptvorlage auf Desktop und Smartphone gegen simulierte API-Antworten einschließlich Linkabruf, bestätigter Übernahme, lokaler Kopie und erhaltenem Herstellerlink. Vollständiger Berechtigungs- und Serverintegrationstest erfordert weiterhin Nextcloud.

### Oberfläche 0.1.22

Die Produktbearbeitung ist als feste Ansicht innerhalb der App eingebunden, mit Bereichsnavigation, scrollbarem Formular und sichtbarer Speicherleiste. Die Hauptvorlage `templates/main.php` und ein neutrales App-Symbol sind jetzt im Repository enthalten. Ein einzelnes Stylesheet nutzt Nextcloud-Farben und durchgängig passende Feldhöhen; der native Dateiauswahlknopf und Datums-/Auswahlfelder werden nicht mehr durch zusätzliche Innenabstände abgeschnitten. Es gibt keine zusätzliche unscharfe Fensterebene. Die Kamera-/Bestandsdialoge bleiben kompakte modale Dialoge. Ein bereits installierter `js/scanner.js`-Decoder wird weiterhin geladen; fehlt er, bleibt die manuelle EAN-Eingabe verfügbar und die App fragt kein fehlendes Script an.

**Korrektur 0.1.23:** Geschlossene Dialoge werden ausdrücklich über `dialog:not([open])` ausgeblendet. Damit können übergreifende Nextcloud-/Theme-Stile weder Produktbearbeitung noch Kamerafenster beim App-Start sichtbar machen. Die Kamera wird weiterhin erst durch „Scannen“ gestartet. Der Browser-Regressionstest simuliert überschreibende Host-Stile und prüft den App-Start ohne Kameraaufruf, das Öffnen und Schließen aller Dialoge sowie Scanner-Stopp über Schließknopf, Escape und manuelle Eingabe auf Desktop und Smartphone.

**SDB-Erkennung 0.1.25:** Die Abschnittserkennung toleriert führende Änderungssterne und Aufzählungszeichen (z. B. `* ABSCHNITT 2`, `· 2.1`, `· Signalwort`). Originaltexte und PDF-Seiten bleiben als Fundstellen erhalten. Der ausdrückliche Satz „Der Stoff ist gemäß CLP-Verordnung nicht eingestuft“ wird in Abschnitt 2.1 erkannt, ohne daraus automatisch „kein Gefahrstoff“ abzuleiten. Andere Hauptabschnitte beenden die Auswertung zuverlässig; Einstufungen einzelner Inhaltsstoffe werden nicht als Produkteinstufung übernommen. Wenn in den gelesenen Seiten kein SDB-Abschnitt 2 vorliegt, erklärt die App den Unterschied zu Produktmerkblatt und Betriebsanweisung. Diese Formatkorrektur betrifft die Auswertung nach erfolgreichem Textauslesen, nicht die Installation der PDF-/OCR-Werkzeuge auf dem Server. Zusätzliche Format-Regressionen sind in `tests/sds-analysis.php` enthalten; mitgelieferte Hersteller-PDFs wurden nur lokal geprüft und nicht ins Repository aufgenommen.

Die Aktionslinks in den Produktkarten verwenden seit 0.1.25 für Buttons und Links dieselbe Darstellung und explizite Zeilenhöhe. „Bestand ändern“, „Bearbeiten“ und die Dokument-/Herstellerlinks stehen dadurch auf einer gemeinsamen Textlinie. Der Browser-Test prüft die tatsächlichen Textpositionen je Zeile auf Desktop und Smartphone.

0.1.14 korrigiert die Schreibzugriffe auf die Datenbank: Einfügen und Produktänderungen laufen über Nextclouds öffentlichen QueryBuilder. Damit funktionieren auch die Initialisierung und das Anlegen von Lagerorten auf Nextcloud 35. Bereits vorhandene Lagerorte werden beim erneuten Eingeben erkannt; technische Fehler werden getrennt gemeldet und im Nextcloud-Protokoll dokumentiert.

Die Desktopübersicht nutzt eine breite, ruhige Listenansicht mit Bestandskennzahlen. In der Produktbearbeitung führen die Bereiche Stammdaten, Recherche, Gefahrstoffangaben und SDB/Dateien durch das Formular. Auf dem Smartphone ordnen sich die Felder untereinander an.

- Produkte, Gebindegröße, Menge je Lagerort, Zugänge/Entnahmen/Korrekturen, Buchungsprotokoll in der Datenbank, mehrere Bilder und SDB-PDF.
- Excel `.xlsx` mit „Gesamtbestand“ und „Gefahrstoffverzeichnis“ sowie CSV. Der XLSX-Export benötigt die PHP-Erweiterung `zip`.
- EAN-Abgleich mit dem eigenen Bestand, optionale Etikett-Texterkennung mit lokalem Tesseract, bestätigbare Stammdaten von Witty-/Flamingo-Herstellerseiten sowie optional eine KI-Websuche mit separatem API-Schlüssel. Eingetragene Einstufungen und SDB müssen fachlich abgeglichen werden.
- Die App verwendet **einen gemeinsamen Bestand** für Administratoren und Mitglieder der Gruppe `freibad-gefahrstoffe`. Sie besitzt noch keine gesonderten Freigaberollen oder eine UI für das Buchungsprotokoll.
- Die frühere Docker-Web-App und diese Nextcloud-App verwenden getrennte Datenbanken. Die neue App startet leer; ein Import aus der Docker-Version ist noch nicht implementiert.

Für eine Sicherung sind **Nextcloud-Datenbank und AppData** gemeinsam nötig. Der Tabellenexport ist keine vollständige Datensicherung für Fotos, PDFs und Buchungshistorie.

Quellcode der App: AGPL-3.0-or-later. Das beigefügte Gemeindewappen wurde vom Nutzer als Bildvorlage geliefert; eine Weiterverbreitung der Grafik außerhalb des Projekts bedarf eigener Prüfung.



## Korrekturen 0.1.18

Mistral verwendet `/v1/chat/completions` mit `ministral-8b-2512`, passend zu Anfrage und Antwortparser. OpenAI Nano verwendet ein verbindliches JSON-Schema und reasoning `none`, damit das Ausgabelimit für die Formularantwort zur Verfügung steht. Unvollständige, abgelehnte, leere und fehlerhafte Antworten werden getrennt gemeldet. Quellenprüfung bleibt strikt: nicht belegte URLs werden nicht übernommen. Ergebnisfelder werden bei Suchmeldungen sichtbar gescrollt. Regressionstest: `php tests/research-response.php`. Live-API-Prüfung auf dem Zielserver erforderlich.
