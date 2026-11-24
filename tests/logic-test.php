<?php

/**
 * Checks the parts that need no Joomla: links, cleaning of the text, the states of an edited review, the options.
 * Not part of the package.
 *
 * Usage: php tests/logic-test.php
 */

\define('_JEXEC', 1);

$service = __DIR__ . '/../src/com_mucronixreviews/admin/src/Service/';

require $service . 'Settings.php';
require $service . 'LinkDetector.php';
require $service . 'TextCleaner.php';
require $service . 'ModerationRules.php';
require $service . 'SchemaGraph.php';
require $service . 'SaveResult.php';
require $service . 'ModeratorMail.php';
require __DIR__ . '/../src/plg_content_mucronixreviews/src/Text/Shortcodes.php';

use Mucronix\Component\MucronixReviews\Administrator\Service\LinkDetector;
use Mucronix\Component\MucronixReviews\Administrator\Service\ModerationRules;
use Mucronix\Component\MucronixReviews\Administrator\Service\ModeratorMail;
use Mucronix\Component\MucronixReviews\Administrator\Service\SaveResult;
use Mucronix\Component\MucronixReviews\Administrator\Service\SchemaGraph;
use Mucronix\Component\MucronixReviews\Administrator\Service\Settings;
use Mucronix\Component\MucronixReviews\Administrator\Service\TextCleaner;
use Mucronix\Plugin\Content\MucronixReviews\Text\Shortcodes;

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
    echo "FAIL  $name\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n";
}

// Links
$links = [
    'http://example.com', 'смотри https://a.b/c', 'HTTPS://EXAMPLE.COM', 'www.something.zz', 'WWW.Example.com',
    'example.com', 'site.org', 'shop.ua', 'купил на shop.com.ua вчера', 'Example.COM', '(example.net)', 'пиши: user@example.com',
    'sub.domain.example.info', 'сайт.рф', 'пример.укр', 'xn--80ak6aa92e.com', 'example.com.', 'example.com/path?x=1', "строка\nexample.io\nстрока",
    'https://1.2.3.4/x', 'my-site.eu', 'http://localhost/a', 'a_b.com', 'example.com_x',
];
$plain = [
    '', 'т.е.', 'и т.д.', '1.5', 'версия 2.0.1', 'Хорошо.Но дорого', 'т.п. и др.', 'Цена 10.50 грн', 'конец. Com начало', 'example. com',
    'просто com', 'файл readme.txt', 'комп.ком', 'a.b.c', 'U.S.A.', 'ok...net', 'http://', 'www.', '.com', 'example.company', 'example.comx', 'слово.уа',
];

foreach ($links as $text) {
    check("link: $text", true, LinkDetector::containsLink($text));
}

foreach ($plain as $text) {
    check("no link: $text", false, LinkDetector::containsLink($text));
}

// Cleaning
$texts = [
    ['<b>Жирный</b> текст', 'Жирный текст'],
    ['5 < 10 и 10 > 5', '5 < 10 и 10 > 5'],
    ['люблю <3 вас', 'люблю <3 вас'],
    ['<scr<b>ipt>alert(1)</script>', 'alert(1)'],
    ['<a href="https://x.com">x</a>', 'x'],
    ["a<br>b<br />c", 'abc'],
    ["один\r\nдва\rтри", "один\nдва\nтри"],
    ["  края  \n", 'края'],
    ["\n\n\n\nа\n\n\n\n\nб", "а\n\nб"],
    ['<!-- <b>x</b> -->y', 'y'],
    ['<?php echo 1; ?>z', 'z'],
    ["nul\0l", 'null'],
    ["таб\tостаётся", "таб\tостаётся"],
    ['эмодзи 😀', 'эмодзи 😀'],
    ["плохой \xC3\x28 utf", 'плохой ?( utf'],
    ['&lt;b&gt;', '&lt;b&gt;'],
    ["\u{00A0}\u{3000}", ''],
    ["\u{200B}\u{FEFF}", ''],
    ["<img src=x\nonerror=alert(1)>", ''],
    ["<scr\0ipt>x", 'x'],
    ["<\u{200B}b>x", 'x'],
    [str_repeat('<a', 40) . str_repeat('>', 40), ''],
];

foreach ($texts as [$in, $out]) {
    check('text: ' . json_encode($in, JSON_INVALID_UTF8_SUBSTITUTE), $out, TextCleaner::text($in));
}

