# Kostenklar – so bringst du die Website online

## Was in diesem Ordner ist

- **index.html** – die komplette Website (Design, Upload, PDF-Lesen, Ergebnisanzeige)
- **api.php** – das Server-Skript, das die KI fragt. Dein API-Schlüssel bleibt hier auf dem Server und ist für Besucher nie sichtbar.
- **config.php** – deine Einstellungen (API-Schlüssel, Modelle, Limits)
- **.htaccess** – schützt config.php vor direktem Aufruf (die Datei beginnt mit einem Punkt und ist evtl. versteckt)

## In 5 Schritten online

1. **Webspace mit PHP besorgen.** Fast jeder günstige Hoster geht (z. B. World4You, easyname, Hostinger, IONOS). Wichtig: PHP 7.4 oder neuer mit cURL – das ist praktisch überall Standard.
2. **API-Schlüssel holen.** Auf https://console.anthropic.com registrieren, Zahlungsmittel hinterlegen und unter „API Keys“ einen Schlüssel erstellen. Setz dort gleich ein **monatliches Ausgabenlimit**, damit dich nie eine böse Überraschung erwartet.
3. **config.php öffnen** und den Schlüssel bei `api_key` eintragen. Bei `allowed_origins` deine echten Domains eintragen.
4. **Alle vier Dateien hochladen** (per FTP oder Dateimanager deines Hosters) in den Hauptordner deiner Domain. Die `.htaccess` nicht vergessen.
5. **Fertig.** kostenklar.at aufrufen und ein Angebot testen.

Hinweis: Wenn du index.html einfach per Doppelklick am eigenen PC öffnest, funktioniert die KI nicht – sie braucht den Server mit api.php.

## Was es kostet

Jede Prüfung ist eine Anfrage an die KI und kostet je nach Modell und Bildgröße ein paar Cent; „Preis nachschlagen“ nutzt ein schnelles, deutlich günstigeres Modell. Aktuelle Preise: https://www.anthropic.com/pricing. Rechne aus, was eine Prüfung kostet und was ein Besucher an Werbung bringt – das ist die wichtigste Zahl deines Geschäftsmodells.

Wenn eine Prüfung zu teuer wird, kannst du in config.php bei `model_check` auch das günstigere Modell eintragen.

## Schutz vor Missbrauch

- Pro Besucher sind standardmäßig 20 Anfragen pro Stunde erlaubt (`max_requests_per_hour`).
- Nur deine eigene Domain darf die Schnittstelle nutzen (`allowed_origins`).
- Zusätzlich schützt dich das Ausgabenlimit in der Anthropic-Konsole.

## Werbung einbauen

Die grauen „Anzeige“-Kästen in index.html sind Platzhalter. Wenn du z. B. bei Google AdSense angenommen wirst, ersetzt du sie durch den Anzeigen-Code, den du dort bekommst. AdSense verlangt meist eine Website mit eigenem Inhalt, Impressum und Datenschutzerklärung.

## Rechtliches (wichtig in Österreich/EU)

- **Impressum** ist Pflicht (Links im Footer zeigen derzeit nur auf die Startseite).
- **Datenschutzerklärung** ist Pflicht. Darin muss stehen, dass hochgeladene Dokumente zur Auswertung an Anthropic (USA) übermittelt werden, sowie welche Dienste eingebunden sind (Google Fonts, cdnjs für den PDF-Leser, später Werbung).
- Für Werbung mit Tracking brauchst du ein **Cookie-Banner** mit Einwilligung.
- Tipp: Google Fonts und den PDF-Leser kannst du später auch selbst hosten, dann fallen diese beiden Drittanbieter weg.

Lass Impressum und Datenschutz am besten von einem Generator (z. B. WKO-Vorlagen) oder einer Fachperson erstellen – ich bin kein Anwalt.
