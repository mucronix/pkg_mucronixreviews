<?php

/**
 * The email to the moderators (since 0.6.0). Included by drive.php, or alone with ONLY=5. The mail of the stand goes
 * to smtp-sink.php, which drive.php starts: every message is a file in <stand>/mail.
 */

/** The messages the stand has sent since the last clear: to, subject, plain text */
function mailbox(): array
{
    global $stand;

    $files = glob("$stand/mail/*.eml") ?: [];
    usort($files, fn ($a, $b) => (int) basename($a) <=> (int) basename($b));
    $mails = [];

    foreach ($files as $file) {
        $raw = str_replace("\r\n", "\n", (string) file_get_contents($file));
        [$head, $body] = explode("\n\n", $raw, 2) + ['', ''];
        $head = preg_replace("~\n[ \t]+~", ' ', $head);

        // The plain part of a message with parts
        if (preg_match('~boundary="?([^";\n]+)~i', $head, $m)) {
            foreach (explode('--' . $m[1], $body) as $part) {
                if (stripos($part, 'text/plain') !== false) {
                    [$partHead, $body] = explode("\n\n", ltrim($part, "\n"), 2) + ['', ''];
                    $head .= "\n" . $partHead;
                    break;
                }
            }
        }

        if (preg_match('~^Content-Transfer-Encoding:\s*quoted-printable~mi', $head)) {
            $body = quoted_printable_decode($body);
        } elseif (preg_match('~^Content-Transfer-Encoding:\s*base64~mi', $head)) {
            $body = base64_decode($body);
        }

        preg_match('~^To:\s*(.*)$~mi', $head, $to);
        preg_match('~^Subject:\s*(.*)$~mi', $head, $subject);
        preg_match_all('~[\w.+-]+@[\w.-]+~', $to[1] ?? '', $addresses);

        $mails[] = ['to' => array_map('strtolower', $addresses[0]), 'subject' => mb_decode_mimeheader(trim($subject[1] ?? '')), 'body' => trim($body)];
    }

    return $mails;
}

function clearMail(): void
{
    global $stand;

    array_map('unlink', glob("$stand/mail/*.eml") ?: []);
}

/** Who got the messages, each address once, sorted */
function mailedTo(array $mails): array
{
    $to = array_unique(array_merge(...array_column($mails, 'to') ?: [[]]));
    sort($to);

    return $to;
}

/** Sets something in configuration.php of the stand */
function config(array $values): void
{
    global $site;

    $conf = file_get_contents("$site/configuration.php");

    foreach ($values as $name => $value) {
        $conf = preg_replace('~public \$' . $name . ' = .*?;~', 'public $' . $name . ' = ' . var_export($value, true) . ';', $conf);
    }

    file_put_contents("$site/configuration.php", $conf);
    sleep(3);
}

$mailIds = [];

// Authors of their own: what the earlier checks did to the other accounts does not matter here
foreach (['mailadmin' => 7, 'mailmanager' => 6, 'mailquiet' => 8, 'mailblocked' => 8, 'mailauthor' => 2, 'manna' => 2, 'mboris' => 2, 'mvera' => 2, 'mgleb' => 2, 'mdina' => 2] as $name => $group) {
    run("DELETE FROM #__users WHERE username = '$name'");
    $mailIds[$name] = user($name, $group);
}

run('DELETE FROM #__user_usergroup_map WHERE user_id NOT IN (SELECT id FROM #__users)');
run("UPDATE #__users SET sendEmail = 1 WHERE username IN ('standadmin', 'mailadmin', 'mailmanager', 'mailblocked')");
run("UPDATE #__users SET block = 1 WHERE username = 'mailblocked'");
$adminMail = strtolower((string) one("SELECT email FROM #__users WHERE username = 'standadmin'"));
$sink      = fn (): array => mailedTo(mailbox());
$authors   = [];

foreach (['anna', 'boris', 'vera', 'gleb', 'dina', 'mailauthor'] as $name) {
    $authors[$name] = (new Client())->login($name === 'mailauthor' ? $name : 'm' . $name);
}

/** A review of one of the authors, after a rest, on an object of its own so that every one is new */
$send = function (string $name, array $fields) use (&$authors): array {
    rest();

    return $authors[$name]->review($fields);
};