// However many tags are nested, none is left
$deep = TextCleaner::text(str_repeat('<a', 3000) . str_repeat('>', 3000));
check('text: a deep nest leaves no tag', 0, preg_match('~<[a-z/!?][^<>]*>~i', $deep));

$start = microtime(true);
TextCleaner::text(str_repeat('<a', 30000) . str_repeat('>', 5000));
LinkDetector::containsLink(str_repeat('a.', 8000));
LinkDetector::containsLink(str_repeat('a-', 8000));
check('a hostile text takes under a second', true, microtime(true) - $start < 1);

// States of a new review: moderation mode, links mode, has content, has links => state
$new = [
    ['all', 'allow', false, false, 0], ['all', 'allow', true, false, 0],
    ['text', 'allow', false, false, 1], ['text', 'allow', true, false, 0],
    ['none', 'allow', false, false, 1], ['none', 'allow', true, false, 1],
    ['none', 'moderate', true, true, 0], ['none', 'moderate', true, false, 1], ['none', 'allow', true, true, 1],
    ['text', 'moderate', true, true, 0],
];

foreach ($new as [$mode, $linksMode, $content, $hasLinks, $state]) {
    $settings = new Settings(['moderation' => $mode, 'links' => $linksMode]);
    check("new: $mode/$linksMode " . json_encode([$content, $hasLinks]), $state, ModerationRules::stateOfNew($settings, $content, $hasLinks));
}

// States of an edited review: moderation, links, was, content changed, has links => state
$edited = [
    ['all', 'allow', 1, false, false, 1], ['text', 'allow', 1, false, false, 1], ['none', 'allow', 1, false, false, 1],
    ['all', 'allow', 1, true, false, 0], ['text', 'allow', 1, true, false, 0], ['none', 'allow', 1, true, false, 1],
    ['none', 'moderate', 1, true, true, 0], ['none', 'moderate', 1, false, true, 1], ['none', 'allow', 1, true, true, 1],
];

foreach (['all', 'text', 'none'] as $mode) {
    foreach ([0 => 0, -1 => 0, -2 => 0, -3 => -3] as $was => $now) {
        $edited[] = [$mode, 'allow', $was, false, false, $was];
        $edited[] = [$mode, 'allow', $was, true, false, $now];
    }
}

foreach ($edited as [$mode, $linksMode, $was, $changed, $hasLinks, $state]) {
    $settings = new Settings(['moderation' => $mode, 'links' => $linksMode]);
    check("edited: $mode/$linksMode " . json_encode([$was, $changed, $hasLinks]), $state, ModerationRules::stateOfEdited($settings, $was, $changed, $hasLinks));
}

// Defaults: an option that was never saved
$settings = new Settings([]);
check('default mode', true, $settings->hasContent());
check('default categories', [], $settings->articleCategories());
check('default position', 'after', $settings->articlePosition());
check('default show', 'full', $settings->articleShow());
check('default text', 'optional', $settings->textMode());
check('default min', 20, $settings->textMin());
check('default max', 3000, $settings->textMax());
check('default groups', [2], $settings->allowedGroups());
check('default edit', true, $settings->allowEdit());
check('default user delete', false, $settings->deleteWithUser());
check('default moderation', 'text', $settings->moderation());
check('default links', 'moderate', $settings->links());
check('default limit', 10, $settings->dailyLimit());
check('default rounding', 'half', $settings->rounding());
check('default show date', true, $settings->showDate());
check('default date format', 'DATE_FORMAT_LC3', $settings->dateFormat());
check('default page', 10, $settings->listLimit());
check('default order', 'newest', $settings->listOrder());
check('default schema', [], $settings->schemaContexts());
check('default keep', true, $settings->keepData());

$stars = new Settings(['mode' => 'stars', 'text_mode' => 'required']);
check('stars: text off', 'off', $stars->textMode());

