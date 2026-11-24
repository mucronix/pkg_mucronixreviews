<?php

/**
 * Stage 2: the administrator. Included by drive.php, the package and the test plugin are installed.
 */

/** Sets the rights of the group Manager on the component, the others stay denied */
function rights(array $actions): void
{
    global $db;

    $rules = [];

    foreach (['core.admin', 'core.options', 'core.manage', 'core.delete', 'core.edit', 'core.edit.state'] as $action) {
        $rules[$action] = [6 => in_array($action, $actions, true) ? 1 : 0];
    }

    $json = $db->real_escape_string(json_encode($rules));
    run("UPDATE #__assets SET rules = '$json' WHERE name = 'com_mucronixreviews'");
    check('asset of the component exists', '1', (string) one("SELECT COUNT(*) FROM #__assets WHERE name = 'com_mucronixreviews'"));
}

/** The ids in the list, in its order */
function listed(Client $client, string $query): array
{
    $body = $client->get('administrator/index.php?option=com_mucronixreviews&view=reviews' . $query);

    if ($client->status !== 200) {
        return ['status ' . $client->status];
    }

    preg_match_all('~name="cid\[\]" value="(\d+)"~', $body, $m);

    return array_map('intval', $m[1]);
}

function message(Client $client): string
{
    $body = html_entity_decode($client->get('administrator/index.php?option=com_mucronixreviews&view=reviews'));

    preg_match('~"joomla\.messages":\[(.*?)\]\s*[,}]~s', $body, $m);

    return $m[1] ?? (preg_match('~<(?:joomla-alert|div class="alert)[^>]*>(.*?)</~s', $body, $a) ? strip_tags($a[1]) : $body);
}

function review(int $id): string
{
    return implode('|', array_map(fn ($v) => $v ?? 'NULL', rows("SELECT rating, text, state, username, modified FROM #__mucronixreviews_reviews WHERE id = $id")[0] ?? ['gone']));
}

/** Ten reviews: 1-3 published, 4-5 pending, 6 rejected, 7 spam, 8 trash, 9 of a deleted account, 10 of a broken object */
$prepare = function () use (&$ids) {
    wipe();
    $rows = [
        [1, 'com_test.item', 1, 'anna', 5, "'Best one. Long text with a needle in it'", 1, '01'],
        [2, 'com_test.item', 1, 'boris', 4, "'Only a text'", 1, '02'],
        [3, 'com_test.item', 2, 'anna', 3, 'NULL', 1, '03'],
        [4, 'com_test.item', 1, 'avtor', 2, "'<script>alert(1)</script> <b>bold</b> 100%'", 0, '04'],
        [5, 'com_test.other', 7, 'anna', 1, 'NULL', 0, '05'],
        [6, 'com_test.item', 3, 'anna', 2, "'Rejected'", -1, '06'],
        [7, 'com_test.item', 4, 'anna', 5, "'Spam'", -3, '07'],
        [8, 'com_test.item', 5, 'anna', 4, "'Trash'", -2, '08'],
        [9, 'com_gone.item', 9, null, 4, "'Of nobody'", 1, '09'],
        [10, 'com_test.item', 15, 'boris', 4, "'Broken'", 1, '10'],
        [11, 'com_test.item', 16, 'boris', 4, 'NULL', 1, '11'],
    ];

    foreach ($rows as [$id, $context, $item, $user, $rating, $text, $state, $day]) {
        run("INSERT INTO #__mucronixreviews_reviews (id, context, item_id, user_id, username, rating, text, state, created) VALUES ($id, '$context', $item, " . ($user ? $ids[$user] : 'NULL') . ", 'stored-" . ($user ?? 'ghost') . "', $rating, $text, $state, '2026-01-$day 12:00:00')");
    }

    inside('$service->recalculateAll();');
};

$super = (new Client())->loginAdmin();
$ids['mod'] = user('moder', 6);
$ids['boris'] = (int) one("SELECT id FROM #__users WHERE username = 'boris'");
options([]);