// ---------------------------------------------------------------- when
options([]);
wipe();
clearMail();
[$status, $answer] = $send('anna', ['item_id' => 51, 'rating' => 4]);
check('mail: stars, published at once: none', [200, true, []], [$status, $answer['published'] ?? null, mailbox()]);

[$status, $answer] = $send('anna', ['item_id' => 51, 'rating' => 4, 'text' => 'Changed text of anna']);
$mails = mailbox();
check('mail: a published review changed, to moderation: one', [200, false, 1], [$status, $answer['published'] ?? null, count($mails)]);
check('mail: to the Super Users who receive system emails', [$adminMail], $sink());
$mail = $mails[0] ?? ['subject' => '', 'body' => ''];
$id51 = (int) one("SELECT id FROM #__mucronixreviews_reviews WHERE item_id = 51");
check('mail: subject', 'A review waits for moderation on Stand: Item <i>51', $mail['subject']);
check('mail: reason', true, str_contains($mail['body'], 'The author has changed the text of the review.'));
check('mail: object and its address', [true, true], [str_contains($mail['body'], 'Object: Item <i>51'), str_contains($mail['body'], 'http://127.0.0.1:8097/index.php?item=51')]);
check('mail: author and rating', [true, true], [str_contains($mail['body'], 'Author: manna'), str_contains($mail['body'], 'Rating: 4 of 5')]);
check('mail: text', true, str_contains($mail['body'], 'Changed text of anna'));
check('mail: the review in the administrator', true, str_contains($mail['body'], 'http://127.0.0.1:8097/administrator/index.php?option=com_mucronixreviews&task=review.edit&id=' . $id51));
check('mail: the list of the waiting', true, str_contains($mail['body'], 'option=com_mucronixreviews&view=reviews&filter[state]=0'));
check('mail: no address, no name of the author', [false, false], [str_contains($mail['body'], 'manna@stand.invalid'), str_contains($mail['body'], 'Real manna')]);
check('mail: no untranslated string', 0, preg_match('~COM_MUCRONIXREVIEWS_~', $mail['subject'] . $mail['body']));

clearMail();
$send('anna', ['item_id' => 51, 'rating' => 3, 'text' => 'Changed once more']);
check('mail: waiting already, changed again: none', [], mailbox());

clearMail();
$send('boris', ['item_id' => 52, 'rating' => 5, 'text' => 'New text of boris']);
$mails = mailbox();
check('mail: a new review with a text: one, a new one', [1, true], [count($mails), str_contains($mails[0]['body'] ?? '', 'A new review.')]);

// Rejected and trashed come back to moderation with a changed text
foreach ([-1 => 'rejected', -2 => 'trashed'] as $state => $name) {
    run("UPDATE #__mucronixreviews_reviews SET state = $state WHERE item_id = 52");
    clearMail();
    $send('boris', ['item_id' => 52, 'rating' => 5, 'text' => "Back from $name"]);
    check("mail: $name, text changed: one", [1, '0'], [count(mailbox()), (string) one('SELECT state FROM #__mucronixreviews_reviews WHERE item_id = 52')]);
}

run('UPDATE #__mucronixreviews_reviews SET state = -3 WHERE item_id = 52');
clearMail();
$send('boris', ['item_id' => 52, 'rating' => 5, 'text' => 'Spam changed']);
check('mail: spam stays spam: none', [[], '-3'], [mailbox(), (string) one('SELECT state FROM #__mucronixreviews_reviews WHERE item_id = 52')]);

// A link sends even a review "without moderation" to moderation
options(['moderation' => 'none']);
clearMail();
[$status, $answer] = $send('vera', ['item_id' => 53, 'rating' => 2, 'text' => 'Look at example.com']);
check('mail: the review with a link is taken', [200, false], [$status, $answer['published'] ?? $answer]);
$mails = mailbox();
check('mail: a link, without moderation: one, says so', [1, true], [count($mails), str_contains($mails[0]['body'] ?? '', 'The text holds a link.')]);
clearMail();
$send('vera', ['item_id' => 54, 'rating' => 2, 'text' => 'No link here']);
check('mail: without moderation, no link: none', [], mailbox());

// "All to moderation": stars alone wait too
options(['moderation' => 'all']);
clearMail();
$send('gleb', ['item_id' => 55, 'rating' => 1]);
$mails = mailbox();
check('mail: stars alone under "all to moderation": one', [1, true], [count($mails), str_contains($mails[0]['body'] ?? '', '(no text, a rating only)')]);

