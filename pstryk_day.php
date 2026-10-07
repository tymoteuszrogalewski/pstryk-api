#!/usr/bin/env php
<?php
/**
 * pstryk_day.php — pobiera wczoraj, dzis i jutro (ceny na jutro Pstryk publikuje po poludniu).
 * Uzycie: php pstryk_day.php [YYYY-MM-DD]
 * Cron:   15 * * * *  /sciezka/go.sh
 */

require __DIR__ . '/pstryk.inc.php';

$tz = new DateTimeZone(PSTRYK_TZ);
if (isset($argv[1])) {
    $from = $to = $argv[1];
} else {
    $from = (new DateTime('yesterday', $tz))->format('Y-m-d');
    $to   = (new DateTime('tomorrow', $tz))->format('Y-m-d');
}

$db   = pstryk_db();
$rows = pstryk_fetch($from, $to);
if ($rows === false) exit(1);
pstryk_save($db, $rows);
if ($db) echo "$from..$to: " . count($rows) . " godzin\n";