// ---------------------------------------------------------------- an empty list
wipe();
$body = html_entity_decode($super->get('administrator/index.php?option=com_mucronixreviews'));
check('empty list', [200, true, false], [$super->status, str_contains($body, 'No reviews yet'), str_contains($body, 'reviews.publish')]);
check('empty list: no untranslated string', 0, preg_match('~COM_MUCRONIXREVIEWS_\w+~', $body));

// ---------------------------------------------------------------- the list
$prepare();
$body = $super->get('administrator/index.php?option=com_mucronixreviews&view=reviews&filter[state]=');
check('list: no untranslated string', 0, preg_match('~COM_MUCRONIXREVIEWS_(?!\w*["\']?:)~', $body));
check('list: without spam and trash, the newest first', [11, 10, 9, 6, 5, 4, 3, 2, 1], listed($super, '&filter[state]='));
check('list: stars', [true, true], [str_contains($body, '★★★★★'), str_contains($body, '★☆☆☆☆')]);
check('list: stars for a screen reader', true, str_contains($body, '1 of 5'));
check('list: stars only', true, str_contains($body, 'Stars Only'));
check('list: HTML of a review is escaped', [false, true], [str_contains($body, '<script>alert(1)</script>'), str_contains($body, '&lt;script&gt;alert(1)&lt;/script&gt;')]);
check('list: HTML of an object is escaped', [false, true], [str_contains($body, 'Item <i>'), str_contains($body, 'Item &lt;i&gt;1')]);
check('list: HTML of a type is escaped', [false, true], [str_contains($body, 'Test <b>items'), str_contains($body, 'Test &lt;b&gt;items')]);
check('list: link to the object', true, str_contains($body, 'href="http://127.0.0.1:8097/index.php?item=1" target="_blank"'));
check('list: no script as a link', false, stripos($body, 'href="javascript:alert'));
check('list: an object without its adapter', [true, true], [str_contains($body, '#9'), str_contains($body, 'com_gone.item')]);
check('list: a broken object', true, str_contains($body, '#15'));
check('list: present username', [true, false], [(bool) preg_match('~>\s*anna\s*<~', $body), str_contains($body, 'stored-anna')]);
check('list: deleted account', [true, true], [str_contains($body, 'stored-ghost'), str_contains($body, 'account deleted')]);

run("UPDATE #__users SET id = id + 100000 WHERE username = 'avtor'");
check('list: an account deleted while the plugin was off', true, str_contains($super->get('administrator/index.php?option=com_mucronixreviews&view=reviews&filter[state]='), 'stored-avtor'));
run("UPDATE #__users SET id = id - 100000 WHERE username = 'avtor'");

foreach ([
    '[state]=1' => [11, 10, 9, 3, 2, 1], '[state]=0' => [5, 4], '[state]=-1' => [6], '[state]=-3' => [7], '[state]=-2' => [8],
    '[state]=*' => [11, 10, 9, 8, 7, 6, 5, 4, 3, 2, 1], '[context]=com_test.other' => [5], '[context]=com_gone.item' => [9], '[context]=x' => [],
    '[rating]=4' => [11, 10, 9, 2], '[user_id]=' . $ids['anna'] => [6, 5, 3, 1], '[with_text]=1' => [10, 9, 6, 4, 2, 1],
    '[search]=needle' => [1], '[search]=long needle' => [1], '[search]=Best' => [1], '[search]=boris' => [11, 10, 2], '[search]=stored-ghost' => [9],
    '[search]=stored-boris' => [11, 10, 2], '[search]=id:4' => [4], '[search]=ID: 4' => [4], '[search]=100%' => [4], '[search]=_' => [], "[search]='" => [], '[search]=id:abc' => [],
] as $filter => $expected) {
    [$name, $value] = explode('=', $filter, 2);
    check("filter $filter", $expected, listed($super, '&filter' . $name . '=' . urlencode($value)));
}

