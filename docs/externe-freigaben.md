# Externe Lesefreigaben – Entwicklungsstand

**Branch:** `feature/external-readonly-shares` (ab `main`, App-Version 0.1.28). Noch nicht für Produktivbetrieb freigegeben.

## Funktionsumfang

Verwalter öffnen **Freigaben → Externe Links**, vergeben eine Bezeichnung und wählen
den Bereich (nur Gefahrstoffkataster, nur Lager oder beides), eine feste Laufzeit
(1, 7 oder 30 Tage) und optional ein mindestens 12 Zeichen langes Passwort.

- Der Link ist ausschließlich **lesend** und benötigt kein Nextcloud-Konto.
- Ein 256-Bit-Zufallstoken wird **nur bei der Erstellung** im Klartext ausgegeben;
  die App-Konfiguration enthält ausschließlich seinen SHA-256-Hash.
- Ein optionales Passwort wird mit `password_hash` gespeichert, niemals im Klartext.
  Ein passwortloser Link ist allein durch den langen geheimen Token geschützt.
- Neue Links können jederzeit **widerrufen** werden. Ablauf und Widerruf werden
  bei jedem einzelnen Datenabruf und PDF-Download neu geprüft.
- Verwalter sehen im Dialog Bezeichnung, Bereich, Passwortschutz, Ablauf und Widerrufen;
  den ursprünglich erzeugten Link kann die App später nicht erneut anzeigen.
- Alle öffentlichen Endpunkte sind mit `PublicPage` markiert. Daten und PDF werden nur
  durch POST-Anfragen nach Prüfung von Token, Passwort und Bereich ausgeliefert.
- Auf öffentliche Antworten werden `Cache-Control: no-store`,
  `Referrer-Policy: no-referrer`, `X-Robots-Tag: noindex` und
  `X-Content-Type-Options: nosniff` angewendet.
- Fehlversuche beim Passwort sind mit Nextclouds `BruteForceProtection` versehen.
- Pro Bereich werden nur definierte Felder ausgegeben:
  - **Kataster:** nur Produkte mit `hazardous=true`; mit vollständigen
    Stammdaten, Beständen und Mengen je Lagerort, Gefahrstoffangaben, aktuellen
    SDB und Produktfotos. Die Lagerortübersicht enthält nur Lagerorte dieser Produkte.
  - **Lager:** alle Produkte mit Stammdaten, Gebindegrößen, Bestand je Lagerort,
    Herstellerlinks, Lagerhinweisen und Produktfotos. Keine Gefahrstoffdokumente
    oder SDB-Links.
  - **Beide:** alle Produkte einschließlich aller Lagerorte; zusätzlich
    Gefahrstoffangaben und SDB zu als Gefahrstoff markierten Produkten.
- PDF-Download prüft Dateityp, Produktbezug und Freigabebereich; fremde
  oder nicht zugeordnete Datei-IDs liefern keine Dokumente.
- Es gibt keine externe Schreibfunktion und keine Wiederverwendung
  authentifizierter Nextcloud-API-Endpunkte.

## Sicherheitsgrenzen

Diese Freigaben beziehen sich auf den **gesamten ausgewählten Bereich**; eine
Freigabe einzelner Lagerorte oder Produkte ist noch nicht enthalten.

Das URL-Token ist eine Zugangsberechtigung. Links dürfen nicht auf öffentlichen
Webseiten, in Ticket-Logs oder Screenshots veröffentlicht werden. Bei aktiviertem
Passwortschutz das Passwort getrennt vom Link übermitteln. Der Zielserver muss
HTTPS verwenden und seine Reverse-Proxy-, Referrer- sowie Caching-Konfiguration
korrekt setzen.

Die App speichert mehrere aktive Links in der Nextcloud-App-Konfiguration.
Die Revision verhindert veraltete Administrationsaktionen, stellt jedoch keine
transaktionale Mehrbenutzer-Sperre dar. Bei gleichzeitigen Link-Änderungen
sind Konflikte im Integrationstest zu prüfen.

## Abnahme vor Merge

1. **Syntax und Regression:**
   ```bash
   php -l lib/Service/ExternalShareService.php
   php -l lib/Controller/ExternalShareController.php
   php tests/external-shares.php
   node --check js/external-share.js
   node --check js/app.js
   node tests/inventory-navigation.js
   ```
2. **Verwalter:** Link für Kataster mit sicherem Passwort und 1 Tag Laufzeit
   erstellen. Freigabe in einem privaten Browserfenster ohne Nextcloud-Konto
   öffnen, korrektes und falsches Passwort testen.
