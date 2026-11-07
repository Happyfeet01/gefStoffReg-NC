# Entwicklungsplan: Lager + Gefahrstoffkataster

Stand: 2026-11-07. Ziel: schrittweiser Ausbau zur gemeinsam genutzten Material- und Gefahrstoffverwaltung. Dieses Dokument ist eine Planung, keine abgeschlossene Implementierung der Freigaben.

## 1. Gemeinsamer Produktbestand, zwei Ansichten

- **Lager**: zeigt alle Produkte und ihre Bestände, einschließlich Gefahrstoffen; hier gehören Zugang, Entnahme und Bestandspflege hin.
- **Gefahrstoffkataster**: zeigt nur Produkte, die ausdrücklich als Gefahrstoff gekennzeichnet sind, mit SDB, Gefahrenkennzeichnung und Prüfstatus.
- Umschalten verändert weder Produktdetails noch Bestände und legt keine zusätzlichen Produktdatensätze an.
- Die bestehende freie Produktkategorie (`category`) ist keine Gefahrstoffeinstufung. Das bestehende Feld `hazardous` bleibt ein *eigenständiges* Merkmal.
- Die erste Umsetzung betrifft ausschließlich die Navigation und Filterung im Browser. Alle bisherigen Serverrouten und Exporte bleiben unverändert.

## 2. Materialarten (nächster Schritt)

Zusätzlich zur frei eingegebenen Unterkategorie einen kontrollierten Typ `material_kind` einführen:

| Typ | Beispiele | Gefahrstoffangaben |
| --- | --- | --- |
| `chemical` | Chlorbleichlauge, pH-Heber, Reiniger | Bei entsprechender Einstufung erforderlich |
| `consumable` | Toilettenpapier, Papierhandtücher, Müllbeutel | Nicht als Pflichtfelder erzwingen |
| `other` | Ersatzteile, Werkstattmaterial | Nur bei tatsächlichem Bedarf |

- `material_kind` und `hazardous` dürfen **nicht** gleichgesetzt werden. Ein nicht als Gefahrstoff gekennzeichnetes Produkt ist dadurch nicht automatisch unbedenklich.
- Alte Produktdatensätze werden beim Update nicht anhand von OCR oder `hazardous=false` als Verbrauchsmaterial eingestuft. Bis zur expliziten Zuordnung bleibt ihr Typ offen (z. B. `unclassified` in der Darstellung).
- Keine SDB-Pflicht für Toilettenpapier; vorhandene SDB- und Dokumentdaten dürfen bei Typwechseln nicht gelöscht werden.
- Kategorien/Unterkategorien sind Filter innerhalb des Lagerbereichs; die Gefahrstoffansicht kann eigene fachliche Filter anbieten.

## 3. Teilen und Berechtigungen (Folge-PR, serverseitig)

Die aktuelle `InventoryController::permitted()`-Prüfung gewährt Administratoren und Mitgliedern der Gruppe `freibad-gefahrstoffe` denselben Zugang. Sie reicht für Kolleg:innen mit reinen Leserechten oder eingeschränkten Änderungsrechten nicht aus.

Vorgeschlagene Fähigkeiten:

| Rolle | Produkte/Bestände lesen | Bestände buchen | Produktstammdaten ändern | Gefahrstoff-/SDB-Angaben ändern | Freigaben verwalten |
| --- | --- | --- | --- | --- | --- |
| Leser | Ja | Nein | Nein | Nein | Nein |
| Lagerbearbeiter | Ja | Ja | Nur nicht sicherheitsrelevante Felder | Nein | Nein |
| Gefahrstoffbearbeiter | Ja | Optional nach separater Freigabe | Ja | Ja | Nein |
| Verwalter | Ja | Ja | Ja | Ja | Ja |