run("UPDATE #__users SET username = 'renamed' WHERE username = 'boris'");
check('search for a present username that is not the stored one', [11, 10, 2], listed($super, '&filter[state]=&filter[search]=renamed'));
run("UPDATE #__users SET username = 'boris' WHERE username = 'renamed'");
check('filters together', [4], listed($super, '&filter[state]=0&filter[with_text]=1&filter[rating]=2'));
check('filter for all states, a search', [8], listed($super, '&filter[state]=*&filter[search]=Trash'));
check('filter that is not one', [200], [(function () use ($super) { listed($super, "&filter[state]=1'--"); return $super->status; })()]);

foreach ([
    'a.rating ASC' => [5, 4, 6, 3, 2, 9, 10, 11, 1], 'a.rating DESC' => [1, 11, 10, 9, 2, 3, 6, 4, 5], 'a.created ASC' => [1, 2, 3, 4, 5, 6, 9, 10, 11],
    'a.id DESC' => [11, 10, 9, 6, 5, 4, 3, 2, 1], 'a.state ASC' => [6, 4, 5, 1, 2, 3, 9, 10, 11], 'a.context ASC' => [9, 1, 2, 4, 3, 6, 10, 11, 5],
    'author ASC' => [1, 3, 5, 6, 4, 2, 10, 11, 9],
] as $order => $expected) {
    check("order $order", $expected, listed($super, '&filter[state]=&list[fullordering]=' . urlencode($order)));
}

check('no order by a title', [11, 10, 9, 6, 5, 4, 3, 2, 1], listed($super, '&filter[state]=&list[fullordering]=' . urlencode('a.title DESC')));
check('order that is not one', 9, count(listed($super, '&filter[state]=&list[fullordering]=' . urlencode('a.id;DROP ASC'))));
check('page', [11, 10, 9, 6, 5], listed($super, '&filter[state]=&list[fullordering]=' . urlencode('a.id DESC') . '&list[limit]=5&limitstart=0'));
check('second page', [4, 3, 2, 1], listed($super, '&filter[state]=&list[fullordering]=' . urlencode('a.id DESC') . '&list[limit]=5&limitstart=5'));
listed($super, '&filter[state]=&list[fullordering]=' . urlencode('a.created DESC') . '&list[limit]=25&limitstart=0');

// ---------------------------------------------------------------- the actions
check('publish: back to the list', 303, action($super, 'publish', [4, 5]));
check('publish: message', true, str_contains(message($super), '2 reviews published.'));
check('publish: states', ['1', '1'], array_column(rows('SELECT state FROM #__mucronixreviews_reviews WHERE id IN (4, 5)'), 'state'));
check('publish: counted', '3 3 11 3.67 0 1 0 1 1', stats());

foreach (['pending' => [0, '1 review moved to pending.'], 'reject' => [-1, '1 review rejected.'], 'spam' => [-3, '1 review marked as spam.'], 'trash' => [-2, '1 review moved to the trash.'], 'publish' => [1, '1 review published.']] as $task => [$state, $text]) {
    action($super, $task, [4]);
    check("$task: message", true, str_contains(message($super), $text));
    check("$task: state", (string) $state, (string) one('SELECT state FROM #__mucronixreviews_reviews WHERE id = 4'));
    check("$task: counted", $state === 1 ? '3 3 11 3.67 0 1 0 1 1' : '2 2 9 4.50 0 0 0 1 1', stats());
}

action($super, 'publish', [4]);
check('publish again: message', true, str_contains(message($super), 'No reviews published.'));
action($super, 'publish', []);
check('nothing chosen', true, str_contains(message($super), 'make a selection'));
action($super, 'publish', ["4' OR 1=1", 'x', -4, 0]);
check('odd ids change nothing', '1', (string) one('SELECT state FROM #__mucronixreviews_reviews WHERE id = 4'));