// What cannot be, for example edited in the database, becomes the default and never something looser
$odd = new Settings(['mode' => 'x', 'moderation' => 'x', 'links' => 'x', 'text_max' => '0', 'text_min' => '-5', 'daily_limit' => 'abc', 'list_limit' => '0', 'allowed_groups' => 'x', 'list_order' => 'x']);
check('odd mode', true, $odd->hasContent());
check('odd moderation', 'text', $odd->moderation());
check('odd links', 'moderate', $odd->links());
check('odd max', 3000, $odd->textMax());
check('odd min', 20, $odd->textMin());
check('odd limit', 10, $odd->dailyLimit());
check('odd page', 10, $odd->listLimit());
check('odd groups', [], $odd->allowedGroups());
check('odd order', 'newest', $odd->listOrder());
check('odd categories', [3, 7], (new Settings(['article_categories' => ['3', 'x', '0', '-2', 7]]))->articleCategories());
check('one category', [5], (new Settings(['article_categories' => '5']))->articleCategories());
check('odd position', 'after', (new Settings(['article_position' => 'x']))->articlePosition());
check('position before', 'before', (new Settings(['article_position' => 'before']))->articlePosition());
check('odd show', 'full', (new Settings(['article_show' => 'x']))->articleShow());
check('show summary', 'summary', (new Settings(['article_show' => 'summary']))->articleShow());
check('show list', 'list', (new Settings(['article_show' => 'list']))->articleShow());
check('zero limit is no limit', 0, (new Settings(['daily_limit' => '0']))->dailyLimit());
check('max is capped', 16000, (new Settings(['text_max' => '99999']))->textMax());

// Shortcodes: B stands for a block, S for a summary, with the arguments the plugin gets
$calls  = [];
$render = function (string $arguments) use (&$calls): array {
    $calls[] = $arguments;
    $isBlock = !str_contains($arguments, 'summary');

    return [$isBlock ? '<section>B' . trim($arguments) . '</section>' : '<span>S' . trim($arguments) . '</span>', $isBlock];
};
$codes = [
    'alone'                   => ['<p>{mucronixreviews}</p>', '<section>B</section>'],
    'alone, attributes'       => ['<p class="lead" style="color:red">{mucronixreviews id=5}</p>', '<section>Bid=5</section>'],
    'alone, emphasis'         => ['<p><em>{mucronixreviews}</em></p>', '<section>B</section>'],
    'alone, nested'           => ['<p><span style="color:#999"><strong><i>{mucronixreviews summary id=3}</i></strong></span></p>', '<span>Ssummary id=3</span>'],
    'alone, spaces'           => ["<p>&nbsp; {mucronixreviews}\u{A0}&#160;<br />\n</p>", '<section>B</section>'],
    'alone, all the wrappers' => ['<p><b><u><s><small><mark><font color="red">{mucronixreviews}</font></mark></small></s></u></b></p>', '<section>B</section>'],
    'two alone'               => ['<p>{mucronixreviews summary}<br>{mucronixreviews id=2}</p>', '<span>Ssummary</span><section>Bid=2</section>'],
    'upper case'              => ['<P>{MucronixReviews ID=7}</P >', '<section>Bid=7</section>'],
    'block in text'           => ['<p><i>Начало {mucronixreviews id=3} конец</i></p><p>b</p>', '<p><i>Начало  конец</i></p><section>Bid=3</section><p>b</p>'],
    'summary in text'         => ['<p>Оценка: {mucronixreviews summary} из пяти</p>', '<p>Оценка: <span>Ssummary</span> из пяти</p>'],
    'both in text'            => ['<p class="x">a {mucronixreviews} b {mucronixreviews summary}</p>', '<p class="x">a  b <span>Ssummary</span></p><section>B</section>'],
    'other text is kept'      => ['<p>a</p><p>{mucronixreviews summary}</p><p>c</p>', '<p>a</p><span>Ssummary</span><p>c</p>'],
    'a link is text'          => ['<p><a href="/x">{mucronixreviews}</a></p>', '<p><a href="/x"></a></p><section>B</section>'],
    'not a paragraph'         => ['<div class="uk-panel">{mucronixreviews}</div>', '<div class="uk-panel"><section>B</section></div>'],
    'pre is no paragraph'     => ['<pre>{mucronixreviews}</pre>', '<pre><section>B</section></pre>'],
    'unclosed paragraph'      => ['<p>open <div>{mucronixreviews}</div><p>x</p>', '<p>open <div><section>B</section></div><p>x</p>'],
    'paragraph before'        => ['<p>a</p>{mucronixreviews}', '<p>a</p><section>B</section>'],
    'unknown argument'        => ['<p>{mucronixreviews foo}</p>', '<p>{mucronixreviews foo}</p>'],
    'braces of other plugins' => ['<p>{loadposition x} {mucronixreviews}</p>', '<p>{loadposition x} </p><section>B</section>'],
    'no shortcode'            => ['<p>Просто текст</p>', '<p>Просто текст</p>'],
];

