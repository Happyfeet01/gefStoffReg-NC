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
  - **Kataster:** nur Produkte mit `hazardous=true`, deren Gefahrenkennzeichnung
    und gegebenenfalls SDB. Keine Mengen/Lagerorte.
  - **Lager:** alle Produkte, Kategorie, Materialart und Bestand je Lagerort.
    Keine Gefahrstoffdokumente oder SDB-Links.
  - **Beide:** alle Produkte; Bestände sowie Gefahrstoffangaben und SDB zu
    tatsächlich als Gefahrstoff markierten Produkten.
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
   Produkte zeigen, keine Bestandsmengen. Lager-Link darf Lagerbestände anzeigen,
   aber keinerlei SDB-PDF oder SDB-URL. „Beide“ muss der Kombination entsprechen.
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
