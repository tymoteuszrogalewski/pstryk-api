#!/usr/bin/env php
<?php
/**
 * pstryk_history.php — pobiera historie miesiacami (np. rok, dwa lata).
 * Uzycie: php pstryk_history.php OD [DO]       np. php pstryk_history.php 2025-01-01
 * DO domyslnie = dzis. Mozna przerwac i uruchomic ponownie — dane sie nadpisza, nic sie nie zdubluje.
 */

require __DIR__ . '/pstryk.inc.php';

if (!isset($argv[1])) {
    fwrite(STDERR, "Uzycie: php pstryk_history.php OD [DO]   (daty YYYY-MM-DD)\n");
    exit(1);
}

$tz   = new DateTimeZone(PSTRYK_TZ);
$from = new DateTime($argv[1], $tz);
$to   = new DateTime($argv[2] ?? 'today', $tz);

$db    = pstryk_db();
$total = 0;
for ($d = clone $from; $d <= $to; $d->modify('first day of next month')) {
    $end = (clone $d)->modify('last day of this month');
    if ($end > $to) $end = clone $to;

    $rows = pstryk_fetch($d->format('Y-m-d'), $end->format('Y-m-d'));
    if ($rows === false) exit(1);
    pstryk_save($db, $rows);
    $total += count($rows);
    if ($db) echo $d->format('Y-m-d') . '..' . $end->format('Y-m-d') . ': ' . count($rows) . " godzin\n";
    sleep(PSTRYK_PAUSE);  // nie obciazamy API
}
if ($db) echo "Gotowe: $total godzin\n";
