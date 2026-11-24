<?php

/**
 * YOOtheme Pro: the fields of the rating, the list of reviews, the element "Mucronix Reviews", the classes of UIkit.
 * On a stand whose default template is YOOtheme Pro. The package of build/ is installed first. Not part of the package.
 *
 * Usage: php yootheme.php <folder of the stand>
 *        MUTATE=<name> breaks one thing of mutations-yootheme.php in the installed copy (see mutants.php --yootheme)
 */

require __DIR__ . '/client.php';

$stand = rtrim($argv[1] ?? exit("the folder of the stand\n"), '/\\');
$site  = "$stand/site";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('127.0.0.1', 'stand', 'stand-only-pass-1', 'stand', 33998);
$db->set_charset('utf8mb4');
$failed = 0;
$passed = 0;
$mutate = getenv('MUTATE') ?: '';

function check(string $name, mixed $expected, mixed $actual): void
{
    global $failed, $passed;

    if ($expected === $actual) {
        $passed++;

        return;
    }

    $failed++;
    echo "FAIL  $name\n  expected: " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n  actual:   " . mb_substr((string) json_encode($actual, JSON_UNESCAPED_UNICODE), 0, 600) . "\n";

    if ($GLOBALS['mutate'] !== '') {
        exit(1);
    }
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

/** Runs code in the administrator application of the stand, returns what it prints */
function inside(string $code): string
{
    global $site, $stand;

    $file = tempnam($stand, 'mxr');
    file_put_contents($file, '<?php define("_JEXEC", 1); define("JPATH_BASE", ' . var_export(realpath($site . '/administrator'), true) . ');
        $_SERVER["HTTP_HOST"] = "127.0.0.1:8097"; $_SERVER["REQUEST_URI"] = "/administrator/index.php"; $_SERVER["SCRIPT_NAME"] = "/administrator/index.php"; $_SERVER["REQUEST_METHOD"] = "GET";
        require JPATH_BASE . "/includes/defines.php"; require JPATH_BASE . "/includes/framework.php";
        $container = Joomla\CMS\Factory::getContainer();
        $container->alias("session.web", "session.web.administrator")->alias("session", "session.web.administrator")->alias("JSession", "session.web.administrator")
            ->alias(Joomla\CMS\Session\Session::class, "session.web.administrator")->alias(Joomla\Session\Session::class, "session.web.administrator")->alias(Joomla\Session\SessionInterface::class, "session.web.administrator");
        $app = $container->get(Joomla\CMS\Application\AdministratorApplication::class); Joomla\CMS\Factory::$application = $app; $app->createExtensionNamespaceMap();
        $app->loadIdentity($container->get(Joomla\CMS\User\UserFactoryInterface::class)->loadUserByUsername("standadmin"));
        Joomla\CMS\Plugin\PluginHelper::importPlugin("system", null, true, $app->getDispatcher());
        ' . $code);
    $out = (string) shell_exec('"' . PHP_BINARY . '" -d display_errors=1 "' . $file . '" 2>&1');
    unlink($file);

    return $out;
}

function user(string $name, int $group): int
{
    global $db;

    if ($id = one("SELECT id FROM #__users WHERE username = '$name'")) {
        return (int) $id;
    }

    $hash = password_hash('stand-only-pass-1', PASSWORD_BCRYPT);
    run("INSERT INTO #__users (name, username, email, password, block, sendEmail, registerDate, params) VALUES ('Real $name', '$name', '$name@stand.invalid', '$hash', 0, 0, NOW(), '{}')");
    $id = (int) $db->insert_id;
    run("INSERT INTO #__user_usergroup_map (user_id, group_id) VALUES ($id, $group)");

    return $id;
}

function category(string $title): int
{
    if ($id = one("SELECT id FROM #__categories WHERE alias = '" . strtolower($title) . "' AND extension = 'com_content'")) {
        return (int) $id;
    }

    $out = inside('$t = new Joomla\CMS\Table\Category(Joomla\CMS\Factory::getContainer()->get(Joomla\Database\DatabaseInterface::class)); $t->setLocation(1, "last-child");
        $t->bind(["title" => "' . $title . '", "alias" => "' . strtolower($title) . '", "extension" => "com_content", "published" => 1, "access" => 1, "language" => "*", "params" => "{}", "metadata" => "{}"]);
        if (!$t->check() || !$t->store()) { echo $t->getError(); } else { $t->rebuildPath($t->id); echo "id=", $t->id; }');

    if (!preg_match('~id=(\d+)~', $out, $m)) {
        throw new RuntimeException("category $title: $out");
    }

    return (int) $m[1];
}

function article(string $title, int $category, string $text, array $more = []): int
{
    $data = var_export($more + ['title' => $title, 'alias' => strtolower(preg_replace('~\W+~', '-', $title)), 'catid' => $category, 'articletext' => $text, 'state' => 1, 'access' => 1, 'language' => '*', 'created_by' => 1], true);
    $out  = inside('define("JPATH_COMPONENT", JPATH_ADMINISTRATOR . "/components/com_content"); $m = $app->bootComponent("com_content")->getMVCFactory()->createModel("Article", "Administrator", ["ignore_request" => true]);
        if (!$m->save(' . $data . ')) { echo "FAILED ", $m->getError(); } else { echo "id=", $m->getState("article.id"); }');

    if (!preg_match('~id=(\d+)~', $out, $m)) {
        throw new RuntimeException("article $title: $out");
    }

    return (int) $m[1];
}

function review(int $article, string $user, int $rating, ?string $text = null, int $state = 1): void
{
    global $db;

    static $n = 0;
    $n++;
    $text = $text === null ? 'NULL' : "'" . $db->real_escape_string($text) . "'";
    $id   = (int) one("SELECT id FROM #__users WHERE username = '$user'");
    run("INSERT INTO #__mucronixreviews_reviews (context, item_id, user_id, username, rating, text, state, created) VALUES ('com_content.article', $article, " . ($id ?: 'NULL') . ", '$user', $rating, $text, $state, DATE_ADD('2026-03-01 12:00:00', INTERVAL $n MINUTE))");
}

function recount(): void
{
    inside('$app->bootComponent("com_mucronixreviews")->getReviewService()->recalculateAll();');
}

function options(array $options): void
{
    global $db;

    $json = $db->real_escape_string(json_encode((object) $options));
    run("UPDATE #__extensions SET params = '$json' WHERE element = 'com_mucronixreviews' AND type = 'component'");
}

/** The templates of the builder: each one is matched by its type and its categories */
function templates(array $templates): void
{
    global $db;

    $data = json_decode((string) one("SELECT custom_data FROM #__extensions WHERE element = 'yootheme' AND folder = 'system'"), true) ?: [];
    $data['templates'] = $templates;
    run("UPDATE #__extensions SET custom_data = '" . $db->real_escape_string(json_encode($data)) . "' WHERE element = 'yootheme' AND folder = 'system'");
}

/** A layout of the builder with these elements in one column */
function layout(array $elements): array
{
    return ['type' => 'layout', 'children' => [['type' => 'section', 'props' => [], 'children' => [['type' => 'row', 'children' => [['type' => 'column', 'props' => [], 'children' => $elements]]]]]]];
}

function page(Client $client, int $id, string $query = ''): string
{
    return $client->get('index.php?option=com_content&view=article&id=' . $id . $query);
}

/** The blocks, summaries and grid items of the reviews on a page */
function parts(string $html): array
{
    preg_match_all('~<div class="el-item[^"]*"~', $html, $items);

    return [
        'blocks'    => substr_count($html, '<section class="mxr-block'),
        'summaries' => substr_count($html, 'data-mxr-summary="'),
        'form'      => str_contains($html, 'data-mxr-form'),
        'notice'    => str_contains($html, 'class="mxr-notice'),
        'list'      => str_contains($html, 'data-mxr-list="'),
        'items'     => count($items[0]),
    ];
}

/** The element in a layout */
function element(array $props = []): array
{
    return ['type' => 'mucronix_reviews', 'props' => $props];
}

/** A text element mapped to a field of the article */
function mapped(string $field): array
{
    return ['type' => 'text', 'props' => ['content' => ''], 'source' => ['query' => ['name' => 'article'], 'props' => ['content' => ['name' => $field]]]];
}

/** The template of the builder for the articles of the first category */
function template(array $elements): void
{
    global $cat;

    templates(['mxr' => ['name' => 'MXR', 'type' => 'com_content.article', 'query' => ['catid' => [$cat]], 'layout' => layout($elements)]]);
}

/** The texts of the text elements on a page */
function texts(string $html): array
{
    preg_match_all('~<div class="uk-panel[^"]*">(.*?)</div>~s', $html, $m);

    return array_map('trim', $m[1]);
}

// ---------------------------------------------------------------- the stand
$package = glob(__DIR__ . '/../../build/pkg_mucronixreviews-*.zip')[0] ?? exit("no package in build/\n");
$out     = (string) shell_exec('"' . PHP_BINARY . '" "' . $site . '/cli/joomla.php" extension:install --path="' . realpath($package) . '" 2>&1');

if (!str_contains($out, 'installed successfully')) {
    exit("the package was not installed: $out\n");
}

if ($mutate !== '') {
    [$file, $from, $to] = (require __DIR__ . '/mutations-yootheme.php')[$mutate];
    $code = file_get_contents("$site/$file");

    if (substr_count($code, $from) !== 1) {
        exit("mutation $mutate does not apply\n");
    }

    file_put_contents("$site/$file", str_replace($from, $to, $code));

    // OPcache looks at a changed file after two seconds
    sleep(3);
}

run("UPDATE #__extensions SET params = '{\"yootheme\":1}' WHERE element = 'mucronixreviews' AND folder = 'system'");
run("UPDATE #__extensions SET enabled = 1 WHERE element IN ('com_mucronixreviews', 'mucronixreviews')");

foreach (['anna', 'boris', 'vasyl'] as $name) {
    user($name, 2);
}

$cat    = category('Yoo');
$other  = category('Yoo2');
$rated  = (int) (one("SELECT id FROM #__content WHERE alias = 'rated-yoo'") ?: article('Rated yoo', $cat, '<p>Text of rated yoo</p>'));
$second = (int) (one("SELECT id FROM #__content WHERE alias = 'second-yoo'") ?: article('Second yoo', $other, '<p>Text of second yoo</p>'));
$hidden = (int) (one("SELECT id FROM #__content WHERE alias = 'hidden-yoo'") ?: article('Hidden yoo', $other, '<p>Text of hidden yoo</p>'));
run("UPDATE #__content SET state = 0 WHERE id = $hidden");
run("UPDATE #__content SET introtext = '<p>Text of rated yoo</p>' WHERE id = $rated");
run('DELETE FROM #__mucronixreviews_reviews');
run('DELETE FROM #__mucronixreviews_stats');
review($rated, 'anna', 5, "Fine <b>bold</b> & \"quoted\"\nsecond line");
review($rated, 'boris', 4);
review($rated, 'gone', 3, 'Pending', 0);
review($second, 'anna', 2, 'Of the second');
review($hidden, 'anna', 1, 'Of the hidden');
recount();
options(['schema_contexts' => ['com_content.article']]);
$guest = new Client();
check('YOOtheme is the template of the site', 1, preg_match('~templates/yootheme/css/theme\.css~', $guest->get('index.php')));

// ---------------------------------------------------------------- the element
template([element(['mode' => 'full'])]);
$html = page($guest, $rated);
check('element: the whole block for a guest', [200, ['blocks' => 1, 'summaries' => 1, 'form' => false, 'notice' => true, 'list' => true, 'items' => 0]], [$guest->status, parts($html)]);
check('element: the classes of UIkit', [true, true, true], [str_contains($html, '<section class="mxr-block mxr-block--uikit"'), str_contains($html, 'class="mxr-notice uk-alert"'), str_contains($html, 'data-mxr-failure="uk-alert uk-alert-danger"')]);
check('element: the article of the page, published reviews only', [true, false, true], [str_contains($html, 'data-mxr-summary="com_content.article.' . $rated . '"'), str_contains($html, 'Pending'), str_contains($html, "Fine &lt;b&gt;bold&lt;/b&gt; &amp; &quot;quoted&quot;<br>\nsecond line")]);
check('element: no untranslated string', 0, preg_match('~(COM|PLG_SYSTEM|PLG_CONTENT)_MUCRONIXREVIEWS_~', $html));
check('element: the rating in the graph of Joomla, once', 1, substr_count($html, '"aggregateRating"'));

$vasyl = (new Client())->login('vasyl');
$html  = page($vasyl, $rated);
check('element: the form for a user, with the classes of UIkit', [true, true, true, true], [parts($html)['form'], str_contains($html, '<span class="mxr-form__button uk-button uk-button-default">'), str_contains($html, '<button type="submit" class="mxr-submit uk-button uk-button-primary">'), str_contains($html, 'class="mxr-textarea uk-textarea"')]);
preg_match('~<form action="([^"]*)" method="post" data-mxr-form>(.*?)</form>~s', $html, $form);
preg_match_all('~<input type="hidden" name="([^"]+)" value="([^"]*)"~', $form[2] ?? '', $inputs, PREG_SET_ORDER);
$fields = array_column(array_map(fn ($h) => [$h[1], html_entity_decode($h[2])], $inputs), 1, 0);
$vasyl->request('POST', 'index.php?option=com_mucronixreviews&task=review.save&format=json', ['rating' => 4, 'text' => 'From the element'] + $fields, ['X-CSRF-Token: ' . $vasyl->token]);
$answer = json_decode($vasyl->body, true) ?? [];
check('element: a review sent by the form, the answer with UIkit', [200, true, true], [$vasyl->status, $answer['success'] ?? false, str_contains($answer['html'] ?? '', 'mxr-form__button uk-button')]);
check('element: the note beside the button after it', true, str_contains(page($vasyl, $rated), '<span class="mxr-form__state">'));
run("DELETE FROM #__mucronixreviews_reviews WHERE username = 'vasyl'");
recount();

template([element(['mode' => 'list'])]);
check('element: summary and reviews', ['blocks' => 1, 'summaries' => 1, 'form' => false, 'notice' => false, 'list' => true, 'items' => 0], parts(page($vasyl, $rated)));
template([element(['mode' => 'summary'])]);
check('element: the summary only', ['blocks' => 0, 'summaries' => 1, 'form' => false, 'notice' => false, 'list' => false, 'items' => 0], parts(page($guest, $rated)));
template([element(['mode' => 'x'])]);
check('element: an unknown mode is the whole block', 1, parts(page($guest, $rated))['blocks']);

template([element(['mode' => 'summary', 'item_id' => (string) $second])]);
$html = page($guest, $rated);
check('element: another article by its id', [true, false], [str_contains($html, 'data-mxr-summary="com_content.article.' . $second . '"'), str_contains($html, 'data-mxr-summary="com_content.article.' . $rated . '"')]);
check('element: no rating of another article in the graph', 0, substr_count($html, '"aggregateRating"'));
template([element(['mode' => 'full', 'item_id' => (string) $hidden])]);
check('element: an article the visitor may not see, nothing', ['blocks' => 0, 'summaries' => 0, 'form' => false, 'notice' => false, 'list' => false, 'items' => 0], parts(page($guest, $rated)));
template([element(['mode' => 'full', 'context' => 'com_unknown.item'])]);
check('element: a type that is not there, nothing', [200, 0], [$guest->status, parts(page($guest, $rated))['summaries']]);

// A page with no object, a blog of the builder: with an id the object is an article, as for the shortcode
$blog = function (array $props) use ($cat): string {
    global $guest;

    templates(['blog' => ['name' => 'Blog', 'type' => 'com_content.category', 'query' => ['catid' => [(string) $cat]], 'layout' => layout([element($props)])]]);

    return $guest->get("index.php?option=com_content&view=category&layout=blog&id=$cat");
};
$html = $blog(['mode' => 'full', 'item_id' => (string) $second]);
check('no object on the page, an id: the block of that article', [200, 1, true], [$guest->status, parts($html)['blocks'], str_contains($html, 'data-mxr-summary="com_content.article.' . $second . '"')]);
check('no object on the page, no id: nothing', 0, parts($blog(['mode' => 'full']))['summaries']);
check('no object on the page, an article the visitor may not see: nothing', 0, parts($blog(['mode' => 'summary', 'item_id' => (string) $hidden]))['summaries']);
check('no object on the page, an id and a type that is not there: nothing', 0, parts($blog(['mode' => 'summary', 'item_id' => (string) $second, 'context' => 'com_unknown.item']))['summaries']);

// Mapped to the id of each article of a list: the element is repeated for them
template([[
    'type'   => 'mucronix_reviews',
    'props'  => ['mode' => 'summary'],
    'source' => ['query' => ['name' => 'customArticles', 'arguments' => ['catid' => [(string) $cat, (string) $other], 'limit' => 10]], 'props' => ['item_id' => ['name' => 'id']]],
]]);
$html = page($guest, $rated);
check('element: mapped to the id of each article of a list', [true, true, false], [str_contains($html, 'data-mxr-summary="com_content.article.' . $rated . '"'), str_contains($html, 'data-mxr-summary="com_content.article.' . $second . '"'), str_contains($html, 'data-mxr-summary="com_content.article.' . $hidden . '"')]);

// ---------------------------------------------------------------- the fields of the article
template([mapped('mucronixRating'), mapped('mucronixRatingRounded'), mapped('mucronixRatingCount'), mapped('mucronixReviewCount'), mapped('mucronixStars')]);
$html = page($guest, $rated);
check('fields: average, rounded, ratings, reviews with text', ['4.50', '4.5', '2', '1'], array_slice(texts($html), 0, 4));
check('fields: the stars, with the mark of the summary', true, str_contains($html, 'data-mxr-summary="com_content.article.' . $rated . '"'));
options(['schema_contexts' => ['com_content.article'], 'rounding' => 'whole']);
check('fields: rounded to whole stars', '5.0', texts(page($guest, $rated))[1] ?? null);
options(['schema_contexts' => ['com_content.article']]);

// ---------------------------------------------------------------- the list of reviews
$grid = fn (array $arguments): array => ['type' => 'grid', 'props' => [], 'children' => [[
    'type'   => 'grid_item',
    'props'  => [],
    'source' => ['query' => ['name' => 'article', 'field' => ['name' => 'mucronixReviews', 'arguments' => $arguments]], 'props' => ['title' => ['name' => 'author'], 'content' => ['name' => 'text'], 'meta' => ['name' => 'date', 'filters' => ['date' => 'Y-m-d']]]],
]]];
$authors = function (string $html): array {
    preg_match_all('~<h3 class="el-title[^"]*">([^<]*)</h3>~', $html, $m);

    return array_map('trim', $m[1]);
};
template([$grid([])]);
$html = page($guest, $rated);
check('list: published reviews, newest first', ['boris', 'anna'], $authors($html));
check('list: the text escaped, the lines kept, the date by the filter', [true, true], [str_contains($html, "Fine &lt;b&gt;bold&lt;/b&gt; &amp; &quot;quoted&quot;<br>\nsecond line"), str_contains($html, '2026-03-01')]);
template([$grid(['text_only' => true])]);
check('list: with text only', ['anna'], $authors(page($guest, $rated)));
template([$grid(['order' => 'lowest'])]);
check('list: lowest first', ['boris', 'anna'], $authors(page($guest, $rated)));
template([$grid(['order' => 'highest', 'limit' => 1])]);
check('list: highest, one', ['anna'], $authors(page($guest, $rated)));
template([$grid(['order' => 'x'])]);
check('list: an unknown order is the one of the options', ['boris', 'anna'], $authors(page($guest, $rated)));

// ---------------------------------------------------------------- one block of an article on a page
run("UPDATE #__content SET introtext = '<p>{mucronixreviews}</p>' WHERE id = $rated");
template([element(['mode' => 'full']), mapped('content')]);
check('one block: the element and the shortcode in the text', 1, parts(page($guest, $rated))['blocks']);
template([element(['mode' => 'summary']), mapped('content')]);
check('one block: the summary of the element and the block of the shortcode', [1, 2], array_values(array_intersect_key(parts(page($guest, $rated)), ['blocks' => 1, 'summaries' => 1])));

// The preview of the builder: the cookie of its session, as YOOtheme makes it (LoadCustomizerSession)
preg_match('~public \$secret = \'([^\']*)\'~', file_get_contents("$site/configuration.php"), $secret);
$preview = (new Client())->cookie('yootheme_session', 'mxr_' . hash_hmac('md5', 'mxr', $secret[1] ?? ''));
$once    = 'are on the page already';
template([element(['mode' => 'full']), mapped('content')]);
$html = page($preview, $rated);
check('one block, preview: the element says the block is on the page already', [1, 1, 1], [parts($html)['blocks'], substr_count($html, $once), substr_count($html, '<div class="uk-alert uk-alert-warning">')]);
check('one block, site: the element says nothing', [1, false], [parts($html = page($guest, $rated))['blocks'], str_contains($html, $once)]);
template([element(['mode' => 'list']), mapped('content')]);
check('one block, preview: the same for summary and reviews', [1, 1], [parts($html = page($preview, $rated))['blocks'], substr_count($html, $once)]);
template([element(['mode' => 'summary']), mapped('content')]);
check('one block, preview: a summary is no repeat', [2, false], [parts($html = page($preview, $rated))['summaries'], str_contains($html, $once)]);
run("UPDATE #__content SET introtext = '<p>Text of rated yoo</p>' WHERE id = $rated");
template([element(['mode' => 'full']), mapped('content')]);
check('one block, preview: the block alone, no message', [1, false], [parts($html = page($preview, $rated))['blocks'], str_contains($html, $once)]);

// ---------------------------------------------------------------- the own look, the integration off, the component off
template([element(['mode' => 'full'])]);
options(['schema_contexts' => ['com_content.article'], 'control_style' => 'own']);
$html = page($vasyl, $rated);
check('own look: no class of UIkit', [false, false, true], [str_contains($html, 'mxr-form__button uk-button'), str_contains($html, 'mxr-block--uikit'), str_contains($html, '<section class="mxr-block" ')]);
options(['schema_contexts' => ['com_content.article']]);

run("UPDATE #__extensions SET params = '{\"yootheme\":0}' WHERE element = 'mucronixreviews' AND folder = 'system'");
$html = page($guest, $rated);
check('integration off: no element, the page works', [200, 0], [$guest->status, parts($html)['summaries']]);
run("UPDATE #__extensions SET params = '{\"yootheme\":1}' WHERE element = 'mucronixreviews' AND folder = 'system'");

run("UPDATE #__extensions SET enabled = 0 WHERE element = 'com_mucronixreviews'");
template([element(['mode' => 'full']), mapped('mucronixRating')]);
$html = page($guest, $rated);
check('component disabled: nothing, the page works', [200, 0, false], [$guest->status, parts($html)['summaries'], str_contains($html, '4.50')]);
run("UPDATE #__extensions SET enabled = 1 WHERE element = 'com_mucronixreviews'");

// ---------------------------------------------------------------- the builder
$admin = (new Client())->loginAdmin();
$style = (int) one("SELECT id FROM #__template_styles WHERE template = 'yootheme' AND client_id = 0");
$page  = $admin->get("administrator/index.php?option=com_ajax&p=customizer&templateStyle=$style&format=html");
check('builder: the customizer opens with the element', [200, true, 0], [$admin->status, str_contains($page, '"mucronix_reviews"'), preg_match('~PLG_SYSTEM_MUCRONIXREVIEWS_~', $page)]);
preg_match('~"csrf":"([0-9a-f]+)"~', $page, $csrf);
$schema = json_decode($admin->get("administrator/index.php?option=com_ajax&p=builder/schema&templateStyle=$style&csrf=" . ($csrf[1] ?? '')), true) ?? [];
$types  = array_column($schema['types'] ?? [], null, 'name');
$names  = array_column($types['Article']['fields'] ?? [], 'name');
check('builder: the fields of the article', [true, true, true, true, true, true], array_map(fn ($f) => in_array($f, $names, true), ['mucronixRating', 'mucronixRatingRounded', 'mucronixRatingCount', 'mucronixReviewCount', 'mucronixStars', 'mucronixReviews']));
check('builder: the type of a review', ['author', 'rating', 'stars', 'text', 'date'], array_column($types['MucronixReview']['fields'] ?? [], 'name'));

// ---------------------------------------------------------------- Cassiopeia gets no class of UIkit
run("UPDATE #__template_styles SET home = IF(template = 'cassiopeia', '1', '0') WHERE client_id = 0");
run("UPDATE #__content SET introtext = '<p>{mucronixreviews}</p>' WHERE id = $rated");
$html = page($vasyl, $rated);
check('Cassiopeia: the block without UIkit', [1, false], [parts($html)['blocks'], str_contains($html, 'uk-button')]);
run("UPDATE #__template_styles SET home = IF(template = 'yootheme', '1', '0') WHERE client_id = 0");
run("UPDATE #__content SET introtext = '<p>Text of rated yoo</p>' WHERE id = $rated");

$log = (string) @file_get_contents("$stand/php-errors.log");
check('no PHP message of the package', [], array_values(array_filter(explode("\n", $log), fn ($line) => preg_match('~PHP (Warning|Notice|Deprecated|Fatal)~', $line) && str_contains($line, 'ucronix'))));

echo $failed ? "\n$failed failed, $passed passed\n" : "all $passed passed\n";
exit($failed ? 1 : 0);
