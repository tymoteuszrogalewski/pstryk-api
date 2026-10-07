<?php
/**
 * pstryk.inc.php — biblioteka: pobranie godzinowych cen, zuzycia i kosztow z API Pstryk
 * (unified-metrics), zapis do MySQL/MariaDB lub CSV.
 */

$cfg = __DIR__ . '/config.php';
if (!file_exists($cfg)) {
    fwrite(STDERR, "Brak config.php — skopiuj config.example.php do config.php i uzupelnij.\n");
    exit(1);
}
require $cfg;

const PSTRYK_URL = 'https://api.pstryk.pl/integrations/meter-data/unified-metrics/';
const PSTRYK_TZ  = 'Europe/Warsaw';

// Zapytanie do API z ponowieniem: HTTP 0 (siec) i 429 (limit zapytan) zwykle mijaja po chwili.
function pstryk_http($url) {
    for ($try = 1; $try <= 3; $try++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 90,
            CURLOPT_HTTPHEADER     => ['Authorization: ' . PSTRYK_API_KEY, 'User-Agent: pstryk-api/1.0'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200) {
            $data = json_decode($body, true);
            if (isset($data['frames'])) return $data;
        }
        if ($code === 401 || $code === 403) {
            fwrite(STDERR, "Pstryk: HTTP $code — zly klucz API (PSTRYK_API_KEY)\n");
            return false;
        }
        $wait = $code === 429 ? 60 : 5;
        fwrite(STDERR, "Pstryk: HTTP $code (proba $try/3), czekam {$wait}s — " . substr((string)$body, 0, 150) . "\n");
        if ($try < 3) sleep($wait);
    }
    return false;
}

// Pobiera zakres dni (YYYY-MM-DD, czas polski, wlacznie). Zwraca [ts_utc => wiersz] albo false.
function pstryk_fetch($from, $to) {
    $tz    = new DateTimeZone(PSTRYK_TZ);
    $utc   = new DateTimeZone('UTC');
    $start = (new DateTime($from, $tz))->setTimezone($utc)->format('Y-m-d\TH:i:s\Z');
    $end   = (new DateTime($to, $tz))->modify('+1 day')->setTimezone($utc)->format('Y-m-d\TH:i:s\Z');

    $data = pstryk_http(PSTRYK_URL . '?metrics=pricing,meter_values,cost&resolution=hour'
                      . "&window_start=$start&window_end=$end");
    if ($data === false) return false;

    $rows = [];
    foreach ($data['frames'] as $f) {
        $p = $f['metrics']['pricing']      ?? [];
        $m = $f['metrics']['meter_values'] ?? [];
        $c = $f['metrics']['cost']         ?? [];

        // Ceny nieznane (jutro przed publikacja, pusta godzina przy zmianie czasu): API daje 0 / null -> NULL
        $known = ($p['tge_price'] ?? null) !== null && !(($p['full_price'] ?? 0) == 0 && ($p['is_cheap'] ?? null) === null);

        $ts = gmdate('Y-m-d H:i:s', strtotime($f['start']));
        $rows[$ts] = [
            'price_gross'          => $known ? $p['full_price'] ?? null : null,
            'price_tge'            => $known ? $p['tge_price'] ?? null : null,
            'price_prosumer_gross' => $known ? $p['price_prosumer_gross'] ?? null : null,
            'is_cheap'             => $known && isset($p['is_cheap']) ? (int)$p['is_cheap'] : null,
            'is_expensive'         => $known && isset($p['is_expensive']) ? (int)$p['is_expensive'] : null,
            'kwh_import'           => $m['energy_active_import_register'] ?? null,
            'kwh_export'           => $m['energy_active_export_register'] ?? null,
            'cost_import'          => $c['energy_import_cost'] ?? null,
            'cost_energy_net'      => $c['energy_cost_net'] ?? null,
            'value_sold'           => $c['energy_sold_value'] ?? null,
            'cost_balance'         => $c['energy_balance_value'] ?? null,
            'json'                 => json_encode($f['metrics']),
        ];
    }
    ksort($rows);
    return $rows;
}

function pstryk_db() {
    if (DB_NAME === '') return null;  // tryb CSV
    try {
        return new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    } catch (mysqli_sql_exception $e) {
        fwrite(STDERR, "DB: " . $e->getMessage() . "\n");
        exit(1);
    }
}

// Zapis do tabeli pstryk_hourly albo wypis CSV na stdout, gdy brak bazy.
function pstryk_save($db, $rows) {
    $tz = new DateTimeZone(PSTRYK_TZ);
    foreach ($rows as $ts => $r) {
        $local = (new DateTime($ts, new DateTimeZone('UTC')))->setTimezone($tz)->format('Y-m-d H:i:s');
        if (!$db) {
            $vals = $r;
            unset($vals['json']);
            echo "$ts;$local;" . implode(';', array_map(fn($v) => $v === null ? '' : rtrim(rtrim(sprintf('%.6f', $v), '0'), '.'), $vals)) . "\n";
            continue;
        }
        $cols = ['ts_utc' => "'$ts'", 'ts_local' => "'$local'"];
        foreach ($r as $k => $v) {
            $cols[$k] = $v === null ? 'NULL' : ($k === 'json' ? "'" . $db->real_escape_string($v) . "'" : (float)$v);
        }
        $upd = [];
        foreach (array_keys($cols) as $k) if ($k !== 'ts_utc') $upd[] = "`$k` = VALUES(`$k`)";
        $db->query('INSERT INTO pstryk_hourly (`' . implode('`, `', array_keys($cols)) . '`) VALUES (' . implode(', ', $cols) . ')
                    ON DUPLICATE KEY UPDATE ' . implode(', ', $upd));
    }
}