3. **Scope-Trennung:** Kataster-Link darf ausschließlich als Gefahrstoff markierte
   Produkte zeigen und muss deren Mengen, Gebindegrößen, Lagerorte, SDB und Fotos
   lesend anzeigen. Lager-Link muss alle Bestände und zugehörige Fotos anzeigen,
   aber keine Gefahrstoff-SDB oder UFI-Felder liefern. „Beide“ zeigt alle Produkte
   mit allen für den jeweiligen Produktstatus verfügbaren Details.
4. **Öffentliche API:** Direkte GET-Aufrufe auf `/shared/{token}/data` und
   `/shared/{token}/file/{id}` dürfen nicht funktionieren (POST-only).
   Nur ein gültiger Token und ggf. das korrekte Passwort darf Daten liefern;
   es darf keinen Zugang zu regulären `/api/products`, `/api/stock`,
   `/api/shares` oder `/api/external-links` eröffnen.
5. **Datei-ID-Manipulation:** Mit fremden, veralteten, zu anderen Produkten
   gehörigen oder Nicht-SDB-Datei-IDs dürfen keine PDFs abgerufen werden.
6. **Rechte:** Ein Nextcloud-Leser und Lagerbearbeiter dürfen keinen externen
   Link erstellen, auflisten oder widerrufen; ein Verwalter darf dies.
7. **Widerruf:** Bereits geöffnete Seiten nach Widerruf neu laden und erneut
   Daten und PDFs abrufen. Alle Requests müssen scheitern.
8. **Ablauf:** Ablauf über manipulierte Testkonfiguration simulieren.
   Nicht nur die Seite, auch Daten und PDF müssen verweigert werden.
9. **Server:** Content-Security-Policy, öffentliche Template-Auslieferung,
   Reverse Proxy, HTTP-Caching, Zugriffslogs, CSRF und Brute-Force-Verhalten
   auf der tatsächlichen Nextcloud-35-Installation kontrollieren.
10. **Regression:** Bestehende Benutzer-/Gruppenfreigaben, Lagerverwaltung,
    SDB-Bearbeitung, interne Exporte und Lagerbestandsbuchungen testen.

**Nicht automatisch deployen oder nach `main` mergen.** Die öffentliche
Freigabe benötigt echte End-to-End-Tests auf der Zielinstallation.

## Erweiterung: vollständige externe Leseansicht

Die externe Freigabe stellt pro erlaubtem Produkt die **gleichen fachlichen Daten
wie die interne Produktmaske** dar, jedoch ausschließlich als Text, Links und
gesicherte Downloads. Eine interne Bearbeitungsmaske wird ausdrücklich nicht
anonym gerendert. Produkte zeigen direkt ihren Gesamtbestand; die weiteren
Stammdaten, Bestände je Lagerort, Sicherheitshinweise und Dateien sind in
aufklappbaren Detailbereichen verfügbar.

Der öffentliche Endpunkt gibt ausschließlich definierte Felder aus (Allowlist).
Interne API-Schlüssel, Benutzerrechte, Freigabegeheimnisse, SDB-Prüfnotizen,
Buchungsakteure und nicht zum Produkt gehörende Dateien werden **nicht**
veröffentlicht. Produktfotos werden nur auf Abruf über den
passwort-/token-/bereichsgeprüften POST-Dateiendpunkt ausgeliefert. Es gibt
keine externen Schreib- oder Buchungsrouten.

### Neue Regressionstests

```bash
php -l lib/Service/ExternalShareProjection.php
php -l lib/Controller/ExternalShareController.php
php tests/external-share-projection.php
node --check js/external-share.js
node tests/external-share-password.js
node tests/external-share-scroll.js
```

Mit zwei produktiven Testprodukten (ein Gefahrstoff, ein normales
Verbrauchsmaterial) den Inhalt aller drei Freigabebereiche prüfen, einschließlich
Lagerorte, Gebindeanzahl und Gesamtmengen, Produktfotos, letztem SDB,
direkten Datei-ID-Requests und Widerruf. Auch eine Kataster-Freigabe darf
Bestände *ihrer* Gefahrstoffe anzeigen.


### Bestandsschutz für bereits verteilte Links

**Nur neu erstellte externe Links** erhalten die vollständige Leseansicht.
Diese Einstellung wird beim Anlegen als `full_details=true` an der Freigabe
gespeichert. Bereits zuvor erstellte Freigaben haben dieses Merkmal nicht und
behalten deshalb den bisherigen eingeschränkten Umfang. Die Oberfläche zeigt
für solche Links einen Hinweis, statt fehlende Bestandsdaten als Nullbestand
auszugeben. Produktfotos bleiben über ältere Links nicht abrufbar.