// A moderator who puts a review back to moderation gets no email about it
$moderator = (new Client())->loginAdmin();
clearMail();
action($moderator, 'publish', [$id51]);
action($moderator, 'pending', [$id51]);
check('mail: a moderator changes the state: none', ['0', []], [(string) one("SELECT state FROM #__mucronixreviews_reviews WHERE id = $id51"), mailbox()]);

// An object whose address has no host gets the host of the site
options([]);
clearMail();
$send('anna', ['item_id' => 63, 'rating' => 4, 'text' => 'Relative address']);
check('mail: an address without the host gets it', true, str_contains(mailbox()[0]['body'] ?? '', "Item <i>63\nhttp://127.0.0.1:8097/index.php?item=63"));

// ---------------------------------------------------------------- to whom
// The rights of the component as Joomla has them: the Manager may change the state, but not open the component
$componentRules = fn (string $json) => run("UPDATE #__assets SET rules = '$json' WHERE name = 'com_mucronixreviews'");
$componentRules('{}');
options(['notify_groups' => [8, 7, 6]]);
clearMail();
$send('dina', ['item_id' => 56, 'rating' => 3, 'text' => 'Text of dina']);
check('mail: the moderators of the groups: not the manager without the right, the blocked, the one without system emails', [$adminMail, 'mailadmin@stand.invalid'], $sink());
check('mail: one message for each address', [1, 1], array_map(fn ($m) => count($m['to']), mailbox()));

$componentRules('{"core.manage":{"6":1}}');
clearMail();
$send('dina', ['item_id' => 61, 'rating' => 3, 'text' => 'The manager may open it']);
check('mail: the manager with both rights gets it', [$adminMail, 'mailadmin@stand.invalid', 'mailmanager@stand.invalid'], $sink());

$componentRules('{"core.manage":{"6":1},"core.edit.state":{"6":0}}');
clearMail();
$send('dina', ['item_id' => 62, 'rating' => 3, 'text' => 'The manager may not change the state']);
// Administrator is a child of Manager: the denial is theirs too, Super Users are above it
check('mail: who may not change the state does not', [$adminMail], $sink());
$componentRules('{}');

// The author is a moderator: no email about the own review
options(['notify_groups' => [8, 7], 'allowed_groups' => [2, 7]]);
$authors['mailadmin'] = (new Client())->login('mailadmin');
clearMail();
$send('mailadmin', ['item_id' => 57, 'rating' => 3, 'text' => 'Text of a moderator']);
check('mail: not to the author', [$adminMail], $sink());

// The address of the Super User from the group and, in capitals, from the list: one email
options(['notify_groups' => [8], 'notify_emails' => "extra@stand.invalid,\n" . strtoupper($adminMail) . "; second@stand.invalid"]);
clearMail();
$send('mailauthor', ['item_id' => 58, 'rating' => 3, 'text' => 'Text for the addresses']);
check('mail: the additional addresses, each once', [$adminMail, 'extra@stand.invalid', 'second@stand.invalid', 3], array_merge($sink(), [count(mailbox())]));

options(['notify_groups' => [], 'notify_emails' => 'mailauthor@stand.invalid']);
clearMail();
$send('mailauthor', ['item_id' => 59, 'rating' => 3, 'text' => 'Text of the author']);
check('mail: not to the author written among the addresses', [], mailbox());

options(['notify' => 0]);
clearMail();
[$status, $answer] = $send('anna', ['item_id' => 60, 'rating' => 3, 'text' => 'Off']);
check('mail: off: none, the review is there', [200, [], '1'], [$status, mailbox(), (string) one('SELECT COUNT(*) FROM #__mucronixreviews_reviews WHERE item_id = 60')]);

// ---------------------------------------------------------------- the limit of an hour
options(['notify_hourly' => 2]);
wipe();
clearMail();

foreach (['anna', 'boris', 'vera', 'gleb'] as $n => $name) {
    $send($name, ['item_id' => 70 + $n, 'rating' => 4, 'text' => "Text $n"]);
}

$subjects = array_column(mailbox(), 'subject');
check('mail: the limit of an hour, two, then one about more, then none', ['A review waits for moderation on Stand: Item <i>70', 'A review waits for moderation on Stand: Item <i>71', 'More than 2 reviews wait for moderation on Stand'], $subjects);
check('mail: about more, the list', true, str_contains(mailbox()[2]['body'] ?? '', 'filter[state]=0'));

