<?php
// ============================================================
//  Kostenklar – Server-Schnittstelle zur KI
//  Nimmt Anfragen der Website an, fragt Claude und gibt JSON zurück.
//  Der API-Schlüssel bleibt dabei auf dem Server (nie im Browser).
// ============================================================
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function antwort($ok, $extra = [], $status = 200) {
    http_response_code($status);
    echo json_encode(array_merge(['ok' => $ok], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}
function fehler($code, $msg, $status = 400) { antwort(false, ['code' => $code, 'error' => $msg], $status); }

$config = require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fehler('method', 'Nur POST erlaubt.', 405);
if (empty($config['api_key']) || strpos($config['api_key'], 'HIER_') === 0) fehler('config', 'API-Schlüssel fehlt in config.php.', 500);
if (!function_exists('curl_init')) fehler('server', 'Auf dem Server fehlt die PHP-Erweiterung cURL.', 500);

// --- Herkunft prüfen ---
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && !empty($config['allowed_origins']) && !in_array($origin, $config['allowed_origins'], true)) {
    fehler('forbidden', 'Anfrage nicht erlaubt.', 403);
}

// --- Größe & Eingabe ---
$raw = file_get_contents('php://input');
if (strlen($raw) > 20 * 1024 * 1024) fehler('too_large', 'Anfrage zu groß.', 413);
$in = json_decode($raw, true);
if (!is_array($in)) fehler('bad_request', 'Ungültige Anfrage.');

// --- Einfaches Limit pro IP und Stunde ---
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unbekannt';
$dir = sys_get_temp_dir() . '/kostenklar_limit';
if (!is_dir($dir)) @mkdir($dir, 0700, true);
$datei = $dir . '/' . hash('sha256', $ip . date('YmdH'));
$anzahl = (int)@file_get_contents($datei);
if ($anzahl >= (int)$config['max_requests_per_hour']) fehler('rate_limited', 'Zu viele Anfragen. Bitte später erneut versuchen.', 429);
@file_put_contents($datei, (string)($anzahl + 1), LOCK_EX);

function kurz($s, $n) { return mb_substr(trim((string)$s), 0, $n); }

$mode   = $in['mode'] ?? '';
$region = kurz($in['region'] ?? '', 60);

if ($mode === 'pruefen') {
    $kat  = kurz($in['kat'] ?? '', 60);
    $text = kurz($in['text'] ?? '', 12000);
    $bilder = [];
    foreach (array_slice(is_array($in['images'] ?? null) ? $in['images'] : [], 0, 3) as $img) {
        $typ = $img['media_type'] ?? '';
        $data = $img['data'] ?? '';
        if (!in_array($typ, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) continue;
        if (!is_string($data) || strlen($data) > 6 * 1024 * 1024 || base64_decode($data, true) === false) fehler('too_large', 'Ein Bild ist zu groß oder beschädigt.', 413);
        $bilder[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $typ, 'data' => $data]];
    }
    if (!$bilder && mb_strlen($text) < 8) fehler('bad_request', 'Bitte Bild, PDF oder Text mitschicken.');

    $hatBild = count($bilder) > 0;
    $prompt = implode("\n", array_filter([
        'Du bist ein unabhängiger, ehrlicher Preisprüfer für Verbraucher im deutschsprachigen Raum.',
        "Kategorie: $kat. Region: $region.",
        $hatBild ? 'In den Bildern ist eine Rechnung oder ein Kostenvoranschlag. Lies alle Positionen mit Beträgen aus.' : 'Unten stehen die Positionen einer Rechnung oder eines Kostenvoranschlags.',
        $text !== '' ? "Text aus dem Dokument bzw. vom Nutzer:\n\"\"\"\n$text\n\"\"\"" : '',
        'Regeln:',
        '- Ignoriere persönliche Daten (Namen, Adressen, Telefonnummern, Kontonummern, Firmennamen) und gib sie nirgends wieder.',
        '- Schätze für jede Position eine realistische übliche Preisspanne in Euro brutto für diese Region (Stand 2026), basierend auf deinem Marktwissen. Berücksichtige Mengen, Stunden und Stundensätze.',
        '- Bewertung je Position: "guenstig" (unter Spanne), "fair" (in Spanne), "etwas_hoch" (bis ca. 20 % darüber), "zu_hoch" (deutlich darüber), "unklar" (nicht beurteilbar).',
        '- Sei fair gegenüber dem Betrieb: nenne auch gute Gründe für höhere Preise. Keine Panikmache.',
        '- Wenn es keine Rechnung/kein Angebot ist oder nichts lesbar ist, setze ist_rechnung auf false und erkläre es in fazit.',
        '- Alle Texte auf Deutsch, kurz und verständlich, Du-Form.',
        'Antworte NUR mit diesem JSON, ohne Text davor oder danach:',
        '{"ist_rechnung":true,"leistung":"kurze Bezeichnung","positionen":[{"bezeichnung":"...","betrag":0,"ueblich_von":0,"ueblich_bis":0,"bewertung":"fair","kommentar":"ein Satz"}],"summe":0,"fair_von":0,"fair_bis":0,"gesamt_bewertung":"fair","fazit":"1-2 Sätze","tipps":["konkreter Tipp"],"nachricht":"höfliche, kurze Nachricht an den Betrieb mit Bitte um Erklärung oder Anpassung der auffälligen Positionen, ohne Namen"}',
    ]));
    $content = array_merge($bilder, [['type' => 'text', 'text' => $prompt]]);
    $model = $config['model_check'];
    $maxTokens = 4000;

} elseif ($mode === 'preis') {
    $q = kurz($in['q'] ?? '', 200);
    if (mb_strlen($q) < 3) fehler('bad_request', 'Bitte eine Leistung eingeben.');
    $prompt = "Du bist ein ehrlicher Preisexperte für Verbraucher. Schätze den üblichen Gesamtpreis (Euro brutto, Stand 2026) für folgende Leistung in der Region $region: \"$q\".\n"
        . "Wenn die Anfrage keine kaufbare Leistung ist, setze von und bis auf 0 und erkläre es in hinweis.\n"
        . 'Antworte NUR mit JSON: {"leistung":"saubere Bezeichnung","von":0,"bis":0,"hinweis":"1 Satz, was im Preis typischerweise enthalten ist","preistreiber":["max 4 kurze Punkte"],"spartipps":["max 3 kurze Punkte"]}. Texte auf Deutsch, Du-Form.';
    $content = [['type' => 'text', 'text' => $prompt]];
    $model = $config['model_quick'];
    $maxTokens = 800;

} else {
    fehler('bad_request', 'Unbekannte Anfrage.');
}

// --- Claude fragen ---
$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 150,
    CURLOPT_HTTPHEADER => [
        'content-type: application/json',
        'x-api-key: ' . $config['api_key'],
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'model' => $model,
        'max_tokens' => $maxTokens,
        'messages' => [['role' => 'user', 'content' => $content]],
    ], JSON_UNESCAPED_UNICODE),
]);
$res = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($res === false) fehler('upstream_error', 'KI nicht erreichbar: ' . $curlErr, 502);
$j = json_decode($res, true);
if ($status === 429 || $status === 529) fehler('rate_limited', 'Die KI ist gerade ausgelastet. Bitte gleich nochmal versuchen.', 429);
if ($status === 401) fehler('config', 'Der API-Schlüssel ist ungültig.', 500);
if ($status >= 400 || !is_array($j)) fehler('upstream_error', 'Fehler bei der KI-Anfrage.', 502);

$text = '';
foreach ($j['content'] ?? [] as $block) if (($block['type'] ?? '') === 'text') $text .= $block['text'];

// JSON aus der Antwort holen (auch wenn es in ``` steht)
$clean = trim(preg_replace('/^```(?:json)?|```$/m', '', $text));
$data = json_decode($clean, true);
if (!is_array($data)) {
    $a = strpos($clean, '{'); $b = strrpos($clean, '}');
    if ($a !== false && $b !== false) $data = json_decode(substr($clean, $a, $b - $a + 1), true);
}
if (!is_array($data)) fehler('invalid_json', 'Die Antwort konnte nicht gelesen werden.', 502);

antwort(true, ['data' => $data]);
