# gefStoffReg-NC · Gefahrstoffkataster für Nextcloud 35

Diese **erste native App-Version** integriert das Inventar direkt in die Nextcloud-Navigation. Sie nutzt Nextcloud-Konten statt eines eigenen App-Passworts. Produkte, Lagerorte und Buchungen liegen in der Nextcloud-Datenbank; Fotos und PDFs in der privaten AppData-Ablage dieser Nextcloud-Instanz. Die Dokumente sind über die App für berechtigte Personen zugänglich, aber nicht automatisch im normalen Nextcloud-Dateibereich sichtbar.

## Installation auf einer Testinstanz

1. Den Ordner `gefahrstoffkataster` aus dem ZIP in einen beschreibbaren Custom-App-Pfad der **Nextcloud-35-Instanz** legen. Ordnername und App-ID müssen `gefahrstoffkataster` bleiben. In einem Docker-Setup muss der App-Ordner im Nextcloud-Container vorhanden sein; eine ZIP-Datei direkt in der App-Verwaltung hochzuladen ist hierfür nicht vorgesehen.
2. Die Nextcloud-Gruppe `freibad-gefahrstoffe` anlegen und die Mitarbeitenden zuordnen. Administratoren haben ebenfalls Zugriff. Ohne diese Gruppenzugehörigkeit erscheint der Navigationspunkt zwar, aber die App verweigert die Datenansicht.
3. Als Administrator im Nextcloud-Kontext `php occ app:enable gefahrstoffkataster` ausführen. Nextcloud führt die Datenbankmigration bei der Aktivierung aus. Abweichende Containerbenutzer/Pfade entsprechend deinem Setup einsetzen.
4. Als Administrator die App öffnen, ein Testprodukt anlegen, einen Bestand buchen und ein Foto aus der Galerie sowie ein SDB-PDF hochladen. Dann die Excel-Datei herunterladen und prüfen.

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
