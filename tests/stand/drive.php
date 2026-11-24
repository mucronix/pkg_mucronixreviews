<?php

/**
 * End-to-end checks of the package on a throwaway Joomla, see README.md. Never touches another site or database of the machine.
 *
 * Usage: php drive.php <folder of the stand> [<package>]
 */

require __DIR__ . '/client.php';

$stand   = rtrim($argv[1] ?? exit("the folder of the stand\n"), '/\\');
$site    = "$stand/site";
$package = $argv[2] ?? glob(__DIR__ . '/../../build/pkg_mucronixreviews-*.zip')[0];
$mutate  = getenv('MUTATE') ?: '';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('127.0.0.1', 'stand', 'stand-only-pass-1', 'stand', 33998);
$db->set_charset('utf8mb4');

$failed = 0;
$passed = 0;
$mutating = false;

function check(string $name, mixed $expected, mixed $actual): void
{
    global $failed, $passed, $mutate;

    if ($expected === $actual) {
        $passed++;

        return;
    }

    $failed++;
    echo "FAIL  $name\n  expected: " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n  actual:   " . mb_substr((string) json_encode($actual, JSON_UNESCAPED_UNICODE), 0, 300) . "\n";

    if ($mutate !== '') {
        exit(1);
    }
}

function rows(string $sql): array
{
    global $db;

    return $db->query(str_replace('#__', 'st_', $sql))->fetch_all(MYSQLI_ASSOC);
}

function run(string $sql): void
{
    global $db;

    $db->query(str_replace('#__', 'st_', $sql));
}

function one(string $sql): mixed
{
    return array_values(rows($sql)[0] ?? [null])[0];
}

function cli(string $command): string
{
    global $site;

    return (string) shell_exec('"' . PHP_BINARY . '" "' . $site . '/cli/joomla.php" ' . $command . ' --no-interaction 2>&1');
}

function install(string $zip): string
{
    $out = cli('extension:install --path="' . realpath($zip) . '"');

    breakCopy();

    return $out;
}

/** Breaks one thing in the installed copy, after every installation: see mutants.php. Not before the first checks are done */
function breakCopy(): void
{
    global $site, $mutate, $mutating;

    if (!$mutating) {
        return;
    }

    [$file, $from, $to] = (require __DIR__ . '/mutations.php')[$mutate];
    $path = "$site/$file";
    $code = file_get_contents($path);

    // Not a file of what was just installed
    if (str_contains($code, $to) && !str_contains($code, $from)) {
        return;
    }

    if (substr_count($code, $from) !== 1) {
        exit("mutation $mutate does not apply\n");
    }

    file_put_contents($path, str_replace($from, $to, $code));
}

function remove(): string
{
    $id = one("SELECT extension_id FROM #__extensions WHERE type = 'package' AND element = 'pkg_mucronixreviews'");

    return $id ? (string) shell_exec('echo yes| "' . PHP_BINARY . '" "' . $GLOBALS['site'] . '/cli/joomla.php" extension:remove ' . $id . ' 2>&1') : '';
}

function quiet(string $name, string $output): void
{
    check("$name: no PHP message", 0, preg_match('~\b(Warning|Notice|Deprecated|Fatal error)\b(?!s)[: ]~', $output) && !str_contains($output, '[OK]') ? 1 : 0);
}

function options(array $options): void
{
    global $db;

    // What plugin() has set for the articles stays
    $json = $db->real_escape_string(json_encode((object) ($options + ($GLOBALS['articleOptions'] ?? []))));
    run("UPDATE #__extensions SET params = '$json' WHERE element = 'com_mucronixreviews' AND type = 'component'");
}

/** Puts all the reviews into the past: the pause between two reviews is over */
function rest(): void
{
    run('UPDATE #__mucronixreviews_reviews SET created = created - INTERVAL 10 SECOND, modified = modified - INTERVAL 10 SECOND');
}

function wipe(): void
{
    run('DELETE FROM #__mucronixreviews_reviews');
    run('DELETE FROM #__mucronixreviews_stats');
}

function user(string $name, int $group): int
{
    global $db;

    $hash = password_hash('stand-only-pass-1', PASSWORD_BCRYPT);
    run("INSERT INTO #__users (name, username, email, password, block, sendEmail, registerDate, params) VALUES ('Real $name', '$name', '$name@stand.invalid', '$hash', 0, 0, NOW(), '{}')");
    $id = (int) $db->insert_id;
    run("INSERT INTO #__user_usergroup_map (user_id, group_id) VALUES ($id, $group)");

    return $id;
}

function tables(): array
{
    return array_column(rows("SHOW TABLES LIKE 'st_mucronixreviews%'"), 'Tables_in_stand (st_mucronixreviews%)');
}

function stats(int $item = 1, string $context = 'com_test.item'): ?string
{
    $row = rows("SELECT rating_count, text_count, rating_sum, rating_average, rating_1, rating_2, rating_3, rating_4, rating_5 FROM #__mucronixreviews_stats WHERE context = '$context' AND item_id = $item")[0] ?? null;

    return $row ? implode(' ', $row) : null;
}

/** Sends an action of the list of the administrator */
function action(Client $client, string $task, array $ids, bool $token = true): int
{
    $client->get('administrator/index.php?option=com_mucronixreviews&view=reviews');
    $client->request('POST', 'administrator/index.php?option=com_mucronixreviews&view=reviews', ['task' => "reviews.$task", 'boxchecked' => count($ids), 'cid' => $ids] + ($token ? [$client->token => 1] : []));

    return $client->status;
}

