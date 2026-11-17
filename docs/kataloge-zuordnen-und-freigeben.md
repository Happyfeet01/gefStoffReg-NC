# Kataloge auswählen, Altbestand zuordnen und extern freigeben

Ab Version 0.1.35 lädt die App Produkte, Mengen und Lagerorte des ausgewählten
Katalogs. Ein neuer Katalog wie „Hallenbad Schlüchtern“ beginnt mit vier Nullen.
Der bisherige Bestand bleibt unter **Ohne Katalog**, bis er gezielt zugeordnet wird.
Die App verschiebt beim Laden, beim Start und beim Upgrade keine Produkte automatisch.

## Vorhandene Produkte zuordnen

1. **Produkte zuordnen** öffnen (Verwalter).
2. Einen vorhandenen Zielkatalog wählen.
3. Gewünschte Produkte aus „Ohne Katalog“ markieren, oder **Alle auswählen** nutzen.
4. **Ausgewählte Produkte zuordnen** anklicken.

Das ist ein Verschieben, keine Kopie. Produkt-IDs, SDB, Fotos, Mengen und
Buchungshistorie bleiben erhalten. Die Operation ist auf maximal 200 Produkte pro
Aufruf begrenzt und wird bei einem Fehler vollständig zurückgerollt.

Lagerorte, die ausschließlich von den ausgewählten Produkten genutzt werden,
wechseln mit. Ein gleichnamiger Lagerort im Ziel wird wiederverwendet.
Falls andere Produkte denselben bisherigen Lagerort noch verwenden, bleibt er
erhalten und die verschobenen Produkte erhalten einen eigenen Lagerort im Ziel.
Dessen Name enthält den Katalognamen und die bisherige Lagerort-ID.
Diese Trennung betrifft auch historische Buchungen; Mengen werden nicht verändert.

Nach der Zuordnung den Zielkatalog auswählen. Dort erscheinen die übernommenen
Produkte und die zugehörigen Lagerorte. Neu erfasste Produkte und Lagerorte werden
dem aktiven Katalog zugeordnet. Der gewählte Katalog bleibt in diesem Browser-Tab
auch nach dem Neuladen ausgewählt.

## Externe Links je Katalog

1. Den gewünschten Katalog oben auswählen.
2. **Freigaben → Externe Links** öffnen.
3. Die angezeigte Zeile **Externe Freigaben für: …** kontrollieren.
4. Bereich, Laufzeit und gegebenenfalls Passwort sowie Schreibberechtigung wählen.
5. Einen neuen Link erstellen.

Jeder neue Link erhält eine feste Katalogbindung. Die Auswahl `Lager`,
`Gefahrstoffkataster` oder `Beides` grenzt die sichtbaren Inhalte innerhalb dieses
Katalogs zusätzlich ein. Der Server prüft die Bindung bei jedem Datenabruf,
Dateidownload und jeder Bestandsbuchung. Ein URL-Parameter des Gastes kann den
Katalog nicht ändern. Die interne Linkliste zeigt die Links des aktiven Katalogs.

Ältere Links ohne Katalogbindung bleiben auf **Ohne Katalog** begrenzt.
Nach einer Produktzuordnung sind diese Produkte deshalb im alten Link nicht mehr
sichtbar. Für den Zielkatalog einen neuen Link erstellen und den bisherigen bei
Bedarf widerrufen. Bestehende Links werden nicht stillschweigend auf neue Kataloge
erweitert. Auch für „Ohne Katalog“ lassen sich explizite Links erstellen.

## Update und Teststatus

Version 0.1.36 ergänzt eine Folgemigration für früher angelegte `gsk_catalog`-
Tabellen: Die Defaults von `created_at` und `updated_at` werden an das erwartete
Schema angepasst. Vorhandene Zeitstempel bleiben erhalten. Nach dem Einspielen
erneut das Nextcloud-App-Upgrade ausführen. Die Korrektur betrifft ausschließlich
die beiden Katalogspalten; Schemawarnungen anderer Apps werden dadurch nicht behoben.

Version 0.1.35 erneuert Nextclouds Cachekennung für die geänderten Skripte. Das
Nextcloud-App-Upgrade ausführen, bevor die App wieder benutzt wird. Die Migration
erlaubt gleiche Lagerortnamen in unterschiedlichen Katalogen; ein bisheriger
globaler eindeutiger Name-Index wird durch einen Index auf Katalog und Namen ersetzt.
Es werden keine Bestandszeilen automatisch umgehängt oder gelöscht.

Die GitHub-Prüfungen führen PHP-Syntaxprüfungen, Controller-/Serviceprüfungen gegen
eine SQLite-Testdatenbank sowie JavaScript- und Chromium-Tests aus. Die Browser-Fixture
prüft mobile und Desktop-Abmessungen, vier Nullzähler im neuen Katalog,
gespeicherte Auswahl, verspätete Antworten und den Zuordnungsdialog. Sie bildet
Nextclouds Rahmen und API-Antworten nach. SQLite bildet die produktiven Datenbank-
Zeilensperren nicht nach; reale Parallelbuchungen und Safari müssen auf der
Nextcloud-Testinstallation zusätzlich geprüft werden.

Vor Übernahme praktisch prüfen: „Ohne Katalog“ → leerer Testkatalog → zurück;
ein Testprodukt samt Bestand und SDB zuordnen; mit einem neuen externen Link
ausschließlich diesen Katalog lesen. Für Schreibfreigaben außerdem eine Buchung
im eigenen Katalog und einen abgewiesenen Versuch auf fremde IDs testen.