- Rollen gelten für Nextcloud-Benutzer/-Gruppen, optional zusätzlich pro Standort. Bewilligte Rechte müssen bei **jeder** API-Aktion serverseitig geprüft werden; ein ausgeblendeter Button schützt nicht.
- Die bestehenden Vollschreib-Routen (`saveProduct`/`updateProduct`) ersetzen das gesamte JSON-Dokument. Für eingeschränkte Rollen dürfen sie nicht freigegeben werden, bevor eine serverseitige feldweise Änderungsprüfung oder getrennte schreibende Endpunkte existieren.
- Alle lesenden Routen (Bootstrap, lokale EAN-Suche, Export, Dateidownload, Vorschau, Recherche) auf die tatsächlich berechtigten Standorte und Produkte begrenzen. Auch Download-IDs dürfen keine Standortberechtigung umgehen.
- Rechteentzug muss sofort auf API-Ebene wirken; Standard ist `deny`. Nutzer dürfen sich keine eigenen Verwaltungsrechte geben.
- Die Bootstrap-Route legt derzeit bei leerer Lagerorttabelle Standardlagerorte an. Vor Einführung eines streng read-only-Prüfkontos muss diese Initialisierung in Installation/Migration bzw. einen Adminvorgang verlagert werden.
- Bestehende Lagerbuchungen werden über `gsk_event` mit Benutzerkennung dokumentiert. Produktstammdaten, SDB-Änderungen und Freigaben benötigen ein ergänzendes unveränderliches Änderungsprotokoll.

## 4. Standorte und gemeinsamer Datenbestand

- Lagerort (z. B. Chemieraum) nicht mit Standort (z. B. Freibad) verwechseln. Eine spätere Standortzuordnung zu Lagerorten erfordert eine explizite, nachweisbare Migration.
- Neue Mitarbeiter sehen nur explizit freigegebene Daten. Kein globaler Zugriff allein durch den Nextcloud-Login.
- Ein Produkt existiert einmal; der Bestand wird je Lagerort geführt. Derselbe Artikel kann mehreren Lagern zugeordnet werden, ohne Duplikate im Kataster zu erzeugen.

## 5. Lesender Qualitätsscan

- Für die geplante wöchentliche Prüfung eine explizite **lesende** API anbieten, die nur freigegebene Datensätze liefert.
- Ein Audit-Bericht sollte Produkt-ID, Typ/Kategorie, SDB-PDF bzw. Herstellerlink, OCR-Zuordnungsstatus mit Nachweis und Lagerort-Referenzen enthalten. Fehlende OCR-Nachweise als *unbekannt* behandeln, nicht als Fehler behaupten.
- Keine Bestandsbuchung, kein SDB-Download-Refresh, kein Schreiben von Prüfvermerken durch den Audit-Zugang. Ein reiner GET-Aufruf muss ohne Datenbank-Seiteneffekte bleiben.
- Client/Zugriff über die vorhandene Nextcloud-Anbindung ist separat einzurichten; eine neue API allein macht die App nicht automatisch für externe Prüfer auslesbar.
- In den Berichten neue bzw. geänderte Befunde gegenüber dem letzten erfolgreichen Lauf markieren und unveränderte nicht wiederholen.

## 6. Abnahme und Rollout

1. Bestands- und Dokument-Backup auf der Testinstanz erstellen.
2. Umschalter am Desktop und Smartphone testen: Lager zeigt alle, Kataster nur ausdrücklich markierte Gefahrstoffe; keine Bestandsänderung durch Ansichtswechsel.
3. Vor Rollenfreigabe API-Tests für Lesen, Schreiben, Download, Export, Rollenentzug, Standortwechsel und direkte URL-Aufrufe mit unterschiedlichen Konten ergänzen.
4. Altbestand einschließlich OCR, PDFs, Bestandsereignissen und Export auf Vollständigkeit vergleichen.
5. Schema und Rechte gestaffelt ausrollen; Release-Version erst erhöhen, wenn die jeweiligen Funktionen fertig und getestet sind.

**Nicht Gegenstand des ersten PRs:** neue Datenbanktabellen, neue Nutzerrechte, Materialtyp-Migration, produktiver Audit-Endpunkt oder automatische Bestandsänderungen.


## Implementierungsstand: Freigaben, erster Schritt (#7)