/** Runs code in the site application of the stand, returns what it prints */
function inside(string $code): string
{
    global $site;

    $file = tempnam($GLOBALS['stand'], 'mxr');
    file_put_contents($file, '<?php define("_JEXEC", 1); define("JPATH_BASE", ' . var_export(realpath($site . '/administrator'), true) . ');
        $_SERVER["HTTP_HOST"] = "127.0.0.1:8097"; $_SERVER["REQUEST_URI"] = "/administrator/index.php"; $_SERVER["SCRIPT_NAME"] = "/administrator/index.php"; $_SERVER["REQUEST_METHOD"] = "GET";
        require JPATH_BASE . "/includes/defines.php"; require JPATH_BASE . "/includes/framework.php";
        $container = Joomla\CMS\Factory::getContainer();
        $container->alias("session.web", "session.web.administrator")->alias("session", "session.web.administrator")->alias("JSession", "session.web.administrator")
            ->alias(Joomla\CMS\Session\Session::class, "session.web.administrator")->alias(Joomla\Session\Session::class, "session.web.administrator")->alias(Joomla\Session\SessionInterface::class, "session.web.administrator");
        $app = $container->get(Joomla\CMS\Application\AdministratorApplication::class); Joomla\CMS\Factory::$application = $app; $app->createExtensionNamespaceMap();
        $app->loadIdentity($container->get(Joomla\CMS\User\UserFactoryInterface::class)->loadUserByUsername("standadmin"));
        Joomla\CMS\Plugin\PluginHelper::importPlugin("system", null, true, $app->getDispatcher());
        $service = $app->bootComponent("com_mucronixreviews")->getReviewService();
        ' . $code);
    $out = (string) shell_exec('"' . PHP_BINARY . '" -d display_errors=1 "' . $file . '" 2>&1');
    unlink($file);

    return $out;
}

// ---------------------------------------------------------------- what is left of an earlier run
// A run that stopped in the checks of the cache has left it on, and Joomla would keep the old options
$conf = file_get_contents("$site/configuration.php");
$off  = preg_replace('~public \$caching = .*?;~', 'public $caching = 0;', $conf);

if ($off !== $conf) {
    file_put_contents("$site/configuration.php", $off);
    sleep(3);
}

// The mail of the stand goes to smtp-sink.php: a file for every message in <stand>/mail, nothing leaves the machine
$mailConf = ['mailonline' => true, 'mailer' => 'smtp', 'smtphost' => '127.0.0.1', 'smtpport' => 2526, 'smtpauth' => false, 'smtpsecure' => 'none'];
$conf     = file_get_contents("$site/configuration.php");
$mailed   = $conf;

foreach ($mailConf as $name => $value) {
    $mailed = preg_replace('~public \$' . $name . ' = .*?;~', 'public $' . $name . ' = ' . var_export($value, true) . ';', $mailed);
}

// The category of the package logged, as an administrator can choose in the Global Configuration
if (!str_contains($mailed, 'public $log_categories')) {
    $mailed = preg_replace('~}\s*$~', "\tpublic \$log_categories = 'com_mucronixreviews';\n\tpublic \$log_priorities = ['all'];\n\tpublic \$log_category_mode = 0;\n}\n", $mailed);
}

if ($mailed !== $conf) {
    file_put_contents("$site/configuration.php", $mailed);
    sleep(3);
}

@mkdir("$stand/mail");
@unlink("$stand/mail/refuse");
$sinkProcess = proc_open([PHP_BINARY, __DIR__ . '/smtp-sink.php', "$stand/mail", '2526'], [['file', 'NUL', 'r'], ['file', 'NUL', 'w'], ['file', 'NUL', 'w']], $sinkPipes);
register_shutdown_function(fn () => proc_terminate($GLOBALS['sinkProcess']));

@unlink("$site/templates/cassiopeia/html/layouts/com_mucronixreviews/summary.php");
remove();
run('DROP TABLE IF EXISTS #__mucronixreviews_reviews');
run('DROP TABLE IF EXISTS #__mucronixreviews_stats');
run('DROP TABLE IF EXISTS #__mucronixreviews_away');
run("DELETE FROM #__users WHERE username <> 'standadmin'");
run('DELETE FROM #__user_usergroup_map WHERE user_id NOT IN (SELECT id FROM #__users)');
run('DELETE FROM #__session');

// Nothing is broken yet while the older versions are installed: no need to repeat this for every mutation
if ($mutate === '') {
    require __DIR__ . '/updates.inc.php';
}

// ---------------------------------------------------------------- installation
$out = install($package);
check('install: ok', true, str_contains($out, '[OK]'));
quiet('install', $out);
check('install: tables', ['st_mucronixreviews_reviews', 'st_mucronixreviews_stats'], tables());
check('install: plugins enabled', ['1', '1'], array_map('strval', array_column(rows("SELECT enabled FROM #__extensions WHERE element = 'mucronixreviews' AND type = 'plugin'"), 'enabled')));
check('install: schema', '0.6.0', one("SELECT s.version_id FROM #__schemas s JOIN #__extensions e USING (extension_id) WHERE e.element = 'com_mucronixreviews'"));
check('install: menu', '4', (string) one("SELECT COUNT(*) FROM #__menu WHERE link LIKE '%mucronixreviews%'"));
check('install: the templates of the emails', ['com_mucronixreviews.moderation', 'com_mucronixreviews.moderation_more'], array_column(rows("SELECT template_id FROM #__mail_templates WHERE extension = 'com_mucronixreviews' AND language = '' ORDER BY template_id"), 'template_id'));
check('install: parts belong to the package', '3', (string) one("SELECT COUNT(*) FROM #__extensions c JOIN #__extensions p ON c.package_id = p.extension_id WHERE p.element = 'pkg_mucronixreviews'"));

foreach (['site' => "$site/language", 'admin' => "$site/administrator/language"] as $part => $dir) {
    check("install: $part language", true, is_file("$dir/en-GB/com_mucronixreviews.ini"));
}

$out = install($package);
check('install again: ok', true, str_contains($out, '[OK]'));
run("UPDATE #__extensions SET enabled = 0 WHERE element = 'mucronixreviews' AND folder = 'system'");
install($package);
check('update: a disabled plugin stays disabled', '0', (string) one("SELECT enabled FROM #__extensions WHERE element = 'mucronixreviews' AND folder = 'system'"));
run("UPDATE #__extensions SET enabled = 1 WHERE element = 'mucronixreviews' AND folder = 'system'");

// Breaks one thing in the installed copy, see mutants.php
$mutating = $mutate !== '';
breakCopy();

// The type of objects of the stand
$zip = $stand . '/plg_content_mxrtest.zip';
@unlink($zip);
$archive = new ZipArchive();
$archive->open($zip, ZipArchive::CREATE);

foreach (['mxrtest.xml', 'services/provider.php', 'src/Extension/Mxrtest.php'] as $name) {
    $archive->addFile(__DIR__ . '/plg_content_mxrtest/' . $name, $name);
}

$archive->close();
check('test plugin: ok', true, str_contains(install($zip), '[OK]'));
run("UPDATE #__extensions SET enabled = 1 WHERE element = 'mxrtest'");

$ids = [];

foreach (['anna' => 2, 'boris' => 2, 'vera' => 2, 'gleb' => 2, 'dina' => 2, 'avtor' => 3, 'nobody' => 6] as $name => $group) {
    $ids[$name] = user($name, $group);
}

// Only the checks of one stage, for mutants.php
if (getenv('ONLY')) {
    require __DIR__ . '/stage' . (int) getenv('ONLY') . '.inc.php';

    echo $failed ? "\n$failed failed, $passed passed\n" : "all $passed passed\n";
    exit($failed ? 1 : 0);
}

// ---------------------------------------------------------------- the checks of saveReview
$anna = (new Client())->login('anna');

[$status, $answer] = $anna->review([]);
check('first review: status', 200, $status);
check('first review: answer', ['success' => true, 'published' => true, 'created' => true, 'message' => 'Thank you, your review is published.', 'stats' => ['average' => 5, 'ratings' => 1, 'texts' => 0]], array_diff_key($answer, ['html' => 1]));
check('first review: row', [['context' => 'com_test.item', 'item_id' => '1', 'user_id' => (string) $ids['anna'], 'username' => 'anna', 'rating' => '5', 'text' => null, 'state' => '1', 'modified' => null]],
    rows('SELECT context, item_id, user_id, username, rating, text, state, modified FROM #__mucronixreviews_reviews'));
check('no title column', '0', (string) one("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'st_mucronixreviews_reviews' AND COLUMN_NAME = 'title'"));
check('first review: stats', '1 0 5 5.00 0 0 0 0 1', stats());
check('created is UTC now', true, abs((int) one('SELECT TIMESTAMPDIFF(SECOND, created, UTC_TIMESTAMP()) FROM #__mucronixreviews_reviews')) < 30);

[$status, $answer] = $anna->review(['rating' => 4]);
check('pause: status', 429, $status);
check('pause: reason', 'flood', $answer['error']);
[$status, $answer] = $anna->review(['rating' => 4, 'item_id' => 2]);
check('pause: another object', 'flood', $answer['error'] ?? null);

rest();
[$status, $answer] = $anna->review(['rating' => 4]);
check('second sending changes', [200, false], [$status, $answer['created']]);
check('second sending: one row', [['rating' => '4', 'state' => '1']], rows('SELECT rating, state FROM #__mucronixreviews_reviews'));
check('second sending: modified', '1', (string) one('SELECT modified IS NOT NULL FROM #__mucronixreviews_reviews'));
check('second sending: stats', '1 0 4 4.00 0 0 0 1 0', stats());

// 1. login and group
$guest = new Client();
$guest->get('index.php?option=com_users&view=login');
[$status, $answer] = $guest->review([]);
check('guest', [401, 'login'], [$status, $answer['error']]);
$nobody = (new Client())->login('nobody');
[$status, $answer] = $nobody->review([]);
check('group not allowed', [403, 'group'], [$status, $answer['error']]);
$avtor = (new Client())->login('avtor');
[$status, $answer] = $avtor->review(['rating' => 3, 'item_id' => 20]);
check('Author is below Registered', 200, $status);
$super = (new Client())->login('standadmin');
[$status, $answer] = $super->review([]);
check('Super Users are not Registered', [403, 'group'], [$status, $answer['error']]);
options(['allowed_groups' => [8]]);
[$status] = $super->review(['item_id' => 14]);
check('Super Users when chosen, and an object for administrators', 200, $status);
[$status, $answer] = $anna->review([]);
check('Registered when not chosen', [403, 'group'], [$status, $answer['error']]);
options([]);

// 2. token
rest();
[$status, $answer] = $anna->review([], []);
check('no token', [403, 'token'], [$status, $answer['error']]);
[$status, $answer] = $anna->review([], ['X-CSRF-Token: ' . str_repeat('a', 32)]);
check('wrong token', [403, 'token'], [$status, $answer['error']]);
[$status] = $anna->review([$anna->token => 1], []);
check('token in the form', 200, $status);
rest();
[$status, $answer] = $anna->review([$anna->token => [1]], []);
check('token as an array', [403, 'token'], [$status, $answer['error']]);
[$status, $answer] = (new Client())->review([]);
check('a new session without a token gets JSON, not a redirect', [401, 'login'], [$status, $answer['error'] ?? null]);
$anna->get('index.php?option=com_mucronixreviews&task=review.save&format=json&context=com_test.item&item_id=1&rating=1&' . $anna->token . '=1');
check('GET is refused', 405, $anna->status);
check('GET changed nothing', '5', (string) one("SELECT rating FROM #__mucronixreviews_reviews WHERE item_id = 1 AND user_id = {$ids['anna']}"));

// 3. the object
foreach (['com_nobody.item' => 'context', '' => 'context', 'com_test.ITEM' => 'context', "com_test.item' OR 1=1" => 'context'] as $context => $reason) {
    [$status, $answer] = $anna->review(['context' => $context]);
    check("context '$context'", [404, $reason], [$status, $answer['error']]);
}

foreach (['13', '14', '1001', '0', '-1', '', 'abc', '1abc', '1.5', ' 1', '4294967296', '99999999999', '01'] as $item) {
    [$status, $answer] = $anna->review(['item_id' => $item]);
    check("object '$item'", [404, 'unavailable'], [$status, $answer['error']]);
}

[$status, $answer] = $anna->review(['item_id' => [1]]);
check('object as an array', [404, 'unavailable'], [$status, $answer['error']]);
[$status, $answer] = $anna->review(['context' => ['com_test.item']]);
check('context as an array', [404, 'context'], [$status, $answer['error']]);
[$status, $answer] = $anna->review(['item_id' => 15]);
check('a broken adapter', [500, 'unavailable_now'], [$status, $answer['error']]);
check('a broken adapter tells nothing', false, str_contains($anna->body, 'secret'));

// 4. the stars
foreach (['0', '6', '-1', '4.5', '', 'abc', '5 ', "5\n", '05', '1e0', ['5']] as $rating) {
    [$status, $answer] = $anna->review(['rating' => $rating]);
    check('stars ' . json_encode($rating), [422, 'rating'], [$status, $answer['error']]);
}

// 5. required fields and lengths
wipe();
$refused = function (array $options, array $fields, string $reason) use ($anna) {
    options($options);
    [$status, $answer] = $anna->review($fields);
    check('refused: ' . json_encode($options) . ' ' . mb_substr(json_encode($fields, JSON_UNESCAPED_UNICODE), 0, 60), [422, $reason], [$status, $answer['error'] ?? null]);
};
$refused(['text_mode' => 'required'], [], 'text_required');
$refused(['text_mode' => 'required'], ['text' => '<p> </p>'], 'text_required');
$refused(['text_mode' => 'required'], ['text' => str_repeat('я', 19)], 'text_short');
$refused(['text_mode' => 'required'], ['text' => str_repeat('<b>я</b>', 19)], 'text_short');
$refused([], ['text' => str_repeat('я', 3001)], 'text_long');
$refused(['text_max' => 10], ['text' => str_repeat('я', 11)], 'text_long');
$refused(['text_max' => 10], ['text' => str_repeat('<b></b>', 30000)], 'text_long');
check('nothing refused was stored', '0', (string) one('SELECT COUNT(*) FROM #__mucronixreviews_reviews'));
check('nothing refused left statistics', '0', (string) one('SELECT COUNT(*) FROM #__mucronixreviews_stats'));

$accepted = function (array $options, array $fields, int $state, ?string $text) use ($anna) {
    wipe();
    options($options);
    [$status] = $anna->review($fields);
    check('accepted: ' . json_encode($options) . ' ' . mb_substr(json_encode($fields, JSON_UNESCAPED_UNICODE), 0, 60), [200, [['state' => (string) $state, 'text' => $text]]], [$status, rows('SELECT state, text FROM #__mucronixreviews_reviews')]);
};
$accepted([], ['text' => str_repeat('😀', 3000)], 0, str_repeat('😀', 3000));
$accepted(['text_max' => 16000], ['text' => str_repeat('😀', 16000)], 0, str_repeat('😀', 16000));
$accepted([], ['text' => 'a'], 0, 'a');
$accepted(['text_mode' => 'required'], ['text' => str_repeat('я', 20)], 0, str_repeat('я', 20));
$accepted(['mode' => 'stars', 'text_mode' => 'required'], ['text' => 'example.com'], 1, null);
$accepted(['text_mode' => 'off'], ['text' => 'x'], 1, null);

// A title, as a form of an older version sends it, is not stored and counts for nothing
$accepted(['moderation' => 'text'], ['title' => 'T'], 1, null);
$accepted(['moderation' => 'none', 'links' => 'moderate'], ['title' => 'me@example.com'], 1, null);
$accepted(['text_mode' => 'required'], ['title' => str_repeat('я', 300), 'text' => str_repeat('я', 20)], 0, str_repeat('я', 20));

// 6. HTML and links
$accepted(['moderation' => 'none'], ['text' => "<script>alert(1)</script> 5 < 10 <3\r\nline"], 1, "alert(1) 5 < 10 <3\nline");
$accepted(['moderation' => 'none'], ['text' => 'a <scr<b>ipt>alert(1)</script> b'], 1, 'a alert(1) b');
$accepted(['moderation' => 'none'], ['text' => "'); DROP TABLE x; --"], 1, "'); DROP TABLE x; --");
$accepted(['moderation' => 'none', 'links' => 'moderate'], ['text' => 'смотри shop.ua'], 0, 'смотри shop.ua');
$accepted(['moderation' => 'none', 'links' => 'moderate'], ['text' => 'me@example.com'], 0, 'me@example.com');
$accepted(['moderation' => 'none', 'links' => 'allow'], ['text' => 'shop.ua'], 1, 'shop.ua');
$accepted(['moderation' => 'none', 'links' => 'moderate'], ['text' => 'т.е. 1.5 и т.д.'], 1, 'т.е. 1.5 и т.д.');
$accepted(['moderation' => 'all'], [], 0, null);
$accepted(['moderation' => 'text'], [], 1, null);
$accepted(['moderation' => 'text'], ['text' => 'T'], 0, 'T');
wipe();
$refused(['links' => 'forbid'], ['text' => 'www.example.zz'], 'links');
$refused(['links' => 'forbid'], ['text' => 'me@example.com'], 'links');
$accepted(['links' => 'forbid'], ['title' => 'example.com'], 1, null);

// Not shown, not counted
wipe();
options([]);
[, $answer] = $anna->review(['text' => 'A text']);
check('pending: answer', [false, 'Thank you, your review will appear after it has been checked.', 0], [$answer['published'], $answer['message'], $answer['stats']['ratings']]);
check('pending: stats', '0 0 0 0.00 0 0 0 0 0', stats());

// Edits
$edit = function (array $options, int $was, array $fields, int $now) use ($anna) {
    wipe();
    options($options + ['links' => 'moderate']);
    run("INSERT INTO #__mucronixreviews_reviews (context, item_id, user_id, username, rating, text, state, created) SELECT 'com_test.item', 1, id, 'old', 5, 'Text', $was, '2026-01-01' FROM #__users WHERE username = 'anna'");
    $anna->review($fields + ['rating' => 3, 'text' => 'Text']);
    $row = rows('SELECT rating, state, username FROM #__mucronixreviews_reviews')[0];
    check('edit: ' . json_encode([$options, $was, $fields], JSON_UNESCAPED_UNICODE), ['3', (string) $now, 'anna', $now === 1 ? '1' : '0'], [$row['rating'], $row['state'], $row['username'], explode(' ', (string) stats())[0]]);
};

foreach (['all', 'text'] as $mode) {
    $edit(['moderation' => $mode], 1, [], 1);
    $edit(['moderation' => $mode], 1, ['title' => 'N'], 1);
    $edit(['moderation' => $mode], 1, ['text' => 'N'], 0);
}

$edit(['moderation' => 'none'], 1, ['text' => 'N'], 1);
$edit(['moderation' => 'none'], 1, ['text' => 'shop.ua'], 0);

foreach ([0, -1, -2, -3] as $was) {
    $edit(['moderation' => 'none'], $was, [], $was);
    $edit(['moderation' => 'none'], $was, ['text' => 'N'], $was === -3 ? -3 : 0);
}

$edit(['mode' => 'stars'], 1, ['text' => ''], 1);
check('stars mode keeps the text', 'Text', one('SELECT text FROM #__mucronixreviews_reviews'));
$edit(['text_mode' => 'off'], 1, ['text' => 'X'], 1);
check('a text that is off is kept', 'Text', one('SELECT text FROM #__mucronixreviews_reviews'));
rest();
options(['allow_edit' => 0]);
[$status, $answer] = $anna->review(['rating' => 1]);
check('editing not allowed', [403, 'edit'], [$status, $answer['error']]);

// 7. the limit
wipe();
options(['daily_limit' => 2]);

for ($i = 1; $i <= 2; $i++) {
    [$status] = $anna->review(['item_id' => $i]);
    check("limit: review $i", 200, $status);
    rest();
}

[$status, $answer] = $anna->review(['item_id' => 3]);
check('limit: one too many', [429, 'limit'], [$status, $answer['error']]);
[$status] = $anna->review(['item_id' => 1, 'rating' => 2]);
check('limit: an edit is free', 200, $status);
run('UPDATE #__mucronixreviews_reviews SET created = created - INTERVAL 1 DAY, modified = NULL');
[$status] = $anna->review(['item_id' => 3]);
check('limit: the next day', 200, $status);
rest();
options(['daily_limit' => 0]);
run("INSERT INTO #__mucronixreviews_reviews (context, item_id, user_id, rating, created) SELECT 'com_test.other', n, {$ids['anna']}, 1, UTC_TIMESTAMP() - INTERVAL 1 HOUR FROM (SELECT 1 n UNION SELECT 2 UNION SELECT 3) x");
[$status] = $anna->review(['item_id' => 4]);
check('limit: zero is no limit', 200, $status);

// Defaults in the code: never saved options keep text waiting
wipe();
options([]);
$anna->review(['text' => 'x']);
check('defaults: text waits', '0', (string) one('SELECT state FROM #__mucronixreviews_reviews'));

// ---------------------------------------------------------------- without JavaScript
wipe();
options([]);
$form = fn (array $fields) => $anna->request('POST', 'index.php?option=com_mucronixreviews&task=review.save', $fields + ['context' => 'com_test.item', 'item_id' => 1, 'rating' => 5, $anna->token => 1]);
$form(['return' => base64_encode('http://127.0.0.1:8097/index.php?option=com_users&view=profile&back=1')]);
check('form: back to the page', [303, 'http://127.0.0.1:8097/index.php?option=com_users&view=profile&back=1'], [$anna->status, $anna->location]);
check('form: stored', '1', (string) one('SELECT COUNT(*) FROM #__mucronixreviews_reviews'));
check('form: message', true, str_contains($anna->get('index.php?option=com_users&view=profile'), 'Thank you, your review is published.'));
$form([]);
check('form: refused, back to the object', 'http://127.0.0.1:8097/index.php?item=1', $anna->location);
check('form: refusal is shown', true, str_contains($anna->get('index.php?option=com_users&view=profile'), 'Too fast.'));

foreach (['https://evil.example/', '//evil.example/index.php', 'javascript:alert(1)', 'http://127.0.0.1.evil.example/index.php', "http://127.0.0.1:8097@evil.example/index.php"] as $url) {
    $form(['return' => base64_encode($url)]);
    check("form: not to $url", 'http://127.0.0.1:8097/index.php?item=1', $anna->location);
}

$form(['return' => '%%%', 'context' => 'com_nobody.item']);
check('form: nothing to return to', 'http://127.0.0.1:8097/', $anna->location);
$form(['item_id' => 13]);
check('form: the address of a hidden object is not told', 'http://127.0.0.1:8097/', $anna->location);

foreach (['', '&task=review.display', '&task=save&controller=review', '&view=reviews'] as $query) {
    $anna->get('index.php?option=com_mucronixreviews' . $query);
    check("no page '$query'", 404, $anna->status);
}

// ---------------------------------------------------------------- several requests at once
wipe();
options(['daily_limit' => 10]);
$clients = [];
$bases = array_map(fn ($port) => "http://127.0.0.1:$port/", range(8097, 8102));

$together = function (array $sendings): array {
    $multi = curl_multi_init();
    $handles = [];

    foreach ($sendings as $i => [$client, $base, $fields]) {
        $handles[$i] = $client->handle('POST', $base . 'index.php?option=com_mucronixreviews&task=review.save&format=json', $fields + ['context' => 'com_test.item', 'rating' => 5], ['X-CSRF-Token: ' . $client->token]);
        curl_multi_add_handle($multi, $handles[$i]);
    }

    do {
        curl_multi_exec($multi, $running);
        curl_multi_select($multi, 0.1);
    } while ($running);

    $answers = [];

    foreach ($handles as $handle) {
        $answers[] = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    }

    return $answers;
};

// One user with several sessions: sessions do not queue them, the lock has to
$sessions = [];

for ($i = 0; $i < 6; $i++) {
    $sessions[] = (new Client())->login('boris');
}

for ($round = 1; $round <= 5; $round++) {
    wipe();
    $answers = $together(array_map(fn ($i) => [$sessions[$i], $bases[$i], ['item_id' => 100 + $i]], range(0, 5)));
    sort($answers);
    check("at once, round $round: one user, six objects", [200, 429, 429, 429, 429, 429], $answers);
    check("at once, round $round: one review", '1', (string) one('SELECT COUNT(*) FROM #__mucronixreviews_reviews'));

    wipe();
    $answers = $together(array_map(fn ($i) => [$sessions[$i], $bases[$i], ['item_id' => 100]], range(0, 5)));
    sort($answers);
    check("at once, round $round: one user, one object", [200, 429, 429, 429, 429, 429], $answers);
}

$people = [];

foreach (['anna', 'boris', 'vera', 'gleb', 'dina', 'avtor'] as $name) {
    $people[] = (new Client())->login($name);
}

for ($round = 1; $round <= 5; $round++) {
    wipe();
    $answers = $together(array_map(fn ($i) => [$people[$i], $bases[$i], ['item_id' => 200, 'rating' => 1 + $i % 5]], range(0, 5)));
    check("at once, round $round: six users, one object", array_fill(0, 6, 200), $answers);
    check("at once, round $round: nobody is lost", '6 0 16 2.67 2 1 1 1 1', stats(200));
    wipe();
    $answers = $together(array_map(fn ($i) => [$people[$i], $bases[$i], ['item_id' => 300 + $i]], range(0, 5)));
    check("at once, round $round: six users, six new objects", array_fill(0, 6, 200), $answers);
}

// ---------------------------------------------------------------- the functions of the service
wipe();
options([]);
$n = 0;

foreach (['anna' => [5, 'Best', 1], 'boris' => [4, '', 1], 'vera' => [4, 'Good', 1], 'gleb' => [1, 'Hidden', 0], 'dina' => [5, 'Spam', -3]] as $name => [$rating, $text, $state]) {
    $n++;
    $text = $text === '' ? 'NULL' : "'$text'";
    run("INSERT INTO #__mucronixreviews_reviews (id, context, item_id, user_id, username, rating, text, state, created) VALUES ($n, 'com_test.item', 1, {$ids[$name]}, 'stored-$name', $rating, $text, $state, '2026-01-0$n')");
}

run("INSERT INTO #__mucronixreviews_stats (context, item_id, rating_count) VALUES ('com_test.item', 999, 7)");
check('recalculateAll', '1', inside('echo $service->recalculateAll();'));
check('recalculateAll: stats', '3 2 13 4.33 0 0 0 2 1', stats());
check('recalculateAll: statistics of nothing go', null, stats(999));
check('getStats', '{"context":"com_test.item","item_id":1,"rating_count":3,"text_count":2,"rating_sum":13,"rating_1":0,"rating_2":0,"rating_3":0,"rating_4":2,"rating_5":1,"rating_average":4.33}', inside('echo json_encode($service->getStats("com_test.item", 1));'));
check('getStats of nothing', '0/0', inside('$s = $service->getStats("com_test.item", 77); echo $s->rating_count . "/" . $s->rating_average;'));
$list = fn (string $options) => inside('echo implode(" ", array_map(fn($r) => $r->author, $service->getReviews("com_test.item", 1, ' . $options . ')));');
check('getReviews', 'vera boris anna', $list('[]'));
check('getReviews: highest', 'anna vera boris', $list('["order" => "highest"]'));
check('getReviews: lowest', 'vera boris anna', $list('["order" => "lowest"]'));
check('getReviews: page', 'boris', $list('["limit" => 1, "offset" => 1]'));
check('getReviews: with text', 'vera anna', $list('["withTextOnly" => true]'));
check('getReviews: an odd order', 'vera boris anna', $list('["order" => "id; DROP"]'));
check('getUserReview: any state', '0', inside('echo $service->getUserReview("com_test.item", 1, ' . $ids['gleb'] . ')->state;'));
check('getUserReview: nothing', 'NULL', inside('var_export($service->getUserReview("com_test.item", 1, ' . $ids['avtor'] . '));'));
check('getUserReview: a guest', 'NULL', inside('var_export($service->getUserReview("com_test.item", 1, 0));'));
check('setState', '2', inside('echo $service->setState([1, "2", 2, 0, -5, 777], -1);'));
check('setState: stats', '1 1 4 4.00 0 0 0 1 0', stats());
check('setState: back', '2 1 8 4.00 0 0 0 2 0', inside('$service->setState([2], 1);') . stats());
check('setState: no such state', 'refused', inside('try { $service->setState([2], 2); } catch (InvalidArgumentException $e) { echo "refused"; }'));
check('setState: nothing', '0', inside('echo $service->setState([], 1);'));
check('deleteReviews', '1', inside('echo $service->deleteReviews([3]);'));
check('deleteReviews: stats', '1 0 4 4.00 0 0 0 1 0', stats());
run("INSERT INTO #__mucronixreviews_reviews (context, item_id, user_id, rating, state, created) VALUES ('com_test.other', 1, {$ids['anna']}, 2, 1, NOW())");
inside('$service->recalculate("com_test.other", 1);');
check('recalculate', '1 0 2 2.00 0 1 0 0 0', stats(1, 'com_test.other'));
check('deleteByItem', '4', inside('echo $service->deleteByItem("com_test.item", 1);'));
check('deleteByItem: no statistics', null, stats());
check('deleteByItem: another type stays', '1 0 2 2.00 0 1 0 0 0', stats(1, 'com_test.other'));
check('saveReview for somebody else', 'login 401', inside('try { $service->saveReview("com_test.item", 1, ' . $ids['anna'] . ', 5); } catch (Mucronix\Component\MucronixReviews\Administrator\Exception\ReviewException $e) { echo $e->getReason(), " ", $e->getCode(); }'));
options(['allowed_groups' => [8]]);
check('saveReview without a token', 'token 403', inside('try { $service->saveReview("com_test.item", 1, (int) $app->getIdentity()->id, 5); } catch (Mucronix\Component\MucronixReviews\Administrator\Exception\ReviewException $e) { echo $e->getReason(), " ", $e->getCode(); }'));
options([]);

// A failure in the middle undoes everything
wipe();
run('ALTER TABLE #__mucronixreviews_stats RENAME COLUMN rating_5 TO rating_x');
[$status, $answer] = $anna->review([]);
check('broken in the middle: answer', [500, 'unavailable_now'], [$status, $answer['error'] ?? null]);
check('broken in the middle: nothing is stored', '0', (string) one('SELECT COUNT(*) FROM #__mucronixreviews_reviews'));
run('ALTER TABLE #__mucronixreviews_stats RENAME COLUMN rating_x TO rating_5');

// ---------------------------------------------------------------- a user is deleted
$admin = (new Client())->loginAdmin();
$fill = function (array $names) use (&$ids) {
    wipe();

    foreach ($names as $name) {
        run("INSERT INTO #__mucronixreviews_reviews (context, item_id, user_id, username, rating, state, created) VALUES ('com_test.item', 1, {$ids[$name]}, '$name', 4, 1, NOW()), ('com_test.item', 2, {$ids[$name]}, '$name', 2, 0, NOW())");
    }

    inside('$service->recalculateAll();');
};
$deleteInAdmin = function (string $name) use ($admin, &$ids) {
    $admin->get('administrator/index.php?option=com_users&view=users');
    $admin->request('POST', 'administrator/index.php?option=com_users&view=users', ['task' => 'users.delete', 'boxchecked' => 1, 'cid' => [$ids[$name]], $admin->token => 1]);
    check("$name is gone", '0', (string) one("SELECT COUNT(*) FROM #__users WHERE username = '$name'"));
};

$fill(['vera', 'gleb']);
$deleteInAdmin('vera');
check('deleted by the administrator, kept', [['user_id' => null, 'username' => 'vera', 'state' => '1'], ['user_id' => null, 'username' => 'vera', 'state' => '0']], rows("SELECT user_id, username, state FROM #__mucronixreviews_reviews WHERE username = 'vera' ORDER BY id"));
check('kept: still counted', '2 0 8 4.00 0 0 0 2 0', stats());
check('kept: others untouched', '2', (string) one("SELECT COUNT(*) FROM #__mucronixreviews_reviews WHERE user_id = {$ids['gleb']}"));
check('kept: shown under the username', 'gleb vera', inside('$a = array_map(fn($r) => $r->author, $service->getReviews("com_test.item", 1)); sort($a); echo implode(" ", $a);'));
run("UPDATE #__users SET username = 'gleb-new' WHERE id = {$ids['gleb']}");
check('a renamed account is shown under its present username', 'gleb-new vera', inside('$a = array_map(fn($r) => $r->author, $service->getReviews("com_test.item", 1)); sort($a); echo implode(" ", $a);'));

options(['user_delete' => 'delete']);
run("UPDATE #__users SET username = 'gleb' WHERE id = {$ids['gleb']}");
$deleteInAdmin('gleb');
check('deleted by the administrator, deleted', '0', (string) one("SELECT COUNT(*) FROM #__mucronixreviews_reviews WHERE username = 'gleb'"));
check('deleted: counted anew', '1 0 4 4.00 0 0 0 1 0', stats());

$ids['vera'] = user('vera', 2);
$ids['gleb'] = user('gleb', 2);
options([]);
$fill(['dina']);
inside('Joomla\CMS\User\User::getInstance(' . $ids['dina'] . ')->delete();');
check('deleted by User::delete(), kept', '2', (string) one("SELECT COUNT(*) FROM #__mucronixreviews_reviews WHERE user_id IS NULL AND username = 'dina'"));
options(['user_delete' => 'delete']);
$fill(['vera']);
inside('Joomla\CMS\User\User::getInstance(' . $ids['vera'] . ')->delete();');
check('deleted by User::delete(), deleted', '0', (string) one('SELECT COUNT(*) FROM #__mucronixreviews_reviews'));
$fill(['gleb']);
cli('user:delete --username=gleb');
check('deleted on the command line', ['0', '0'], [(string) one("SELECT COUNT(*) FROM #__users WHERE username = 'gleb'"), (string) one('SELECT COUNT(*) FROM #__mucronixreviews_reviews')]);

$ids['gleb'] = user('gleb', 2);
$fill(['gleb']);
$failedDelete = 'Joomla\CMS\Factory::getApplication()->getDispatcher()->dispatch("onUserAfterDelete", new Joomla\CMS\Event\User\AfterDeleteEvent("onUserAfterDelete", ["subject" => ["id" => ' . $ids['gleb'] . '], "deletingResult" => false, "errorMessage" => ""]));';
inside($failedDelete);
check('a deletion that failed', '2', (string) one("SELECT COUNT(*) FROM #__mucronixreviews_reviews WHERE user_id = {$ids['gleb']}"));
run("UPDATE #__extensions SET enabled = 0 WHERE element = 'com_mucronixreviews'");
inside('Joomla\CMS\User\User::getInstance(' . $ids['gleb'] . ')->delete();');
check('works while the component is disabled', '0', (string) one('SELECT COUNT(*) FROM #__mucronixreviews_reviews'));
run("UPDATE #__extensions SET enabled = 1 WHERE element = 'com_mucronixreviews'");
run('ALTER TABLE #__mucronixreviews_reviews RENAME TO #__mucronixreviews_away');
$deleteInAdmin('nobody');
check('a failure is told and does not stop the deletion', true, str_contains(html_entity_decode($admin->get('administrator/index.php?option=com_users&view=users')), 'their reviews were not dealt with'));
run('ALTER TABLE #__mucronixreviews_away RENAME TO #__mucronixreviews_reviews');

// ---------------------------------------------------------------- the options
options([]);
$id = one("SELECT extension_id FROM #__extensions WHERE element = 'com_mucronixreviews'");
$page = $admin->get('administrator/index.php?option=com_config&view=component&component=com_mucronixreviews');
check('options: page', 200, $admin->status);
check('options: no untranslated string', 0, preg_match('~COM_MUCRONIXREVIEWS_(?!\w*["\']?:)~', $page));
check('options: in the list of components', true, str_contains($page, 'component=com_mucronixreviews'));
check('options: warning about the data', true, str_contains($page, 'deletes all reviews and ratings for good'));
check('options: types, escaped', [true, false], [str_contains($page, 'Test &lt;b&gt;items'), str_contains($page, 'Test <b>items</b>')]);
check('options: groups default', 1, preg_match('~<option value="2"\s+selected~', $page));
check('options: the tab of the articles', [true, true, true, true, false], [str_contains($page, '>Joomla Articles<'), str_contains($page, 'name="jform[article_categories][]"'), str_contains($page, '<table class="table table-sm">'), str_contains($page, '<code>{mucronixreviews summary id=123}</code>'), str_contains($page, 'name="jform[title_mode]"')]);

$good = ['mode' => 'full', 'text_mode' => 'optional', 'text_min' => '20', 'text_max' => '3000', 'allowed_groups' => ['2'], 'allow_edit' => '1', 'user_delete' => 'keep', 'moderation' => 'text', 'links' => 'moderate', 'daily_limit' => '10', 'rounding' => 'half', 'show_date' => '1', 'date_format' => 'DATE_FORMAT_LC3', 'list_limit' => '10', 'list_order' => 'newest', 'keep_data' => '1', 'notify' => '1', 'notify_groups' => ['8'], 'notify_emails' => '', 'notify_hourly' => '20'];
$save = function (array $change) use ($admin, $good, $id): array {
    options([]);
    $admin->get('administrator/index.php?option=com_config&view=component&component=com_mucronixreviews');
    $admin->request('POST', 'administrator/index.php?option=com_config', ['task' => 'component.apply', 'component' => 'com_mucronixreviews', 'id' => $id, 'jform' => array_filter($change + $good, fn ($v) => $v !== null), $admin->token => 1]);
    $body = $admin->get('administrator/index.php?option=com_config&view=component&component=com_mucronixreviews');

    return [one("SELECT params FROM #__extensions WHERE extension_id = $id"), html_entity_decode($body)];
};

[$params, $body] = $save(['schema_contexts' => ['com_test.item'], 'list_limit' => '7', 'text_min' => '0', 'daily_limit' => '0']);
$saved = json_decode($params, true);
check('options: saved', ['com_test.item', '7', '0', '0', [2]], [$saved['schema_contexts'][0] ?? null, (string) ($saved['list_limit'] ?? ''), (string) ($saved['text_min'] ?? ''), (string) ($saved['daily_limit'] ?? ''), $saved['allowed_groups'] ?? null]);
[$params] = $save(['article_categories' => ['2', 'x'], 'article_position' => 'before', 'article_show' => 'summary']);
$saved = json_decode($params, true);
check('options: articles saved', [[2, 0], 'before', 'summary'], [array_map('intval', (array) ($saved['article_categories'] ?? [])), $saved['article_position'] ?? null, $saved['article_show'] ?? null]);

foreach (
    [
        [['text_min' => '-1'], 'Minimum Text Length: no less than 0.'],
        [['text_min' => 'abc'], 'Minimum Text Length: enter a whole number.'],
        [['text_min' => '1.5'], 'Minimum Text Length: enter a whole number.'],
        [['text_max' => '0'], 'Maximum Text Length: no less than 1.'],
        [['text_max' => 'abc'], 'Maximum Text Length: enter a whole number.'],
        [['text_max' => '16001'], 'Maximum Text Length: no more than 16000.'],
        [['text_min' => '3001'], 'The minimum text length is greater than the maximum one.'],
        [['text_min' => '5', 'text_max' => '4', 'mode' => 'stars'], 'The minimum text length is greater than the maximum one.'],
        [['daily_limit' => '-1'], 'Most New Reviews per Day: no less than 0.'],
        [['daily_limit' => 'x'], 'Most New Reviews per Day: enter a whole number.'],
        [['daily_limit' => '99999999999999999999'], 'Most New Reviews per Day: enter a whole number.'],
        [['list_limit' => '0'], 'Reviews per Page: no less than 1.'],
        [['list_limit' => '-3'], 'Reviews per Page: no less than 1.'],
        [['allowed_groups' => null], 'Who Can Rate'],
        [['allowed_groups' => ['777']], 'Who Can Rate: there is no such group.'],
        [['mode' => 'x'], 'Mode'],
        [['moderation' => 'x'], 'Moderation'],
        [['article_position' => 'x'], 'Position'],
        [['article_show' => 'x'], 'What to Show'],
        [['text_max' => ['5']], 'Maximum Text Length'],
        [['notify_groups' => null], 'Notify Moderators is on, but there is nobody to send the email to'],
        [['notify_groups' => null, 'notify_emails' => " \n, "], 'Notify Moderators is on, but there is nobody to send the email to'],
        [['notify_emails' => 'good@stand.invalid, bad@'], 'Not an email address: bad@'],
        [['notify_emails' => implode("\n", array_map(fn ($n) => "m$n@stand.invalid", range(1, 11)))], 'Additional Addresses: no more than 10 addresses.'],
        [['notify_groups' => ['777']], 'Recipient Groups: there is no such group.'],
        [['notify_hourly' => '-1'], 'Most Emails per Hour: no less than 0.'],
        [['notify_hourly' => 'x'], 'Most Emails per Hour: enter a whole number.'],
    ] as [$change, $message]
) {
    [$params, $body] = $save($change);
    check('options refused: ' . json_encode($change), ['{}', true], [$params, str_contains($body, $message)]);
}

[$params] = $save(['notify' => '0', 'notify_groups' => null]);
check('options: notifications off with nobody to get them', '0', (string) (json_decode($params, true)['notify'] ?? ''));
[$params] = $save(['notify_groups' => null, 'notify_emails' => "a@stand.invalid\nB@stand.invalid", 'notify_hourly' => '0']);
$saved = json_decode($params, true);
check('options: addresses alone, no limit', ["a@stand.invalid\nB@stand.invalid", '0'], [str_replace("\r", '', (string) ($saved['notify_emails'] ?? '')), (string) ($saved['notify_hourly'] ?? '')]);
$body = $admin->get('administrator/index.php?option=com_config&view=component&component=com_mucronixreviews');
check('options: the fields of the email', [true, true, true, true, 1], [str_contains($body, 'name="jform[notify]"'), str_contains($body, 'name="jform[notify_groups][]"'), str_contains($body, 'name="jform[notify_emails]"'), str_contains($body, 'Receive System Emails'), preg_match('~<option value="8"\s+selected~', ($save([]))[1])]);
[$params] = $save(['text_min' => '']);
check('options: Joomla turns an empty number into the default', '20', (string) (json_decode($params, true)['text_min'] ?? ''));
[$params] = $save(['text_min' => '3000']);
check('options: minimum equal to maximum', '3000', (string) (json_decode($params, true)['text_min'] ?? ''));
options([]);
$admin->get('administrator/index.php?option=com_mucronixreviews');
check('closing the options leads to the list', [200, true], [$admin->status, str_contains($admin->body, 'Mucronix Reviews: Reviews')]);

// A setting of a type whose adapter is disabled survives
options(['schema_contexts' => ['com_gone.item']]);
check('options: an absent type is offered', true, str_contains($admin->get('administrator/index.php?option=com_config&view=component&component=com_mucronixreviews'), 'value="com_gone.item"'));
run("UPDATE #__extensions SET enabled = 0 WHERE element = 'mxrtest'");
options([]);
$body = $admin->get('administrator/index.php?option=com_config&view=component&component=com_mucronixreviews');
check('options: the articles, not chosen', [1, 0], [preg_match('~value="com_content\.article"~', $body), preg_match('~value="com_content\.article"[^>]*checked~', $body)]);
run("UPDATE #__extensions SET enabled = 0 WHERE element = 'mucronixreviews' AND folder = 'content'");
check('options: no types', true, str_contains($admin->get('administrator/index.php?option=com_config&view=component&component=com_mucronixreviews'), 'No types of objects are registered.'));
run("UPDATE #__extensions SET enabled = 1 WHERE element = 'mxrtest'");
run("UPDATE #__extensions SET enabled = 1 WHERE element = 'mucronixreviews' AND folder = 'content'");

require __DIR__ . '/stage2.inc.php';
require __DIR__ . '/stage3.inc.php';
require __DIR__ . '/stage5.inc.php';

// ---------------------------------------------------------------- uninstallation
$wholePackage = one("SELECT extension_id FROM #__extensions WHERE element = 'com_mucronixreviews'");
shell_exec('echo yes| "' . PHP_BINARY . '" "' . $site . '/cli/joomla.php" extension:remove ' . $wholePackage . ' 2>&1');
check('a part alone cannot be uninstalled', '1', (string) one("SELECT COUNT(*) FROM #__extensions WHERE element = 'com_mucronixreviews'"));

wipe();
run("INSERT INTO #__mucronixreviews_reviews (context, item_id, user_id, username, rating, text, state, created) VALUES ('com_test.item', 1, {$ids['anna']}, 'anna', 4, 'Text', 1, NOW())");
inside('$service->recalculateAll();');
$before = [rows('SELECT * FROM #__mucronixreviews_reviews'), stats()];

foreach (['never saved' => [], 'yes' => ['keep_data' => 1]] as $name => $options) {
    options($options);
    $out = remove();
    quiet("uninstall, keep $name", $out);
    check("uninstall, keep $name: gone", '0', (string) one("SELECT COUNT(*) FROM #__extensions WHERE element IN ('com_mucronixreviews', 'pkg_mucronixreviews', 'mucronixreviews')"));
    check("uninstall, keep $name: files gone", [false, false, false], [is_dir("$site/components/com_mucronixreviews") || is_dir("$site/media/com_mucronixreviews"), is_dir("$site/administrator/components/com_mucronixreviews"), is_dir("$site/plugins/system/mucronixreviews") || is_dir("$site/plugins/content/mucronixreviews")]);
    check("uninstall, keep $name: language gone", [], array_merge(glob("$site/language/*/*mucronixreviews*"), glob("$site/administrator/language/*/*mucronixreviews*")));
    check("uninstall, keep $name: tables", 2, count(tables()));
    check("uninstall, keep $name: data", $before, [rows('SELECT * FROM #__mucronixreviews_reviews'), stats()]);
    check("uninstall, keep $name: the templates of the emails gone", '0', (string) one("SELECT COUNT(*) FROM #__mail_templates WHERE extension = 'com_mucronixreviews'"));
    check("uninstall, keep $name: mark", 'Mucronix Reviews schema 0.6.0', rows("SHOW TABLE STATUS LIKE 'st_mucronixreviews_stats'")[0]['Comment']);
    check("site works without it ($name)", 200, (function () { $c = new Client(); $c->get('index.php'); return $c->status; })());

    $out = install($package);
    check("install over kept data ($name)", true, str_contains($out, '[OK]'));
    quiet("install over kept data ($name)", $out);
    check("install over kept data ($name): data", $before, [rows('SELECT * FROM #__mucronixreviews_reviews'), stats()]);
    check("install over kept data ($name): the templates back", '2', (string) one("SELECT COUNT(*) FROM #__mail_templates WHERE extension = 'com_mucronixreviews'"));
    check("install over kept data ($name): schema", '0.6.0', one("SELECT s.version_id FROM #__schemas s JOIN #__extensions e USING (extension_id) WHERE e.element = 'com_mucronixreviews'"));
}

// A later version over the tables of this one: its updates have to run
$later = $stand . '/pkg_mucronixreviews-0.9.0-stand.zip';
copy($package, $later);
$outer = new ZipArchive();
$outer->open($later);
$inner = $stand . '/com_mucronixreviews-stand.zip';
file_put_contents($inner, $outer->getFromName('packages/com_mucronixreviews.zip'));
$part = new ZipArchive();
$part->open($inner);
$part->addFromString('admin/sql/updates/mysql/0.9.0.sql', "ALTER TABLE `#__mucronixreviews_stats` ADD COLUMN `later` INT NOT NULL DEFAULT 5;\n");
$part->addFromString('mucronixreviews.xml', preg_replace('~<version>[^<]+<~', '<version>0.9.0<', $part->getFromName('mucronixreviews.xml')));
$part->close();
$outer->addFile($inner, 'packages/com_mucronixreviews.zip');
$outer->close();

$schema = fn () => one("SELECT s.version_id FROM #__schemas s JOIN #__extensions e USING (extension_id) WHERE e.element = 'com_mucronixreviews'");
$hasLater = fn () => (string) count(rows("SHOW COLUMNS FROM #__mucronixreviews_stats LIKE 'later'"));

remove();
install($later);
check('a later version over kept tables: updated', ['1', '0.9.0', '5'], [$hasLater(), $schema(), (string) one('SELECT later FROM #__mucronixreviews_stats')]);
check('a later version over kept tables: data', $before[0], rows('SELECT * FROM #__mucronixreviews_reviews'));
remove();
check('its mark', 'Mucronix Reviews schema 0.9.0', rows("SHOW TABLE STATUS LIKE 'st_mucronixreviews_stats'")[0]['Comment']);
install($later);
check('the same later version again: no update twice', ['1', '0.9.0'], [$hasLater(), $schema()]);
remove();
run('ALTER TABLE #__mucronixreviews_stats DROP COLUMN later');
run("ALTER TABLE #__mucronixreviews_stats COMMENT = ''");
$out = install($later);
check('kept tables without a mark: the update without the title column', [false, '1', '0.9.0'], [str_contains($out, 'could not be updated'), $hasLater(), $schema()]);

options(['keep_data' => 0]);
remove();
check('uninstall, delete: no tables', [], tables());
install($package);
check('a clean installation after it', [['st_mucronixreviews_reviews', 'st_mucronixreviews_stats'], '0.6.0', '0'], [tables(), $schema(), (string) count(rows("SHOW COLUMNS FROM #__mucronixreviews_reviews LIKE 'title'"))]);


$web = implode("", array_map("file_get_contents", glob("$stand/{web-*,php-errors}.log", GLOB_BRACE) ?: []));
check('no PHP message in the log of the web server', [], array_values(array_unique(array_filter(explode("\n", $web), fn ($line) => preg_match('~PHP (Warning|Notice|Deprecated|Fatal)~', $line) && str_contains($line, 'ucronix')))));

echo $failed ? "\n$failed failed, $passed passed\n" : "all $passed passed\n";
exit($failed ? 1 : 0);
