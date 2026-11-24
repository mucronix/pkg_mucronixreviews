<?php

/**
 * Runs drive.php once for every mutation, each has to turn a check red. The result, one line each, names the check.
 *
 * Usage: php mutants.php <folder of the stand> [<names>]
 *        php mutants.php <folder of a stand with YOOtheme Pro> --yootheme [<names>]: those of yootheme.php
 */

$yootheme = in_array('--yootheme', $argv, true);
$list     = __DIR__ . ($yootheme ? '/mutations-yootheme.php' : '/mutations.php');
$names    = array_values(array_diff(array_slice($argv, 2), ['--yootheme'])) ?: array_keys(require $list);
$survived = [];
$all      = array_keys(require $list);

foreach ($names as $name) {
    putenv("MUTATE=$name");

    if ($yootheme) {
        $out   = (string) shell_exec('"' . PHP_BINARY . '" "' . __DIR__ . '/yootheme.php" "' . $argv[1] . '" 2>&1');
        $first = preg_match('~^FAIL  (.*)$~m', $out, $m) ? 'caught: ' . $m[1] : 'SURVIVED: ' . trim(substr($out, -200));

        if (!str_starts_with($first, 'caught')) {
            $survived[] = $name;
        }

        echo str_pad($name, 20), $first, "\n";

        continue;
    }

    // The checks of one stage are quicker. What they let through gets the whole run
    $place = array_search($name, $all, true);
    $stage = str_starts_with($name, 'mail-') ? '5' : (str_starts_with($name, 'uninstall') || $place < array_search('list-escape-title', $all, true) ? '' : ($place < array_search('avail-state', $all, true) ? '2' : '3'));

    foreach (array_unique([$stage, '']) as $only) {
        putenv("ONLY=$only");
        $out = (string) shell_exec('"' . PHP_BINARY . '" "' . __DIR__ . '/drive.php" "' . $argv[1] . '" 2>&1');

        if (str_contains($out, 'FAIL  ')) {
            break;
        }
    }

    $first = preg_match('~^FAIL  (.*)$~m', $out, $m) ? 'caught: ' . $m[1] : 'SURVIVED: ' . trim(substr($out, -200));

    if (!str_starts_with($first, 'caught')) {
        $survived[] = $name;
    }

    echo str_pad($name, 20), $first, "\n";
}

echo count($names) - count($survived), ' of ', count($names), " caught\n";
exit($survived ? 1 : 0);