foreach (['unpublish', 'archive', 'report', 'setState', 'saveorder', 'checkin', 'feature'] as $task) {
    $before = rows('SELECT * FROM #__mucronixreviews_reviews ORDER BY id');
    action($super, $task, [1]);
    check("no task $task", $before, rows('SELECT * FROM #__mucronixreviews_reviews ORDER BY id'));
}

$super->get('administrator/index.php?option=com_mucronixreviews&view=reviews');
$super->request('GET', 'administrator/index.php?option=com_mucronixreviews&task=reviews.trash&cid[]=1');
check('an action without a token', '1', (string) one('SELECT state FROM #__mucronixreviews_reviews WHERE id = 1'));

foreach (['trash', 'delete', 'recalculate'] as $task) {
    action($super, $task, [1, 8], false);
}

check('no action without a token', ['1', '-2'], [(string) one('SELECT state FROM #__mucronixreviews_reviews WHERE id = 1'), (string) one('SELECT state FROM #__mucronixreviews_reviews WHERE id = 8')]);

// Deleting
$body = $super->get('administrator/index.php?option=com_mucronixreviews&view=reviews&filter[state]=1');
check('no deleting outside the trash', [false, true], [str_contains($body, 'reviews.delete'), str_contains($body, 'reviews.trash')]);
$body = $super->get('administrator/index.php?option=com_mucronixreviews&view=reviews&filter[state]=-2');
check('deleting in the trash', [true, false], [str_contains($body, 'reviews.delete'), str_contains($body, 'reviews.trash')]);
action($super, 'delete', [1, 7, 8]);
check('deleted: only what was in the trash', [1, 7], array_map('intval', array_column(rows('SELECT id FROM #__mucronixreviews_reviews WHERE id IN (1, 7, 8) ORDER BY id'), 'id')));
check('deleted: message', true, str_contains(message($super), '1 review deleted permanently.'));
action($super, 'delete', [1]);
check('deleted: nothing', true, str_contains(message($super), 'No reviews deleted'));

// Recalculating
run("UPDATE #__mucronixreviews_stats SET rating_count = 99 WHERE item_id = 1 AND context = 'com_test.item'");
action($super, 'recalculate', []);
check('recalculated', '3 3 11 3.67 0 1 0 1 1', stats());
check('recalculated: message', 1, preg_match('~Statistics recalculated for \d+ objects \(all~', message($super)));

// ---------------------------------------------------------------- the form
$prepare();
$open = function (Client $client, int $id): string {
    return $client->get("administrator/index.php?option=com_mucronixreviews&task=review.edit&id=$id");
};
$store = function (Client $client, int $id, array $fields, string $task = 'review.save') {
    $client->request('POST', "administrator/index.php?option=com_mucronixreviews&layout=edit&id=$id", ['task' => $task, 'jform' => $fields + ['id' => $id, 'rating' => '5', 'text' => 'Text', 'state' => '1'], $client->token => 1]);
    $client->get('administrator/index.php?option=com_mucronixreviews&view=reviews');
};

$body = $open($super, 4);
check('form: opens', 200, $super->status);
check('form: no untranslated string', 0, preg_match('~COM_MUCRONIXREVIEWS_(?!\w*["\']?:)~', $body));
check('form: values, escaped', [true, false, false], [str_contains($body, '&lt;script&gt;alert(1)&lt;/script&gt; &lt;b&gt;bold&lt;/b&gt; 100%'), str_contains($body, '<script>alert(1)</script>'), str_contains($body, 'jform[title]')]);
check('form: object, author, no account fields', [true, true, false], [str_contains($body, 'Item &lt;i&gt;1'), (bool) preg_match('~>\s*avtor\s*<~', $body), str_contains($body, 'jform[user_id]')]);
$store($super, 4, ['rating' => '3', 'title' => ' New <b>title</b> ', 'text' => "5 < 10 <i>x</i>\r\nline", 'state' => '1']);
check('form: stored and cleaned, a title sent is nothing', "3|5 < 10 x\nline|1|avtor|NULL", review(4));
check('form: counted', '3 3 12 4.00 0 0 1 1 1', stats());

