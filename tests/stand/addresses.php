<?php

/**
 * The form and "more" under other settings of the addresses: the suffix .html, no short addresses, several languages,
 * a site in a folder. Run after drive.php: the package has to be installed, with an article that is published.
 *
 * Usage: php addresses.php <folder of the stand>
 */

require __DIR__ . '/client.php';

$stand = rtrim($argv[1] ?? exit("the folder of the stand\n"), '/\\');
$site  = "$stand/site";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('127.0.0.1', 'stand', 'stand-only-pass-1', 'stand', 33998);
$failed = 0;
$passed = 0;

function check(string $name, mixed $expected, mixed $actual): void
{
    global $failed, $passed;

    if ($expected === $actual) {
        $passed++;

        return;
    }

    $failed++;
    echo "FAIL  $name\n  expected: " . json_encode($expected) . "\n  actual:   " . mb_substr((string) json_encode($actual), 0, 400) . "\n";
}

function run(string $sql): void
{
    global $db;

    $db->query(str_replace('#__', 'st_', $sql));
}

function one(string $sql): mixed
{
    global $db;

    return $db->query(str_replace('#__', 'st_', $sql))->fetch_row()[0] ?? null;
}

$conf    = file_get_contents("$site/configuration.php");
$set     = function (array $values) use ($site, $conf): void {
    $code = $conf;

    foreach ($values as $name => $value) {
        $code = preg_replace('~public \$' . $name . ' = .*?;~', 'public $' . $name . ' = ' . var_export($value, true) . ';', $code, 1, $count);

        if (!$count) {
            throw new RuntimeException("no $name in the configuration");
        }
    }

    file_put_contents("$site/configuration.php", $code);

    // OPcache looks at a changed file after two seconds
    sleep(3);
};
$article = (int) one('SELECT id FROM #__content WHERE state = 1 ORDER BY id DESC LIMIT 1');
$catid   = (int) one("SELECT catid FROM #__content WHERE id = $article");
$user    = (int) one("SELECT id FROM #__users WHERE username = 'anna'");
run("UPDATE #__extensions SET params = '{\"list_limit\":\"2\",\"article_categories\":[$catid]}' WHERE element = 'com_mucronixreviews'");

/** What a browser with and without JavaScript does on the page of the article */
$walk = function (string $name, string $base, string $address, string $root = 'http://127.0.0.1:8097') use ($article, $user): void {
    run('DELETE FROM #__mucronixreviews_reviews');
    run('DELETE FROM #__mucronixreviews_stats');

    for ($i = 1; $i <= 5; $i++) {
        run("INSERT INTO #__mucronixreviews_reviews (context, item_id, username, rating, state, created) VALUES ('com_content.article', $article, 'user$i', 4, 1, '2026-01-0$i')");
    }

    run("INSERT INTO #__mucronixreviews_stats (context, item_id, rating_count, rating_sum, rating_average) VALUES ('com_content.article', $article, 5, 20, 4)");

    $client = new Client($base);
    $client->login('anna');
    $html = $client->get($address);
    check("$name: the page", [200, true], [$client->status, str_contains($html, 'data-mxr-form')]);

    if (!preg_match('~<form action="([^"]*)" method="post" data-mxr-form>(.*?)</form>~s', $html, $m)) {
        return;
    }

    preg_match_all('~<input type="hidden" name="([^"]+)" value="([^"]*)"~', $m[2], $hidden, PREG_SET_ORDER);
    $fields = [];

    foreach ($hidden as [, $key, $value]) {
        $fields[$key] = html_entity_decode($value);
    }

    $action = $root . html_entity_decode($m[1]);
    $json   = fn (string $url): string => $url . (str_contains($url, '?') ? '&' : '?') . 'format=json';

    $client->request('POST', $json($action), $fields + ['rating' => 5]);
    $answer = json_decode($client->body, true);
    check("$name: sent by a script", [200, true, 1], [$client->status, $answer['success'] ?? mb_substr($client->body, 0, 80), (int) one("SELECT COUNT(*) FROM #__mucronixreviews_reviews WHERE user_id = $user")]);

    run('UPDATE #__mucronixreviews_reviews SET created = created - INTERVAL 1 MINUTE');
    $client->request('POST', $action, $fields + ['rating' => 3]);
    check("$name: sent by the form", [303, '3'], [$client->status, (string) one("SELECT rating FROM #__mucronixreviews_reviews WHERE user_id = $user")]);
    $client->get($client->location);
    check("$name: back on the page", [200, true], [$client->status, str_contains($client->body, 'data-mxr-form')]);

    preg_match('~data-mxr-url="([^"]+)" data-mxr-offset="(\d+)"~', $client->body, $m);
    $client->request('GET', $json($root . html_entity_decode($m[1] ?? '')) . '&offset=' . ($m[2] ?? ''));
    $answer = json_decode($client->body, true);
    check("$name: more", [200, 2], [$client->status, substr_count($answer['html'] ?? '', 'mxr-review__author')]);

    $guest = new Client($base);
    $html  = $guest->get($address);
    preg_match('~mxr-notice"><a href="([^"]+)"~', $html, $m);
    $guest->get($root . html_entity_decode($m[1] ?? '/x'));
    check("$name: the login of a guest", [200, true], [$guest->status, str_contains($guest->body, 'name="password"')]);
};
$base    = 'http://127.0.0.1:8097/';
$address = "index.php?option=com_content&view=article&id=$article";