foreach ($codes as $name => [$in, $out]) {
    check("shortcode: $name", $out, Shortcodes::replace($in, $render));
}

$calls = [];
Shortcodes::replace('<p>{mucronixreviews}</p> {mucronixreviews summary id=4}', $render);
check('shortcode: each rendered once, in order', ['', ' summary id=4'], $calls);

$output = Shortcodes::replace('<p>{mucronixreviews}</p>', fn (): array => ['<section>A review: {mucronixreviews id=9}</section>', true]);
check('shortcode: what is put in is not read again', '<section>A review: {mucronixreviews id=9}</section>', $output);
check('shortcode: removed with its paragraph', '<p>a</p>', Shortcodes::replace('<p>a</p><p><em>{mucronixreviews}</em></p>', fn (): array => ['', false]));

$start = microtime(true);
$long  = Shortcodes::replace('<p>' . str_repeat('<em>x {mucronix ', 20000) . '{mucronixreviews}</p>' . str_repeat('<p>{', 20000), $render);
check('shortcode: a long text is read', true, str_contains($long, '<section>B</section>'));
check('shortcode: a long text is fast', true, microtime(true) - $start < 1);

$words = str_repeat('Длинный абзац обычного текста. ', 400);
check('shortcode: a long paragraph', '<p>' . $words . '</p><section>B</section>', Shortcodes::replace('<p>' . $words . '{mucronixreviews}</p>', $render));
check('shortcode: broken encoding', "<p>\xFF </p><section>B</section>", Shortcodes::replace("<p>\xFF {mucronixreviews}</p>", $render));
check('shortcode: broken encoding, alone', '<section>B</section>', Shortcodes::replace("<p>{mucronixreviews}\xC2\xA0</p>", $render));

// The graph of Schema.org
$id     = 'http://site/#/schema/com_content/article/5';
$rating = ['aggregateRating' => ['@type' => 'AggregateRating', 'ratingValue' => '4.5'], 'review' => [['@type' => 'Review']]];
$base   = [['@type' => 'Organization', '@id' => 'http://site/#/schema/Organization/base'], ['@type' => 'WebPage', '@id' => 'http://site/#/schema/WebPage/base']];
$graph  = array_merge($base, [['@type' => 'Article', '@id' => $id, 'name' => 'A']]);
$after  = SchemaGraph::attach($graph, $id, $rating);
check('graph: into the node', ['@type' => 'Article', '@id' => $id, 'name' => 'A'] + $rating, $after[2]);
check('graph: the rest untouched', [$base[0], $base[1], 3], [$after[0], $after[1], count($after)]);
check('graph: no node, nothing', $base, SchemaGraph::attach($base, $id, $rating));
check('graph: another node, nothing', $graph, SchemaGraph::attach($graph, $id . '0', $rating));
$own = array_merge($base, [['@type' => 'Article', '@id' => $id, 'aggregateRating' => ['ratingValue' => '2']]]);
check('graph: a rating there already stays alone', $own, SchemaGraph::attach($own, $id, $rating));
$vote = array_merge($graph, [['@isPartOf' => ['@id' => $id, '@type' => 'Product', 'aggregateRating' => ['ratingValue' => '3']]]]);
check('graph: the rating of the vote plugin stays alone', $vote, SchemaGraph::attach($vote, $id, $rating));
$voteOther = array_merge($graph, [['@isPartOf' => ['@id' => $id . '9', 'aggregateRating' => ['ratingValue' => '3']]]]);
check('graph: a vote of another node does not count', true, isset(SchemaGraph::attach($voteOther, $id, $rating)[2]['aggregateRating']));

foreach (['Article', 'BlogPosting', 'Book', 'Recipe', 'Event', 'Product', 'Organization', 'CreativeWork', ['Book', 'Product']] as $type) {
    check('graph: rated type ' . json_encode($type), true, isset(SchemaGraph::attach([['@type' => $type, '@id' => $id]], $id, $rating)[0]['aggregateRating']));
}

foreach (['Person', 'JobPosting', 'WebPage', '', null, ['Person'], 'article', 5] as $type) {
    check('graph: no rating for type ' . json_encode($type), false, isset(SchemaGraph::attach([['@type' => $type, '@id' => $id]], $id, $rating)[0]['aggregateRating']));
}

