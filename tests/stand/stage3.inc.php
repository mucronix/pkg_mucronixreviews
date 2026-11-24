<?php

/**
 * Stage 3: the articles of Joomla. Included by drive.php, the package is installed.
 */

/** Saves an article by the model of Joomla, returns its id */
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

function category(string $title, int $parent = 1): int
{
    $out = inside('$t = new Joomla\CMS\Table\Category(Joomla\CMS\Factory::getContainer()->get(Joomla\Database\DatabaseInterface::class)); $t->setLocation(' . $parent . ', "last-child");
        $t->bind(["title" => "' . $title . '", "alias" => "' . strtolower($title) . '", "extension" => "com_content", "published" => 1, "access" => 1, "language" => "*", "params" => "{}", "metadata" => "{}"]);
        if (!$t->check() || !$t->store()) { echo $t->getError(); } else { $t->rebuildPath($t->id); echo "id=", $t->id; }');

    if (!preg_match('~id=(\d+)~', $out, $m)) {
        throw new RuntimeException("category $title: $out");
    }

    return (int) $m[1];
}

/** The block of the articles: categories, position, show. They are options of the component, kept by options() too. */
function plugin(array $params): void
{
    $GLOBALS['articleOptions'] = [];

    foreach ($params as $name => $value) {
        $GLOBALS['articleOptions']['article_' . $name] = $value;
    }

    $current = json_decode((string) one("SELECT params FROM #__extensions WHERE element = 'com_mucronixreviews' AND type = 'component'"), true) ?: [];
    options(array_diff_key($current, array_flip(['article_categories', 'article_position', 'article_show'])));
}

function page(Client $client, int $id, string $query = ''): string
{
    return $client->get('index.php?option=com_content&view=article&id=' . $id . $query);
}

/** What there is of the reviews on a page: block, summary, form, guest, list, number of blocks */
function parts(string $html): string
{
    return implode(' ', array_filter([
        substr_count($html, '<section class="mxr-block"') ? 'block' . substr_count($html, '<section class="mxr-block"') : '',
        'summary' . substr_count($html, 'class="mxr-summary"'),
        str_contains($html, 'data-mxr-form') ? 'form' : '',
        str_contains($html, 'class="mxr-notice"') ? 'notice' : '',
        str_contains($html, 'class="mxr-list"') ? 'list' : '',
    ]));
}

function authors(string $html): array
{
    preg_match_all('~mxr-review__author">([^<]*)<~', $html, $m);

    return $m[1];
}

/** What a browser would send with the form of a page */
function formFields(string $html): array
{
    if (!preg_match('~<form action="([^"]*)" method="post" data-mxr-form>(.*?)</form>~s', $html, $m)) {
        return [];
    }

    preg_match_all('~<input type="hidden" name="([^"]+)" value="([^"]*)"~', $m[2], $hidden, PREG_SET_ORDER);

    return ['action' => html_entity_decode($m[1]), 'fields' => array_column(array_map(fn ($h) => [$h[1], html_entity_decode($h[2])], $hidden), 1, 0)];
}

function reviewOf(int $article, string $user, int $rating, ?string $text = null, int $state = 1): void
{
    global $db, $ids;

    $text = $text === null ? 'NULL' : "'" . $db->real_escape_string($text) . "'";
    static $n = 0;
    $n++;
    run("INSERT INTO #__mucronixreviews_reviews (context, item_id, user_id, username, rating, text, state, created) VALUES ('com_content.article', $article, " . ($ids[$user] ?? 'NULL') . ", '$user', $rating, $text, $state, DATE_ADD('2026-03-01 12:00:00', INTERVAL $n MINUTE))");
}

$recount = fn () => inside('$service->recalculateAll();');
$ids = array_column(rows('SELECT id, username FROM #__users'), 'id', 'username') + $ids;

foreach (['anna', 'boris', 'avtor', 'vasyl', 'nobody2'] as $name) {
    if (!one("SELECT id FROM #__users WHERE username = '$name'")) {
        $ids[$name] = user($name, $name === 'nobody2' ? 6 : 2);
    }
}

run('DELETE FROM #__content');
run("DELETE FROM #__workflow_associations WHERE extension = 'com_content.article'");
run("DELETE FROM #__categories WHERE alias IN ('parent', 'child', 'other')");
run("DELETE FROM #__modules WHERE note = 'mxr'");
wipe();
options([]);
plugin([]);

$parent = category('Parent');
$child  = category('Child', $parent);
$other  = category('Other');
$main   = article('Main article', $child, '<p>Text of main</p>');
$second = article('Second article', $other, '<p>Text of second</p>');
$guest  = new Client();
$anna   = (new Client())->login('anna');

// ---------------------------------------------------------------- the type of objects
check('type: title', 'Main article', inside('echo $app->bootComponent("com_mucronixreviews")->getContextRegistry()->getHandler("com_content.article")->getTitle(' . $main . ');'));
$address = inside('echo $app->bootComponent("com_mucronixreviews")->getContextRegistry()->getHandler("com_content.article")->getUrl(' . $main . ');');
check('type: address, also from the administrator', 1, preg_match('~^http://127\.0\.0\.1:8097/(?!administrator)\S*main-article~', $address));
check('type: the address is the page', true, str_contains($guest->get($address), 'Text of main'));
check('type: nothing of an article that is not there', '||0', inside('$h = $app->bootComponent("com_mucronixreviews")->getContextRegistry()->getHandler("com_content.article"); echo $h->getTitle(777777), "|", $h->getUrl(777777), "|", (int) $h->isAvailable(777777, $app->getIdentity());'));

// ---------------------------------------------------------------- what can be reviewed
$try = function (string $name, string $break, string $restore, int $status, bool $viewable) use ($anna, $main) {
    wipe();
    run($break);
    [$got] = $anna->review(['context' => 'com_content.article', 'item_id' => $main]);
    $anna->request('GET', "index.php?option=com_mucronixreviews&task=list.more&format=json&context=com_content.article&item_id=$main&offset=0");

    if (in_array($anna->status, [301, 303], true)) {
        $anna->request('GET', $anna->location);
    }

    run($restore);
    check("availability: $name", [$status, $viewable ? 200 : 404], [$got, $anna->status]);
};
$content = fn (string $set) => "UPDATE #__content SET $set WHERE id = $main";
$cat = fn (string $set, int $id) => "UPDATE #__categories SET $set WHERE id = $id";
$try('published', 'DO 0', 'DO 0', 200, true);
$try('unpublished', $content('state = 0'), $content('state = 1'), 404, false);
$try('archived', $content('state = 2'), $content('state = 1'), 404, true);
$try('trashed', $content('state = -2'), $content('state = 1'), 404, false);
$try('not yet', $content('publish_up = UTC_TIMESTAMP() + INTERVAL 1 HOUR'), $content('publish_up = NULL'), 404, false);
$try('no longer', $content('publish_down = UTC_TIMESTAMP() - INTERVAL 1 HOUR'), $content('publish_down = NULL'), 404, false);
$try('within its dates', $content('publish_up = UTC_TIMESTAMP() - INTERVAL 1 HOUR, publish_down = UTC_TIMESTAMP() + INTERVAL 1 HOUR'), $content('publish_up = NULL, publish_down = NULL'), 200, true);
$try('access', $content('access = 3'), $content('access = 1'), 404, false);
$try('for registered users', $content('access = 2'), $content('access = 1'), 200, true);
$try('category unpublished', $cat('published = 0', $child), $cat('published = 1', $child), 404, false);
$try('category archived', $cat('published = 2', $child), $cat('published = 1', $child), 404, true);
$try('category trashed', $cat('published = -2', $child), $cat('published = 1', $child), 404, false);
$try('category access', $cat('access = 3', $child), $cat('access = 1', $child), 404, false);
$try('parent unpublished', $cat('published = 0', $parent), $cat('published = 1', $parent), 404, false);
$try('parent archived', $cat('published = 2', $parent), $cat('published = 1', $parent), 404, true);
$try('a foreign category does not matter', $cat('published = 0', $other), $cat('published = 1', $other), 200, true);

// ---------------------------------------------------------------- the automatic block
wipe();
plugin([]);
check('automatic: no category, nothing', 'summary0', parts(page($guest, $main)));
check('automatic: nothing, no assets', false, str_contains(page($guest, $main), 'com_mucronixreviews/'));
plugin(['categories' => [$child]]);
check('automatic: guest', 'block1 summary1 notice', parts(page($guest, $main)));
check('automatic: user', 'block1 summary1 form', parts(page($anna, $main)));
check('automatic: another category', 'summary0', parts(page($guest, $second)));
plugin(['categories' => [$parent]]);
check('automatic: not for the categories inside', 'summary0', parts(page($guest, $main)));
plugin(['categories' => [$child, $other]]);
check('automatic: several categories', 'block1 summary1 notice', parts(page($guest, $second)));
$html = page($guest, $main);
check('automatic: after the text', true, strpos($html, 'Text of main') < strpos($html, 'mxr-block'));
check('automatic: assets', [1, 1], [preg_match('~com_mucronixreviews/css/reviews(\.min)?\.css~', $html), preg_match('~com_mucronixreviews/js/reviews(\.min)?\.js[^>]*defer~', $html)]);
check('automatic: no untranslated string', 0, preg_match('~(COM|PLG_CONTENT)_MUCRONIXREVIEWS_~', $html));
plugin(['categories' => [$child], 'position' => 'before']);
$html = page($guest, $main);
check('automatic: before the text', [true, 'block1 summary1 notice'], [strpos($html, 'Text of main') > strpos($html, 'mxr-block'), parts($html)]);

reviewOf($main, 'anna', 5, 'Anna text');
reviewOf($main, 'boris', 4);
$recount();

foreach (['full' => ['block1 summary1 notice list', 'block1 summary1 form list'], 'list' => ['block1 summary1 list', 'block1 summary1 list'], 'summary' => ['block1 summary1', 'block1 summary1']] as $show => [$forGuest, $forUser]) {
    plugin(['categories' => [$child], 'show' => $show]);
    check("automatic: $show", [$forGuest, $forUser], [parts(page($guest, $main)), parts(page($anna, $main))]);
}

plugin(['categories' => [$child, $other, 2]]);
$guest->get("index.php?option=com_content&view=category&layout=blog&id=$child");
check('automatic: not in a blog', [200, 'summary0'], [$guest->status, parts($guest->body)]);
check('automatic: not on the featured page', 'summary0', parts($guest->get('index.php?option=com_content&view=featured')));
run($content('state = 2'));
check('automatic: an archived article, list without form', ['block1 summary1 list', 'block1 summary1 list'], [parts(page($guest, $main)), parts(page($anna, $main))]);
run($content('state = 1'));
$superSite = (new Client())->login('standadmin');
run($content('state = 0'));
$html = page($superSite, $main);
check('automatic: not in the preview of an unpublished article', [true, 'summary0'], [str_contains($html, 'Text of main'), parts($html)]);
run($content('state = 1'));
run("UPDATE #__extensions SET enabled = 0 WHERE element = 'com_mucronixreviews'");
check('automatic: nothing while the component is disabled', ['summary0', 200, false], [parts(page($guest, $main)), $guest->status, str_contains($guest->body, 'com_mucronixreviews/')]);
run("UPDATE #__extensions SET enabled = 1 WHERE element = 'com_mucronixreviews'");

// ---------------------------------------------------------------- what the block says
plugin(['categories' => [$child]]);
wipe();
$html = html_entity_decode(page($guest, $main));
check('summary: no ratings', [true, true], [str_contains($html, 'No ratings yet'), str_contains($html, 'mxr-stars--0"')]);
reviewOf($main, 'anna', 5);
$recount();
$html = html_entity_decode(page($guest, $main));
check('summary: one', [true, true, true], [str_contains($html, '>5.0 of 5<'), str_contains($html, '>1 rating<'), str_contains($html, 'mxr-stars--50"')]);
reviewOf($main, 'boris', 4);
reviewOf($main, 'avtor', 4);
$recount();
$html = html_entity_decode(page($guest, $main));
check('summary: 4.33, half', [true, true, true], [str_contains($html, '>4.3 of 5<'), str_contains($html, '>3 ratings<'), str_contains($html, 'mxr-stars--45" aria-hidden')]);
options(['rounding' => 'whole']);
$html = html_entity_decode(page($guest, $main));
check('summary: 4.33, whole: the stars, not the number', [true, true], [str_contains($html, '>4.3 of 5<'), str_contains($html, 'mxr-stars--40" aria-hidden')]);
options([]);

wipe();
reviewOf($main, 'anna', 2, "First <b>line</b>\nSecond & \"line\"");
reviewOf($main, 'boris', 3, 'Hidden', 0);
reviewOf($main, 'avtor', 1, 'Spam', -3);
reviewOf($main, '<u>gone</u>', 4);
$recount();
$html = page($guest, $main);
check('list: published only, the newest first', ['&lt;u&gt;gone&lt;/u&gt;', 'anna'], authors($html));
check('list: escaped, lines kept, no title', [true, true, false], [str_contains($html, 'First &lt;b&gt;line&lt;/b&gt;<br>'), str_contains($html, '&quot;line&quot;'), str_contains($html, 'mxr-review__title')]);
check('list: no name, no email', [false, false], [str_contains($html, 'Real anna'), str_contains($html, 'anna@stand')]);
check('list: stars for a screen reader', true, str_contains($html, 'role="img" aria-label="2 of 5"'));
check('list: date', 1, preg_match('~<time class="mxr-review__date" datetime="2026-03-01T[^"]+">01 March 2026</time>~', $html));
options(['date_format' => 'Y/m/d']);
check('list: format of the date', true, str_contains(page($guest, $main), '>2026/03/01</time>'));
options(['show_date' => 0]);
check('list: no date', false, str_contains(page($guest, $main), '<time class="mxr'));
options(['list_order' => 'lowest']);
check('list: order', ['anna', '&lt;u&gt;gone&lt;/u&gt;'], authors(page($guest, $main)));
options([]);

// ---------------------------------------------------------------- who sees what
run("UPDATE #__extensions SET params = JSON_SET(params, '$.allowUserRegistration', '0') WHERE element = 'com_users'");
$html = html_entity_decode(page($guest, $main));
check('guest: login only', [1, false], [preg_match('~<div class="mxr-notice"><a href="([^"]+)">Log in</a> to leave a review\.~', $html, $login), str_contains($html, '>register</a>')]);
preg_match('~return=([^&"]+)~', $login[1] ?? '', $return);
check('guest: comes back to the article', $address, base64_decode(urldecode($return[1] ?? '')));
$fresh = new Client();
$fresh->get(ltrim($login[1] ?? '', '/'));
check('guest: the login page', [200, true], [$fresh->status, str_contains($fresh->body, 'name="password"')]);
run("UPDATE #__extensions SET params = JSON_SET(params, '$.allowUserRegistration', '1') WHERE element = 'com_users'");
$html = html_entity_decode(page($guest, $main));
check('guest: login or register', 1, preg_match('~>Log in</a> or <a href="[^"]+">register</a> to leave a review\.~', $html));
$raw = page($guest, $main, '&utm=<script>x');
check('guest: foreign parameters are not carried on', [$address, false], [base64_decode(urldecode(preg_match('~mxr-notice"><a href="[^"]*return=([^&"]+)"~', $raw, $m) ? $m[1] : '')), str_contains($raw, '<script>x')]);
run("UPDATE #__extensions SET params = JSON_SET(params, '$.allowUserRegistration', '0') WHERE element = 'com_users'");

$nobody = (new Client())->login('nobody2');
$html = html_entity_decode(page($nobody, $main));
check('wrong group', [true, false], [str_contains($html, 'Your account may not leave reviews.'), str_contains($html, 'data-mxr-form')]);
$html = html_entity_decode(page($anna, $main));
check('own review: form is filled', [true, true, true, false], [str_contains($html, 'Edit Your Review'), str_contains($html, 'value="2" required checked'), str_contains($html, 'First <b>line</b>'), str_contains($html, 'name="title"')]);
check('own review: published, no note', [false, false], [str_contains($html, 'mxr-form__state'), str_contains($html, 'Your review is')]);
$boris = (new Client())->login('boris');
check('own review: pending, beside the button', 1, preg_match('~<summary>\s*<span class="mxr-form__button">Edit Your Review</span>\s*<span class="mxr-form__state">Your review is being checked</span>\s*</summary>~', page($boris, $main)));
foreach ([-1 => 'rejected', -2 => 'in the trash', -3 => 'spam'] as $state => $name) {
    run("UPDATE #__mucronixreviews_reviews SET state = $state WHERE username = 'boris'");
    $html = page($boris, $main);
    check("own review: $name, not published", [1, false, false], [preg_match('~<span class="mxr-form__button">Edit Your Review</span>\s*<span class="mxr-form__state">Your review is not published</span>~', $html), str_contains($html, 'being checked'), stripos($html, 'spam')]);
}
run("UPDATE #__mucronixreviews_reviews SET state = 0 WHERE username = 'boris'");
check('own review: the pending text is in the form of its author only', [true, false], [str_contains(page($boris, $main), 'Hidden'), str_contains(page($anna, $main), 'Hidden')]);
$vasyl = (new Client())->login('vasyl');
$html = page($vasyl, $main);
check('no review yet', [true, false, false], [str_contains($html, 'Leave a Review'), str_contains($html, ' checked'), str_contains($html, '<details class="mxr-form" open')]);
options(['allow_edit' => 0]);
$html = page($anna, $main);
check('editing not allowed', [true, false], [str_contains($html, 'You have already left a review here.'), str_contains($html, 'data-mxr-form')]);
check('editing not allowed: a new review is', true, str_contains(page($vasyl, $main), 'data-mxr-form'));
options(['mode' => 'stars']);
check('form: stars only', false, str_contains(page($vasyl, $main), 'name="text"'));
options(['text_mode' => 'required', 'text_min' => 7, 'text_max' => 77]);
$html = page($vasyl, $main);
check('form: required', [1, true, false], [preg_match('~name="text" rows="5" maxlength="77" required minlength="7"~', $html), str_contains($html, 'At least 7 characters.'), str_contains($html, 'name="title"')]);
options(['text_mode' => 'off']);
check('form: text off', false, str_contains(page($vasyl, $main), 'name="text"'));
options([]);
check('form: no title', [false, true], [str_contains(page($vasyl, $main), 'name="title"'), str_contains(page($vasyl, $main), 'name="text"')]);
check('form: five radio buttons with labels', 5, preg_match_all('~<input type="radio" name="rating" value="[1-5]" required>\s*<span class="mxr-rate__star" aria-hidden="true"></span>\s*<span class="mxr-visually-hidden">\d stars?</span>~', page($vasyl, $main)));

// ---------------------------------------------------------------- the form without JavaScript
wipe();
$form = formFields(page($vasyl, $main));
$post = function (Client $client, array $form, array $fields): void {
    $client->request('POST', preg_replace('~^/~', '', $form['action']), $fields + $form['fields']);
};
$post($vasyl, $form, ['rating' => 4, 'text' => 'From a page']);
check('form: sent, back to the article', [303, $address], [$vasyl->status, $vasyl->location]);
check('form: stored', [['item_id' => (string) $main, 'rating' => '4', 'text' => 'From a page', 'state' => '0']], rows('SELECT item_id, rating, text, state FROM #__mucronixreviews_reviews'));
check('form: the message is shown', true, str_contains(html_entity_decode($vasyl->get($vasyl->location)), 'will appear after it has been checked'));
check('form: after it, the note beside the button', true, str_contains(page($vasyl, $main), '<span class="mxr-form__state">Your review is being checked</span>'));
rest();
options(['text_mode' => 'required']);
$post($vasyl, formFields(page($vasyl, $main)), ['rating' => 3, 'text' => "Kept\n<text>"]);
$html = $vasyl->get($vasyl->location);
check('form: refused, what was typed is there and the form is open', [true, true, true], [str_contains(html_entity_decode($html), 'The text is too short'), str_contains($html, "Kept\n&lt;text&gt;"), str_contains($html, '<details class="mxr-form" open')]);
check('form: what was typed is shown once', false, str_contains(page($vasyl, $main), "Kept\n&lt;text&gt;"));
options([]);

// ---------------------------------------------------------------- the form with JavaScript
wipe();
[$status, $answer] = $vasyl->review(['context' => 'com_content.article', 'item_id' => $main, 'rating' => 4, 'list' => '1', 'return' => base64_encode($address)]);
check('script: what is shown after it', ['summary1 form list', ['vasyl'], true, 1], [parts($answer['html']), authors($answer['html']), str_contains($answer['html'], 'Edit Your Review'), $answer['stats']['ratings']]);
check('script: the return stays', $address, base64_decode(formFields($answer['html'])['fields']['return'] ?? ''));
rest();
[$status, $answer] = $vasyl->review(['context' => 'com_content.article', 'item_id' => $main, 'rating' => 5, 'list' => '0']);
check('script: without the list', 'summary1 form', parts($answer['html']));
rest();
[$status, $answer] = $vasyl->review(['context' => 'com_content.article', 'item_id' => $main, 'return' => base64_encode('https://evil.example/')]);
check('script: no foreign return', $address, base64_decode(formFields($answer['html'])['fields']['return'] ?? ''));

// ---------------------------------------------------------------- more
wipe();
options(['list_limit' => 2]);

foreach ([1, 2, 3, 4, 5] as $n) {
    reviewOf($main, "user$n", $n);
}

$recount();
$html = page($guest, $main);
check('more: the first page', [['user5', 'user4'], 1, 1], [authors($html), preg_match('~<button type="button" class="mxr-more" hidden data-mxr-url="([^"]+)" data-mxr-offset="2">Show More</button>~', $html, $m), preg_match('~<button type="button" class="mxr-more" hidden~', $html)]);
$url = html_entity_decode($m[1] ?? '');
$next = function (Client $client, string $offset) use ($url): array {
    $client->request('GET', ltrim($url, '/') . (str_contains($url, '?') ? '&' : '?') . 'format=json&offset=' . urlencode($offset));

    return [$client->status, json_decode($client->body, true)];
};
[$status, $answer] = $next($guest, '2');
check('more: the second page', [200, ['user3', 'user2'], 4], [$status, authors($answer['html'] ?? ''), $answer['next'] ?? null]);
[$status, $answer] = $next($guest, '4');
check('more: the last page', [200, ['user1'], null], [$status, authors($answer['html'] ?? ''), $answer['next'] ?? null]);
[$status, $answer] = $next($guest, '99');
check('more: beyond the last page', [200, [], null], [$status, authors($answer['html'] ?? ''), $answer['next'] ?? null]);

foreach (['-1', 'abc', '1 OR 1=1', '1.5', ''] as $offset) {
    [$status, $answer] = $next($guest, $offset);
    check("more: offset '$offset'", [200, 'user5'], [$status, authors($answer['html'] ?? '')[0] ?? null]);
}

check('more: only a script may ask', 404, (function () use ($guest, $url) { $guest->get(ltrim($url, '/')); return $guest->status; })());

foreach (["context=com_nobody.item&item_id=1", "context=com_content.article&item_id=0", "context=com_content.article&item_id=777777", "context[]=x&item_id=$main", "item_id=$main"] as $query) {
    $guest->request('GET', "index.php/component/mucronixreviews/?task=list.more&format=json&$query");
    check("more: $query", 404, $guest->status);
}

run($content('access = 2'));
check('more: not for a guest who may not see the article', 404, $next($guest, '0')[0]);
check('more: for a user who may', 200, $next($anna, '0')[0]);
run($content('access = 1'));
options(['list_limit' => 5]);
check('more: no button when everything is there', false, str_contains(page($guest, $main), 'mxr-more'));
options([]);

// ---------------------------------------------------------------- the shortcode
plugin([]);
wipe();
reviewOf($main, 'anna', 5);
reviewOf($second, 'boris', 2);
$recount();
$withText = function (string $text) use ($main): void {
    global $db;

    run("UPDATE #__content SET introtext = '" . $db->real_escape_string($text) . "' WHERE id = $main");
};

foreach ([
    '<p>{mucronixreviews}</p>' => 'block1 summary1 notice list',
    '<p>{mucronixreviews summary}</p>' => 'summary1',
    "<p>{mucronixreviews id=$second}</p>" => 'block1 summary1 notice list',
    "<p>{mucronixreviews summary id=$second}</p>" => 'summary1',
    "<p>{mucronixreviews id=$second summary}</p>" => 'summary1',
    '<p>{MucronixReviews  SUMMARY }</p>' => 'summary1',
    '<p>Rating: {mucronixreviews summary} here</p>' => 'summary1',
    '{mucronixreviews}{mucronixreviews}' => 'block1 summary1 notice list',
    '{mucronixreviews}<p>{mucronixreviews summary}</p>{mucronixreviews summary}' => 'block1 summary3 notice list',
    "{mucronixreviews}{mucronixreviews id=$second}" => 'block2 summary2 notice list',
    '{mucronixreviews id=777777}' => 'summary0',
    '{mucronixreviews id=0}' => 'summary0',
    '{mucronixreviewsx}' => 'summary0',
] as $text => $expected) {
    $withText($text);
    $html = page($guest, $main);
    check("shortcode $text", [$expected, $text === '{mucronixreviewsx}'], [parts($html), stripos($html, '{mucronixreviews') !== false]);
}

$withText('<p class="x">{mucronixreviews}</p><p>After</p>');
check('shortcode: no paragraph around the block', 0, preg_match('~<p[^>]*>\s*<section class="mxr-block"~', page($guest, $main)));
$withText('<p>Rating: {mucronixreviews summary} here</p>');
check('shortcode: the summary stays in its sentence', 1, preg_match('~<p>Rating: <span class="mxr-summary"[^>]*>.*?</span>\s* here</p>~s', page($guest, $main)));
$withText('<p style="color: #999;"><em>{mucronixreviews}</em>&nbsp;</p><p>After</p>');
$html = page($guest, $main);
check('shortcode: a paragraph in emphasis is replaced as a whole', [0, 0, 1], [preg_match('~<em>\s*<section~', $html), preg_match('~color: #999~', $html), preg_match('~<section class="mxr-block"[^>]*>.*?</section>\s*<p>After</p>~s', $html)]);
$withText('<p><i>Before {mucronixreviews} after</i></p><p>Next</p>');
$html = page($guest, $main);
check('shortcode: a block in a sentence goes after the paragraph', [1, 0], [preg_match('~<p><i>Before\s+after</i></p>\s*<section class="mxr-block"[^>]*>.*?</section>\s*<p>Next</p>~s', $html), preg_match('~<i>[^<]*<section~', $html)]);
$long = str_repeat('A long paragraph of plain text. ', 200);
$withText("<p>$long{mucronixreviews}</p>");
$html = page($guest, $main);
check('shortcode: a long paragraph', [false, true], [str_contains($html, '{mucronixreviews'), str_contains($html, '<section class="mxr-block"')]);
$withText("{mucronixreviews summary id=$second}");
check('shortcode: of the other article', true, str_contains(page($guest, $main), '2.0 of 5'));
$withText("{mucronixreviews foo}");
check('shortcode: an unknown word is left to be seen', true, str_contains(page($guest, $main), '{mucronixreviews foo}'));

foreach (['state = 0', 'state = 2', 'state = -2', 'access = 3', 'publish_up = UTC_TIMESTAMP() + INTERVAL 1 HOUR'] as $hide) {
    run("UPDATE #__content SET $hide WHERE id = $second");
    $withText("{mucronixreviews id=$second}{mucronixreviews summary id=$second}");
    check("shortcode: nothing of another article with $hide", 'summary0', parts(page($guest, $main)));
    run("UPDATE #__content SET state = 1, access = 1, publish_up = NULL WHERE id = $second");
}

run("UPDATE #__content SET access = 2 WHERE id = $second");
check('shortcode: for whom the article is', ['summary0', 'block1 summary2 form list'], [parts(page($guest, $main)), parts(page($anna, $main))]);
run("UPDATE #__content SET access = 1 WHERE id = $second");

// With the automatic block
plugin(['categories' => [$child]]);

foreach ([
    '{mucronixreviews}' => 'block1 summary1 notice list', '{mucronixreviews summary}' => 'summary1',
    "{mucronixreviews summary id=$main}" => 'summary1', "{mucronixreviews summary id=$second}" => 'block1 summary2 notice list', '<p>No shortcode</p>' => 'block1 summary1 notice list',
] as $text => $expected) {
    $withText($text);
    check("with the automatic block: $text", $expected, parts(page($guest, $main)));
}

plugin(['categories' => [$child], 'position' => 'before']);
$withText('{mucronixreviews}');
check('with the automatic block before the text', 'block1 summary1 notice list', parts(page($guest, $main)));
plugin([]);

// Lists and modules
$withText('{mucronixreviews}<p>Intro</p>');
$guest->get("index.php?option=com_content&view=category&layout=blog&id=$child");
check('shortcode in a blog: the summary', [200, 'summary1'], [$guest->status, parts($guest->body)]);
run("INSERT IGNORE INTO #__content_frontpage (content_id, ordering) VALUES ($main, 1)");
run($content('featured = 1'));
check('shortcode on the featured page: the summary', 'summary1', parts($guest->get('index.php?option=com_content&view=featured')));
run($content('featured = 0'));
run("DELETE FROM #__content_frontpage WHERE content_id = $main");
$guest->get("index.php?option=com_content&view=category&id=$child&format=feed&type=rss");
check('shortcode in a feed: nothing', [200, true, false, 'summary0'], [$guest->status, str_contains($guest->body, 'Intro'), str_contains($guest->body, '{mucronixreviews'), parts($guest->body)]);
check('shortcode outside the pages of the site: nothing', 'a  b', inside('$i = (object) ["text" => "a {mucronixreviews} b", "id" => ' . $main . ', "catid" => 2]; Joomla\CMS\Plugin\PluginHelper::importPlugin("content"); $app->getDispatcher()->dispatch("onContentPrepare", new Joomla\CMS\Event\Content\ContentPrepareEvent("onContentPrepare", ["context" => "com_content.article", "subject" => $i, "params" => new Joomla\Registry\Registry(), "page" => 0])); echo $i->text;'));
check('shortcode in the index: nothing', 'a  b', inside('$i = (object) ["text" => "a {mucronixreviews} b"]; Joomla\CMS\Plugin\PluginHelper::importPlugin("content"); $app->getDispatcher()->dispatch("onContentPrepare", new Joomla\CMS\Event\Content\ContentPrepareEvent("onContentPrepare", ["context" => "com_finder.indexer", "subject" => $i, "params" => new Joomla\Registry\Registry(), "page" => 0])); echo $i->text;'));

$module = function (string $type, array $params, string $content = '') use ($db): int {
    $json = $db->real_escape_string(json_encode($params));
    $content = $db->real_escape_string($content);
    run("INSERT INTO #__modules (title, note, content, ordering, position, published, module, access, showtitle, params, client_id, language) VALUES ('M', 'mxr', '$content', 1, 'sidebar-right', 1, '$type', 1, 0, '$json', 0, '*')");
    $id = (int) $db->insert_id;
    run("INSERT INTO #__modules_menu (moduleid, menuid) VALUES ($id, 0)");

    return $id;
};
$without = function (int $id): void {
    run("DELETE FROM #__modules WHERE id = $id");
    run("DELETE FROM #__modules_menu WHERE moduleid = $id");
};
$withText('<p>Text of main</p>');
$login = 'index.php?option=com_users&view=login';
$id = $module('mod_custom', ['prepare_content' => 1], '<p>{mucronixreviews}</p>');
check('module without id: the article of the page', 'block1 summary1 notice list', parts(page($guest, $main)));
check('module without id: nothing elsewhere', ['summary0', false], [parts($guest->get($login)), str_contains($guest->body, '{mucronixreviews')]);
plugin(['categories' => [$child]]);
check('module and automatic block: one', 'block1 summary1 notice list', parts(page($guest, $main)));
plugin([]);
$without($id);
$id = $module('mod_custom', ['prepare_content' => 1], "{mucronixreviews id=$main}");
$html = $guest->get($login);
check('module with id, elsewhere', 'block1 summary1 notice list', parts($html));
check('module with id: comes back to the page of the module', 1, preg_match('~/component/users/login~', base64_decode(urldecode(preg_match('~mxr-notice"><a href="[^"]*return=([^&"]+)"~', $html, $m) ? $m[1] : ''))));
$without($id);
$id = $module('mod_custom', ['prepare_content' => 0], '{mucronixreviews}');
check('module that is not prepared', 'summary0', parts(page($guest, $main)));
$without($id);
run("UPDATE #__content SET introtext = '{mucronixreviews}<p>S</p>' WHERE id = $second");

foreach (['mod_articles_news' => ['catid' => [$other], 'count' => 5], 'mod_articles' => ['mode' => 'normal', 'catid' => [$other], 'count' => 5, 'show_introtext' => 1, 'item_heading' => 'h4', 'title_only' => 0, 'articles_layout' => 0]] as $type => $params) {
    $id = $module($type, $params);
    plugin(['categories' => [$child, $other]]);
    $html = page($guest, $main);
    check("$type: summaries, and the block of the page alone", ['block1', true], [preg_match('~block\d+~', parts($html), $m) ? $m[0] : '', substr_count($html, 'class="mxr-summary"') >= 1]);
    plugin([]);
    $html = page($guest, $second);
    check("$type: the article of the page keeps its block", 'block1', preg_match('~block\d+~', parts($html), $m) ? $m[0] : '');
    $without($id);
}

run("UPDATE #__content SET introtext = '<p>S</p>' WHERE id = $second");
$withText('{mucronixreviews}');
run($content('state = 2'));
check('shortcode on the page of an archived article', 'block1 summary1 list', parts(page($anna, $main)));
run($content('state = 1'));

// ---------------------------------------------------------------- an override of a layout
$override = "$site/templates/cassiopeia/html/layouts/com_mucronixreviews";
@mkdir($override, 0777, true);
file_put_contents("$override/summary.php", '<?php echo "<em>OVERRIDE " . (int) $displayData["ratings"] . "</em>";');
check('a layout of the template', true, str_contains(page($guest, $main), '<em>OVERRIDE 1</em>'));
unlink("$override/summary.php");
rmdir($override);
check('and without it', 'block1 summary1 notice list', parts(page($guest, $main)));

// ---------------------------------------------------------------- the cache
$conf = file_get_contents("$site/configuration.php");
file_put_contents("$site/configuration.php", preg_replace('~public \$caching = .*?;~', 'public $caching = 1;', $conf));
// OPcache looks at a changed file after two seconds
sleep(3);
wipe();
$withText('<p>Cached</p>{mucronixreviews}');
$first = page(new Client(), $main);
run("UPDATE #__content SET introtext = '<p>Changed</p>{mucronixreviews}' WHERE id = $main");
check('cache: it is on', true, str_contains(page(new Client(), $main), 'Cached'));
$cached = page(new Client(), $main);
check('cache: the cached page is whole', ['block1 summary1 notice', 1, 1], [parts($cached), preg_match('~com_mucronixreviews/css/reviews(\.min)?\.css~', $cached), preg_match('~com_mucronixreviews/js/reviews(\.min)?\.js~', $cached)]);
$cachingUser = (new Client())->login('vasyl');
[$status] = $cachingUser->review(['context' => 'com_content.article', 'item_id' => $main, 'rating' => 3]);
check('cache: a review cleans it', [200, true], [$status, str_contains(page(new Client(), $main), '3.0 of 5')]);
action((new Client())->loginAdmin(), 'reject', [(int) one('SELECT id FROM #__mucronixreviews_reviews')]);
check('cache: a moderator cleans it', true, str_contains(page(new Client(), $main), 'No ratings yet'));
check('cache: a user never gets the page of a guest', 'block1 summary1 form', parts(page($cachingUser, $main)));
page(new Client(), $main);
run("UPDATE #__content SET introtext = '<p>Third</p>{mucronixreviews}' WHERE id = $main");
$testUser = (new Client())->login('anna');
$testUser->review(['item_id' => 1]);
check('cache: reviews of other objects leave it', false, str_contains(page(new Client(), $main), 'Third'));
file_put_contents("$site/configuration.php", $conf);
sleep(3);

// ---------------------------------------------------------------- the deletion of an article
wipe();
reviewOf($main, 'anna', 5);
reviewOf($main, 'boris', 4, 'x', 0);
reviewOf($second, 'anna', 3);
run("INSERT INTO #__mucronixreviews_reviews (context, item_id, user_id, rating, state, created) VALUES ('com_test.item', $main, {$ids['anna']}, 2, 1, NOW())");
$recount();
$admin3 = (new Client())->loginAdmin();
$articles = function (string $task, int $id) use ($admin3): void {
    $admin3->get('administrator/index.php?option=com_content&view=articles&filter[published]=*');
    $admin3->request('POST', 'administrator/index.php?option=com_content&view=articles', ['task' => "articles.$task", 'boxchecked' => 1, 'cid' => [$id], $admin3->token => 1]);
};
$articles('trash', $main);
check('deletion: the trash keeps the reviews', ['-2', '2'], [(string) one("SELECT state FROM #__content WHERE id = $main"), (string) one("SELECT COUNT(*) FROM #__mucronixreviews_reviews WHERE context = 'com_content.article' AND item_id = $main")]);
$articles('delete', $main);
check('deletion: the article is gone', '0', (string) one("SELECT COUNT(*) FROM #__content WHERE id = $main"));
check('deletion: its reviews and statistics are gone', ['0', null], [(string) one("SELECT COUNT(*) FROM #__mucronixreviews_reviews WHERE context = 'com_content.article' AND item_id = $main"), stats($main, 'com_content.article')]);
check('deletion: the others stay', ['1', '1'], [(string) one("SELECT COUNT(*) FROM #__mucronixreviews_reviews WHERE context = 'com_content.article' AND item_id = $second"), (string) one("SELECT COUNT(*) FROM #__mucronixreviews_reviews WHERE context = 'com_test.item'")]);

// A deleted contact with the id of an article is not the article
inside('Joomla\CMS\Plugin\PluginHelper::importPlugin("content"); $app->getDispatcher()->dispatch("onContentAfterDelete", new Joomla\CMS\Event\Model\AfterDeleteEvent("onContentAfterDelete", ["context" => "com_contact.contact", "subject" => (object) ["id" => ' . $second . ']]));');
check('deletion: of something else', '1', (string) one("SELECT COUNT(*) FROM #__mucronixreviews_reviews WHERE context = 'com_content.article' AND item_id = $second"));
run("UPDATE #__extensions SET enabled = 0 WHERE element = 'com_mucronixreviews'");
inside('Joomla\CMS\Plugin\PluginHelper::importPlugin("content"); $app->getDispatcher()->dispatch("onContentAfterDelete", new Joomla\CMS\Event\Model\AfterDeleteEvent("onContentAfterDelete", ["context" => "com_content.article", "subject" => (object) ["id" => ' . $second . ']]));');
run("UPDATE #__extensions SET enabled = 1 WHERE element = 'com_mucronixreviews'");
check('deletion: also while the component is disabled', '0', (string) one("SELECT COUNT(*) FROM #__mucronixreviews_reviews WHERE context = 'com_content.article' AND item_id = $second"));

// The administrator knows the articles
$third = article('Third article', $other, '<p>Third</p>');
reviewOf($third, 'anna', 5);
$body = $admin3->get('administrator/index.php?option=com_mucronixreviews&view=reviews&filter[state]=');
check('list of the administrator: the article', [1, true], [preg_match('~href="http://127\.0\.0\.1:8097/(?!administrator)[^"]*third-article[^"]*" target="_blank"[^>]*>Third article~', $body), str_contains($body, 'Articles')]);
$body = $admin3->get('administrator/index.php?option=com_plugins&task=plugin.edit&extension_id=' . one("SELECT extension_id FROM #__extensions WHERE element = 'mucronixreviews' AND folder = 'content'"));
check('options of the plugin: only the way to the options of the component', [200, 0, true, false], [$admin3->status, preg_match('~PLG_CONTENT_MUCRONIXREVIEWS_~', $body), str_contains($body, 'href="index.php?option=com_config&amp;view=component&amp;component=com_mucronixreviews"'), str_contains($body, 'jform[params][categories]')]);
$admin3->get('administrator/index.php?option=com_plugins&task=plugin.cancel');
run("DELETE FROM #__modules WHERE note = 'mxr'");
plugin([]);

require __DIR__ . '/stage4.inc.php';
