<?php
// ============================================================
//  Kostenklar – Einstellungen
//  Diese Datei NIEMALS öffentlich teilen (enthält deinen Schlüssel).
// ============================================================
return [
    // Dein Anthropic API-Schlüssel (https://console.anthropic.com → API Keys)
    'api_key' => 'HIER_DEINEN_API_SCHLUESSEL_EINTRAGEN',

    // Modelle: genaues Modell für die Rechnungsprüfung, schnelles für "Preis nachschlagen"
    'model_check' => 'claude-sonnet-5-5',
    'model_quick' => 'claude-haiku-4-5-20251001',

    // Schutz vor Missbrauch: maximale Anfragen pro Besucher (IP) und Stunde
    'max_requests_per_hour' => 20,

    // Nur Anfragen von deiner eigenen Domain erlauben (leer lassen = alle erlauben)
    'allowed_origins' => [
        'https://kostenklar.at',
        'https://www.kostenklar.at',
        'https://kostenklar.com',
        'https://www.kostenklar.com',
    ],
];