try {
    $walk('short addresses', $base, $address);
    $set(['sef_suffix' => true]);
    $walk('suffix', $base, $address);
    $set(['sef_suffix' => true, 'sef' => true]);
    run("INSERT INTO #__menu (menutype, title, alias, path, link, type, published, parent_id, level, component_id, access, language, client_id, img, params, note) SELECT 'mainmenu', 'Art', 'art', 'art', 'index.php?option=com_content&view=article&id=$article', 'component', 1, 1, 1, extension_id, 1, '*', 0, '', '{}', 'mxr' FROM #__extensions WHERE element = 'com_content'");
    run("UPDATE #__menu SET lft = (SELECT x FROM (SELECT MAX(rgt) x FROM #__menu) m), rgt = lft + 1 WHERE note = 'mxr'");
    $walk('a menu item with the suffix', $base, 'index.php/art.html');
    $set(['sef' => false, 'sef_suffix' => false]);
    $walk('no short addresses', $base, $address);

    // Several languages
    $set([]);
    run("UPDATE #__extensions SET enabled = 1 WHERE folder = 'system' AND element = 'languagefilter'");
    run("INSERT IGNORE INTO #__languages (lang_code, title, title_native, sef, image, published, access, ordering, description, metakey, metadesc, sitename, asset_id) VALUES ('ru-RU', 'Russian', 'Russian', 'ru', 'ru_ru', 1, 1, 2, '', '', '', '', 0)");
    run("UPDATE #__languages SET published = 1 WHERE lang_code IN ('en-GB', 'ru-RU')");
    $walk('languages: English', $base, "index.php/en/component/content/article/$article?catid=$catid");
    $client = (new Client($base))->login('anna');
    $html   = $client->get("index.php/ru/component/content/article/$article?catid=$catid");
    preg_match('~<form action="([^"]*)" method="post" data-mxr-form>(.*?)</form>~s', $html, $m);
    check('languages: the page is Russian', [200, true], [$client->status, str_contains($html, 'Изменить свой отзыв') || str_contains($html, 'Оставить отзыв')]);
    preg_match_all('~<input type="hidden" name="([^"]+)" value="([^"]*)"~', $m[2] ?? '', $hidden, PREG_SET_ORDER);
    run('UPDATE #__mucronixreviews_reviews SET created = created - INTERVAL 1 MINUTE, modified = NULL');
    $action = html_entity_decode($m[1] ?? '');
    $client->request('POST', 'http://127.0.0.1:8097' . $action . (str_contains($action, '?') ? '&' : '?') . 'format=json', array_column(array_map(fn ($h) => [$h[1], html_entity_decode($h[2])], $hidden), 1, 0) + ['rating' => 4]);
    $answer = json_decode($client->body, true);
    check('languages: the answer is Russian', [200, 'Спасибо, отзыв опубликован.', true], [$client->status, $answer['message'] ?? null, str_contains($answer['html'] ?? '', 'Изменить свой отзыв')]);
} finally {
    run("UPDATE #__extensions SET enabled = 0 WHERE folder = 'system' AND element = 'languagefilter'");
    run("DELETE FROM #__menu WHERE note = 'mxr'");
    file_put_contents("$site/configuration.php", $conf);
    sleep(3);
}

// A site in a folder: the same files through a router of the web server
$router = "$stand/folder.php";
file_put_contents($router, '<?php
$root = ' . var_export($site, true) . ';
$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
if (!str_starts_with($path, "/shop/")) { http_response_code(404); exit; }
$file = substr($path, 5);
if (is_file($root . $file) && !str_ends_with($file, ".php")) { readfile($root . $file); exit; }
chdir($root);
$_SERVER["SCRIPT_NAME"] = $_SERVER["PHP_SELF"] = "/shop/index.php";
$_SERVER["SCRIPT_FILENAME"] = $root . "/index.php";
require $root . "/index.php";
');
$process = proc_open([PHP_BINARY, '-S', '127.0.0.1:8103', $router], [['pipe', 'r'], ['file', "$stand/folder.log", 'w'], ['file', "$stand/folder.log", 'a']], $pipes);
sleep(2);

try {
    $set(['live_site' => 'http://127.0.0.1:8103/shop']);
    $walk('a folder', 'http://127.0.0.1:8103/shop/', $address, 'http://127.0.0.1:8103');
} finally {
    file_put_contents("$site/configuration.php", $conf);
    proc_terminate($process);
    sleep(3);
}

echo $failed ? "\n$failed failed, $passed passed\n" : "all $passed passed\n";
exit($failed ? 1 : 0);