check('graph: odd entries are skipped', true, isset(SchemaGraph::attach(['x', null, ['@type' => 'Article', '@id' => $id]], $id, $rating)[2]['aggregateRating']));

$page = '<span class="mxr-summary" data-mxr-summary="com_content.article.5"></span><ul class="mxr-list" data-mxr-list="com_content.article.5"></ul>';
check('shown: both', ['summary' => true, 'list' => true], SchemaGraph::shown($page, 'com_content.article.5'));
check('shown: another object', ['summary' => false, 'list' => false], SchemaGraph::shown($page, 'com_content.article.50'));
check('shown: a summary alone', ['summary' => true, 'list' => false], SchemaGraph::shown('<span data-mxr-summary="com_content.article.5">', 'com_content.article.5'));
check('shown: a text that names it is not a mark', ['summary' => false, 'list' => false], SchemaGraph::shown('<p>data-mxr-summary=&quot;com_content.article.5&quot;</p>', 'com_content.article.5'));

// The email to the moderators (since 0.6.0): when a review comes to wait for them
$P = ModerationRules::PUBLISHED;
$W = ModerationRules::PENDING;

foreach ([[null, $W, true], [$P, $W, true], [ModerationRules::REJECTED, $W, true], [ModerationRules::TRASHED, $W, true],
    [$W, $W, false], [null, $P, false], [$P, $P, false], [$W, $P, false], [ModerationRules::SPAM, ModerationRules::SPAM, false],
    [null, ModerationRules::SPAM, false], [ModerationRules::REJECTED, ModerationRules::REJECTED, false]] as [$before, $after, $comes]) {
    check('comes to moderation: ' . json_encode([$before, $after]), $comes, ModerationRules::comesToModeration($before, $after));
}

check('needs a moderator: new and waiting', true, (new SaveResult(1, $W, true))->needsModerator());
check('needs a moderator: waited already', false, (new SaveResult(1, $W, false, $W))->needsModerator());
check('needs a moderator: published again', true, (new SaveResult(1, $W, false, $P))->needsModerator());
check('needs a moderator: published', false, (new SaveResult(1, $P, true))->needsModerator());

$fresh = new Settings([]);
check('notify: on by default', true, $fresh->notify());
check('notify: off', false, (new Settings(['notify' => '0']))->notify());
check('notify: groups by default, Super Users', [8], $fresh->notifyGroups());
check('notify: groups chosen', [3, 6], (new Settings(['notify_groups' => ['3', '6', '3', '0', '']]))->notifyGroups());
check('notify: no group chosen', [], (new Settings(['notify_groups' => []]))->notifyGroups());
check('notify: no address by default', [], $fresh->notifyEmails());
check('notify: hourly by default', 20, $fresh->notifyHourly());
check('notify: hourly 0', 0, (new Settings(['notify_hourly' => '0']))->notifyHourly());
check('notify: hourly wrong', 20, (new Settings(['notify_hourly' => '-1']))->notifyHourly());
check('email list: separators', ['a@x.io', 'b@x.io', 'c@x.io', 'd@x.io'], Settings::emailList(" a@x.io, b@x.io;c@x.io\r\n\n d@x.io "));
check('email list: empty', [], Settings::emailList(" \n , "));
check('notify: addresses each once, as written', ['A@x.io', 'b@x.io'], (new Settings(['notify_emails' => "A@x.io\na@X.io, b@x.io"]))->notifyEmails());
$many = implode(',', array_map(fn ($n) => "m$n@x.io", range(1, 12)));
check('notify: ten addresses at most', 10, count((new Settings(['notify_emails' => $many]))->notifyEmails()));
check('notify: the first ten', 'm10@x.io', (new Settings(['notify_emails' => $many]))->notifyEmails()[9]);

check('excerpt: short stays', 'Хороший товар', ModeratorMail::excerpt("  Хороший\n\nтовар "));
$long = str_repeat('я', 299) . ' ' . str_repeat('б', 10);
check('excerpt: 300 characters and an ellipsis', str_repeat('я', 299) . '…', ModeratorMail::excerpt($long));
check('excerpt: exactly 300 stays', str_repeat('ж', 300), ModeratorMail::excerpt(str_repeat('ж', 300)));

echo $failed ? "\n$failed failed, $passed passed\n" : "all $passed passed\n";
exit($failed ? 1 : 0);