run("UPDATE #__mucronixreviews_reviews SET modified = '2026-02-02 10:00:00' WHERE id = 1");
$open($super, 1);
$store($super, 1, ['rating' => '1', 'text' => '', 'state' => '0']);
check('form: empty text, the time of the author stays', '1||0|anna|2026-02-02 10:00:00', str_replace('NULL', '', review(1)));
check('form: an empty text is NULL', '1', (string) one('SELECT text IS NULL FROM #__mucronixreviews_reviews WHERE id = 1'));

// What binds an author does not bind an administrator
options(['text_mode' => 'required', 'text_min' => 100, 'text_max' => 200, 'links' => 'forbid', 'allowed_groups' => [2]]);
$open($super, 1);
$store($super, 1, ['text' => 'www.example.com', 'state' => '1']);
check('form: no rules of authors', '5|www.example.com|1', implode('|', array_slice(explode('|', review(1)), 0, 3)));
$open($super, 1);
$store($super, 1, ['text' => str_repeat('я', 16000)]);
check('form: up to 16000 whatever the options say', '16000', (string) one('SELECT CHAR_LENGTH(text) FROM #__mucronixreviews_reviews WHERE id = 1'));
options([]);

foreach ([['rating' => '6'], ['rating' => ''], ['rating' => '4.5'], ['text' => str_repeat('я', 16001)], ['state' => '2'], ['state' => '-5']] as $wrong) {
    $before = review(2);
    $open($super, 2);
    $store($super, 2, $wrong);
    check('form refused: ' . mb_substr(json_encode($wrong, JSON_UNESCAPED_UNICODE), 0, 40), $before, review(2));
}

$open($super, 9);
$store($super, 9, ['rating' => '2']);
check('form: the stored username of a deleted account stays', 'stored-ghost', one('SELECT username FROM #__mucronixreviews_reviews WHERE id = 9'));
check('form: the author is still nobody', '1', (string) one('SELECT user_id IS NULL FROM #__mucronixreviews_reviews WHERE id = 9'));
$open($super, 2);
$store($super, 2, ['user_id' => '1', 'context' => 'x', 'item_id' => '77', 'username' => 'x', 'created' => '2000-01-01', 'reply' => 'x']);
check('form: nothing but its fields', 'com_test.item|1|' . $ids['boris'] . '|boris|2026-01-02 12:00:00|', implode('|', rows('SELECT context, item_id, user_id, username, created, reply FROM #__mucronixreviews_reviews WHERE id = 2')[0]));

$open($super, 777);
check('form: no such review', 404, $super->status);
$super->get('administrator/index.php?option=com_mucronixreviews&task=review.cancel');
$super->get('administrator/index.php?option=com_mucronixreviews&view=review&layout=edit&id=3');
check('form: not without its task', true, str_contains(html_entity_decode($super->body), 'not permitted') || str_contains($super->body, 'reviewList'));
$before = (string) one('SELECT COUNT(*) FROM #__mucronixreviews_reviews');
$super->get('administrator/index.php?option=com_mucronixreviews&task=review.add');
$store($super, 0, ['context' => 'com_test.item', 'item_id' => 50]);
check('no review is created', $before, (string) one('SELECT COUNT(*) FROM #__mucronixreviews_reviews'));
$open($super, 2);
$store($super, 2, [], 'review.save2copy');
check('no review is copied', $before, (string) one('SELECT COUNT(*) FROM #__mucronixreviews_reviews'));
$open($super, 3);
$token = $super->token;
$super->token = str_repeat('a', 32);
$store($super, 3, ['rating' => '1']);
check('form without a token', '3', (string) one('SELECT rating FROM #__mucronixreviews_reviews WHERE id = 3'));
$super->token = $token;