**Status:** Entwicklungsbranch, noch nicht in `main`. Freigaben gelten zunächst **instanzweit** für alle Produkte und Lagerorte dieser App; *keine* Standort-Einschränkungen oder freigegebenen Teilmengen. Diese folgen nach separater Daten- und Berechtigungsprüfung.

- Verwalter öffnen im Kopfbereich **Freigaben**, geben eine existierende Nextcloud-Benutzer- oder Gruppen-ID ein und wählen **Lesen**, **Lager bearbeiten**, **Gefahrstoffdaten bearbeiten** oder **Verwalten**.
- Die Server-API prüft die Rolle **für jede schreibende Route**. Browserseitig deaktivierte Eingaben sind nur Komfort, keine Zugriffskontrolle.
- **Lesen:** Produktlisten, Bestand, SDB, Dateidownload und Export; keine Bestands-, Produkt- oder Freigabeänderungen.
- **Lager bearbeiten:** Bestand buchen sowie neue Produkte der Arten Verbrauchsmaterial/Sonstiges erfassen. Bereits korrekt zugeordnetes, **nicht als Gefahrstoff markiertes** Material darf in nicht sicherheitsrelevanten Stammdaten gepflegt und mit Fotos versehen werden. Gefahrstoffe, Chemikalien und ungeklärte Altprodukte bleiben in dieser Rolle unveränderbar. Keine SDB-/GHS-/UFI-Änderungen.
- **Gefahrstoffdaten bearbeiten:** Produkt- und Sicherheitsdaten, Dokumente und Bestände bearbeiten; keine Freigaben.
- **Verwalten:** sämtliche Produkt- und Bestandsrechte sowie Freigaben und neue Lagerorte.
- Nextcloud-Administratoren bleiben Verwalter. Die **bestehende Gruppe `freibad-gefahrstoffe`** behält ihre früheren vollen Rechte; die Freigabemaske kann diese Gruppe selbst nicht verwalten. Eine explizite Benutzerfreigabe setzt bei einem betroffenen Benutzer die Gruppenrolle außer Kraft (z. B. Leser trotz alter Gruppenzugehörigkeit).
- Freigaben werden als App-Konfiguration `shares_v1` gespeichert; zum Bearbeiten wird der vorherige Stand mit einer Revisions-Prüfsumme geprüft. **Keine DB-Migration** und kein Kopieren der Bestandsdaten. Rollen entziehen wirkt bei der nächsten Serveranfrage.
- Der lesende `/api/bootstrap`-Aufruf legt bei leerer Lagerorttabelle keine Lagerorte mehr automatisch an. Auf frischen Installationen müssen Verwalter daher Lagerorte manuell erfassen.
- Änderungen an Freigaben werden im Nextcloud-Log mit Akteur und Ziel protokolliert. Ein revisionssicheres fachliches Produkt-Änderungsprotokoll und **Standortrechte** bleiben separat offen.
- Die geplante automatisierte ChatGPT-Qualitätsprüfung benötigt weiterhin eine **eigene lesende Anbindung an die Gefahrstoffkataster-API**. Allein eine Freigabe im Benutzerkonto reicht für den vorhandenen Nextcloud-MCP nicht aus.

### Vor der Übernahme testen

```sh
php -l lib/Service/AccessPolicy.php
php -l lib/Controller/InventoryController.php
php tests/access-policy.php
php tests/role-protection.php
php tests/material-kind.php
php tests/sds-product.php
node --check js/app.js
node tests/inventory-navigation.js
# sofern Playwright/Chromium vorhanden:
node tests/sds-browser.js
```

Mit **separaten Testkonten** prüfen: Leser sieht Daten und Export, kann direkte Schreib-URLs nicht verwenden; Lagerbearbeiter darf Verbrauchsmaterial buchen und bearbeiten, jedoch keine SDB/GHS/UFI ändern; Gefahrstoffbearbeiter darf fachliche Felder bearbeiten; nur Verwalter darf Freigaben ändern. Danach Benutzerfreigabe entziehen und direkte API-Aufrufe erneut auf HTTP 403 prüfen. Die alte Gruppe in Nextcloud unverändert lassen, solange ihre Mitglieder keine expliziten Einzelrechte erhalten.