// Moderated: the hour counts the waiting ones only
run('UPDATE #__mucronixreviews_reviews SET state = 1');
clearMail();
$send('dina', ['item_id' => 75, 'rating' => 4, 'text' => 'After the moderation']);
check('mail: the limit counts the waiting ones', 1, count(mailbox()));

// Older than an hour: not counted
run('UPDATE #__mucronixreviews_reviews SET state = 0, created = created - INTERVAL 2 HOUR, modified = NULL');
clearMail();
$send('mailauthor', ['item_id' => 76, 'rating' => 4, 'text' => 'An hour later']);
check('mail: the limit of the last hour only', 1, count(mailbox()));

options(['notify_hourly' => 0]);
wipe();
clearMail();

foreach (['anna', 'boris', 'vera', 'gleb'] as $n => $name) {
    $send($name, ['item_id' => 80 + $n, 'rating' => 4, 'text' => "Text $n"]);
}

check('mail: no limit', 4, count(mailbox()));

// ---------------------------------------------------------------- a mail server that refuses, "Send Mail" off
options([]);
touch("$stand/mail/refuse");
clearMail();
$log = "$site/administrator/logs/custom-logging.php";
@unlink($log);
[$status, $answer] = $send('dina', ['item_id' => 90, 'rating' => 4, 'text' => 'Refused by the server']);
unlink("$stand/mail/refuse");
check('mail: a refusing server: the review is there, the answer as always', [200, true, false, '0'], [$status, $answer['success'] ?? null, $answer['published'] ?? null, (string) one('SELECT state FROM #__mucronixreviews_reviews WHERE item_id = 90')]);
check('mail: a refusing server: nothing delivered', [], mailbox());
check('mail: a refusing server: in the log of Joomla, when the category is logged', true, str_contains((string) @file_get_contents($log), 'Email to the moderators, ' . $adminMail . ':'));

config(['mailonline' => false]);
clearMail();
@unlink($log);
[$status, $answer] = $send('mailauthor', ['item_id' => 91, 'rating' => 4, 'text' => 'Mail is off']);
config(['mailonline' => true]);
check('mail: "Send Mail" off: none, the review is there, nothing in the log', [200, true, [], false], [$status, $answer['success'] ?? null, mailbox(), str_contains((string) @file_get_contents($log), 'Email to the moderators')]);
clearMail();
$send('mailauthor', ['item_id' => 89, 'rating' => 4, 'text' => 'Mail is on again']);
check('mail: "Send Mail" on again: one', 1, count(mailbox()));

// ---------------------------------------------------------------- the templates
check('mail: the templates are there', ['com_mucronixreviews.moderation', 'com_mucronixreviews.moderation_more'], array_column(rows("SELECT template_id FROM #__mail_templates WHERE extension = 'com_mucronixreviews' ORDER BY template_id"), 'template_id'));
$templates = $moderator->get('administrator/index.php?option=com_mails&view=templates');
check('mail: the templates in "Mail Templates", translated', [200, true, true, 0], [$moderator->status, str_contains($templates, 'Mucronix Reviews: Review Waiting for Moderation'), str_contains($templates, 'Mucronix Reviews: Many Reviews Waiting'), preg_match('~COM_MUCRONIXREVIEWS_MAIL~', $templates)]);

// In the language of the administrator, with the strings loaded for every address
run("UPDATE #__extensions SET params = JSON_SET(params, '$.administrator', 'ru-RU') WHERE element = 'com_languages'");
options(['notify_groups' => [8], 'notify_emails' => 'extra@stand.invalid']);
clearMail();
[$status, $answer] = $send('anna', ['item_id' => 92, 'rating' => 5, 'text' => 'По-русски']);
check('mail: the review in Russian is taken', [200, false], [$status, $answer['published'] ?? $answer]);
run("UPDATE #__extensions SET params = JSON_SET(params, '$.administrator', 'en-GB') WHERE element = 'com_languages'");
$mails = mailbox();
check('mail: in the language of the administrator, to every address', [2, 2, 0], [count($mails), count(array_filter($mails, fn ($m) => str_contains($m['subject'], 'Отзыв ждёт модерации') && str_contains($m['body'], 'Оценка: 5 из 5') && str_contains($m['body'], 'Новый отзыв.'))), preg_match('~COM_MUCRONIXREVIEWS_~', implode('', array_column($mails, 'body')))]);

options([]);
wipe();
clearMail();