// ---------------------------------------------------------------- the rights
$prepare();
rights(['core.manage']);
$moder = (new Client())->loginAdmin('moder');
$body  = $moder->get('administrator/index.php?option=com_mucronixreviews&view=reviews&filter[state]=-2');
check('manage only: sees the list', [8], listed($moder, '&filter[state]=-2'));
check('manage only: no buttons', [false, false, false, false, false], array_map(fn ($s) => str_contains($body, $s), ['reviews.publish', 'reviews.delete', 'reviews.recalculate', 'task=review.edit', 'component=com_mucronixreviews&amp;']));

$untouched = rows('SELECT * FROM #__mucronixreviews_reviews ORDER BY id');

foreach (['publish', 'pending', 'reject', 'spam', 'trash', 'delete'] as $task) {
    check("manage only: $task is refused", 403, action($moder, $task, [1, 8]));
}

run("UPDATE #__mucronixreviews_stats SET rating_count = 99 WHERE item_id = 2 AND context = 'com_test.item'");
check('manage only: recalculate is refused', 403, action($moder, 'recalculate', []));
check('manage only: not recalculated', '99', explode(' ', stats(2))[0]);
$open($moder, 1);
$store($moder, 1, ['rating' => '1']);
check('manage only: nothing has changed', $untouched, rows('SELECT * FROM #__mucronixreviews_reviews ORDER BY id'));

rights(['core.manage', 'core.edit.state']);
$body = $moder->get('administrator/index.php?option=com_mucronixreviews&view=reviews&filter[state]=-2');
check('state: buttons', [true, true, false, false], array_map(fn ($s) => str_contains($body, $s), ['reviews.publish', 'reviews.recalculate', 'reviews.delete', 'task=review.edit']));
action($moder, 'reject', [1]);
check('state: may reject', '-1', (string) one('SELECT state FROM #__mucronixreviews_reviews WHERE id = 1'));
action($moder, 'recalculate', []);
check('state: may recalculate', '1', explode(' ', stats(2))[0]);
check('state: may not delete', 403, action($moder, 'delete', [8]));
$open($moder, 2);
$store($moder, 2, ['rating' => '1']);
check('state: may not edit', '4', (string) one('SELECT rating FROM #__mucronixreviews_reviews WHERE id = 2'));

rights(['core.manage', 'core.delete']);
$body = $moder->get('administrator/index.php?option=com_mucronixreviews&view=reviews&filter[state]=-2');
check('delete: buttons', [false, true], array_map(fn ($s) => str_contains($body, $s), ['reviews.publish', 'reviews.delete']));
action($moder, 'delete', [8]);
check('delete: may delete', '0', (string) one('SELECT COUNT(*) FROM #__mucronixreviews_reviews WHERE id = 8'));
check('delete: may not set a state', 403, action($moder, 'trash', [1]));

rights(['core.manage', 'core.edit']);
$body = $open($moder, 2);
check('edit: the state is disabled', 1, preg_match('~<select[^>]*id="jform_state"[^>]*disabled~s', $body));
$moder->request('POST', 'administrator/index.php?option=com_mucronixreviews&layout=edit&id=2', ['task' => 'review.save', 'jform' => ['id' => 2, 'rating' => '1', 'text' => 'F'], $moder->token => 1]);
check('edit: may edit, the state stays', '1|F|1', implode('|', array_slice(explode('|', review(2)), 0, 3)));
$open($moder, 4);
$store($moder, 4, ['state' => '1']);
check('edit: a state sent all the same does not count', '0', (string) one('SELECT state FROM #__mucronixreviews_reviews WHERE id = 4'));

rights([]);
check('nothing: no component', ['status 403'], listed($moder, ''));
check('nothing: no menu', false, str_contains($moder->get('administrator/index.php'), 'com_mucronixreviews'));
action($moder, 'publish', [4]);
check('nothing: no action', '0', (string) one('SELECT state FROM #__mucronixreviews_reviews WHERE id = 4'));
rights(['core.manage', 'core.delete', 'core.edit', 'core.edit.state']);
$guest = new Client();
$guest->get('administrator/index.php');
$guest->request('POST', 'administrator/index.php?option=com_mucronixreviews', ['task' => 'reviews.publish', 'cid' => [4], $guest->token => 1]);
check('a guest changes nothing', '0', (string) one('SELECT state FROM #__mucronixreviews_reviews WHERE id = 4'));