Zum Umstellen eines vorhandenen Testlinks: Alte Freigabe in der App widerrufen,
neuen Link mit dem gewünschten Bereich und Passwort erstellen und diesen
an die vorgesehenen Empfänger übermitteln. Das geschieht bewusst **nicht**
automatisch: Ein App-Update darf eine bereits verteilte Freigabe nicht
ungefragt um weitere Lagerdaten erweitern.


## Persönliche externe Bestandsbearbeitung (0.1.33)

Freigaben für Personen ohne Nextcloud-Konto haben jetzt zwei Stufen: Nur Lesen (Standard) und Bestände bearbeiten. Bestehende Links bleiben ausschließlich lesend.
Schreibrechte erfordern einen separaten Link pro Mitarbeiter, einen Namen (2–60 Zeichen), ein Passwort mit mindestens 12 Zeichen und eine befristete Gültigkeit von 1, 7 oder 30 Tagen. Der Link kann jederzeit widerrufen werden.

Der öffentliche POST-Endpunkt /shared/{token}/stock prüft Token, Passwort, Ablauf, Widerruf, stock_editor-Rolle, Produktbereich und einen speziellen Anti-CSRF-Header X-GSK-Stock-Action: 1 bei jeder Buchung. Nur Zugang, Entnahme und Bestand korrigieren sind erlaubt. Produktstammdaten, Gefahrstoffangaben und Sicherheitsdatenblätter bleiben unveränderbar.

Die Buchung enthält eine erwartete Bestandsmenge in Tausendstel Gebinde; konkurrierende Änderungen werden mit HTTP 409 abgewiesen. Alle Änderungen werden in gsk_event mit before/after, Notiz und der Zugangskennung external:<16-stellige Link-ID> protokolliert. Im Nextcloud-Log wird diese ID mit dem eingetragenen Mitarbeiternamen verknüpft. Die tatsächliche Identität kann durch einen weitergegebenen Link nicht mit Sicherheit bewiesen werden.

### Tests vor Merge

```sh
php -l lib/Service/ExternalShareService.php
php -l lib/Service/ExternalStockBookingService.php
php -l lib/Controller/ExternalShareController.php
php tests/external-shares.php
php tests/external-stock-validation.php
php tests/external-share-routes.php
node --check js/app.js
node --check js/external-share.js
node tests/external-stock-browser.js
node tests/external-share-scroll.js
```

Für den Produktivbetrieb zusätzlich echte Nextcloud-Testdaten verwenden und Freigabebereich, widerrufene bzw. abgelaufene Links, fehlendes Passwort, direkte POST-Manipulation, gsk_event, Parallelbuchungen und insbesondere zwei gleichzeitige Erstbuchungen bei bisher leerem Produkt-/Lagerort prüfen. Falls gsk_stock noch keine eindeutige Kombination von product_id und location_id garantiert, muss vor einem Merge ein passender Datenbankschutz umgesetzt werden.

Dieser Stand ist ein Draft und darf vor bestandenen Sicherheitstests nicht produktiv eingesetzt werden.

## Einfache Bedienung für Lagerpersonal (ab 0.1.34)

Die externe Mitarbeiteransicht ist bewusst nach dem täglichen Ablauf gestaltet:

1. Produkt über den Namen oder die Suche finden.
2. Aktuellen Bestand und Lagerort direkt in der kompakten Produktkarte sehen.
3. **Bestand ändern** öffnen, ohne erst technische Produktdetails auszuklappen.
4. **Einlagern**, **Entnehmen** oder **Bestand korrigieren** auswählen, Menge und optional eine Bemerkung eintragen, **Änderung speichern**.
5. Der aktuelle Bestand wird erneut vom Server geladen und die Bestätigung erscheint direkt am betroffenen Produkt. Fehler werden unmittelbar am Buchungsformular angezeigt.

Stammdaten, SDB und weitere Gefahrstoffinformationen stehen separat unter **Weitere Produktinfos**. Dort werden Beschriftungen und Werte als kompakte Paare in einer hellen Karte angezeigt: beispielsweise **PRODUKTNAME** direkt über **Witty-Pool Flocal 5**. Die Lagerortübersicht ist standardmäßig eingeklappt. Lange Warntexte werden bei älteren Links auf einen kurzen Hinweis reduziert.

**Sicherheit unverändert:** Die Gestaltung fügt keine Schreibrechte hinzu. Leselinks bleiben read-only; externe Schreibaktionen sind nur mit dem in PR #18 geprüften persönlichen stock_editor-Zugang möglich.

Tests: `node tests/external-share-usability.js`, `node tests/external-share-layout.js`, `node tests/external-stock-browser.js` (Browser erforderlich). iPhone-Safari-Abnahme weiterhin erforderlich.