// ---------------------------------------------------------------- the menu
$prepare();
$badge = function (Client $client): ?string {
    $body = $client->get('administrator/index.php?option=com_mucronixreviews&view=reviews');

    return preg_match('~filter\[state\]=0"[^>]*>.*?</a>~s', $body, $m) || preg_match('~filter%5Bstate%5D=0"[^>]*>.*?</a>~s', $body, $m)
        ? (preg_match('~<span class="badge">(\d+)</span>~', $m[0], $n) ? $n[1] : '') : null;
};
check('menu: the number', '2', $badge($super));
check('menu: for a moderator', '2', $badge($moder));
action($super, 'publish', [4, 5]);
check('menu: no number at zero', '', $badge($super));
action($super, 'pending', [1, 2, 3]);
check('menu: three', '3', $badge($super));
run("UPDATE #__extensions SET enabled = 0 WHERE element = 'mucronixreviews' AND folder = 'system'");
check('menu: without the plugin', '', $badge($super));
run("UPDATE #__extensions SET enabled = 1 WHERE element = 'mucronixreviews' AND folder = 'system'");
run('ALTER TABLE #__mucronixreviews_reviews RENAME TO #__mucronixreviews_away');
$super->get('administrator/index.php?option=com_content&view=articles');
check('menu: a failure leaves the administrator working', 200, $super->status);
run('ALTER TABLE #__mucronixreviews_away RENAME TO #__mucronixreviews_reviews');
check('menu: the link shows what it says', [3, 2, 1], listed($super, '&filter[state]=0'));
check('menu: the other link shows the usual list', 9, count(listed($super, '&filter[state]=')));
check('menu: items', ['index.php?option=com_config&view=component&component=com_mucronixreviews', 'index.php?option=com_mucronixreviews', 'index.php?option=com_mucronixreviews&view=reviews&filter[state]=', 'index.php?option=com_mucronixreviews&view=reviews&filter[state]=0'],
    array_column(rows("SELECT link FROM #__menu WHERE link LIKE '%mucronixreviews%' AND client_id = 1 ORDER BY link"), 'link'));

// ---------------------------------------------------------------- the service
$prepare();
check('updateReview: no such state', 'refused', inside('try { $service->updateReview(1, 5, "", 2); } catch (InvalidArgumentException $e) { echo "refused"; }'));
check('updateReview: no such review', 'not_found 404', inside('try { $service->updateReview(777, 5, ""); } catch (Mucronix\Component\MucronixReviews\Administrator\Exception\ReviewException $e) { echo $e->getReason(), " ", $e->getCode(); }'));
check('updateReview: rating', 'rating', inside('try { $service->updateReview(1, "9", ""); } catch (Mucronix\Component\MucronixReviews\Administrator\Exception\ReviewException $e) { echo $e->getReason(); }'));
check('updateReview: the state stays', '1', inside('$service->updateReview(1, 2, "b");') . one('SELECT state FROM #__mucronixreviews_reviews WHERE id = 1'));
check('countPending', '2', inside('echo $service->countPending();'));
check('table: does not store', 'refused', inside('$t = $app->bootComponent("com_mucronixreviews")->getMVCFactory()->createTable("Review", "Administrator"); $t->load(1); try { $t->store(); } catch (LogicException $e) { echo "refused"; }'));
check('table: does not delete', 'refused', inside('$t = $app->bootComponent("com_mucronixreviews")->getMVCFactory()->createTable("Review", "Administrator"); try { $t->delete(1); } catch (LogicException $e) { echo "refused"; }'));
