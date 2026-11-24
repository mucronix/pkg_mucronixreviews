<?php

/**
 * Schema.org: the rating in the graph Joomla builds. Included by stage3.inc.php, after its checks.
 */

/** The JSON-LD of a page: every block decoded, and the raw text of all of them */
function ldOf(string $html): array
{
    preg_match_all('~<script[^>]*type="application/ld\+json"[^>]*>(.*?)</script>~s', $html, $m);

    return [array_map(fn ($json) => json_decode($json, true), $m[1]), implode("\n", $m[1])];
}

/** The node with an @id in the graphs of a page */
function nodeOf(array $blocks, string $id): ?array
{
    foreach ($blocks as $block) {
        foreach ($block['@graph'] ?? [$block] as $entry) {
            if (($entry['@id'] ?? null) === $id) {
                return $entry;
            }
        }
    }

    return null;
}

$nodeId = fn (int $id): string => 'http://127.0.0.1:8097/#/schema/com_content/article/' . $id;
$schema = fn (string $html): array => ldOf($html);
$count  = fn (string $raw): int => substr_count($raw, '"aggregateRating"');
$graphs = fn (array $blocks): int => count(array_filter($blocks, fn ($b) => isset($b['@graph'])));

wipe();
$rated  = article('Rated article', $child, '<p>Text of rated</p>');
$plain2 = article('Plain article', $other, '<p>Text of plain</p>');
reviewOf($rated, 'anna', 5, "Good \"one\" </script><b>bold</b>");
reviewOf($rated, 'boris', 4);
reviewOf($rated, 'avtor', 2, 'Pending text', 0);
$recount();
plugin(['categories' => [$child]]);
options([]);

// Off by default for articles
$html = page($guest, $rated);
[$blocks, $raw] = $schema($html);
check('schema: the graph of Joomla is there', [1, 'Article'], [$graphs($blocks), nodeOf($blocks, $nodeId($rated))['@type'] ?? null]);
check('schema: off for articles by default', 0, $count($raw));

options(['schema_contexts' => ['com_content.article']]);
$html = page($guest, $rated);
[$blocks, $raw] = $schema($html);
$node = nodeOf($blocks, $nodeId($rated)) ?? [];
check('schema: no JSON-LD of its own: the graph and the breadcrumbs of Joomla', [1, 2, 2], [$graphs($blocks), count($blocks), substr_count($html, 'application/ld+json')]);
check('schema: the rating in the node of the article', ['Article', ['@type' => 'AggregateRating', 'ratingValue' => '4.5', 'bestRating' => '5', 'worstRating' => '1', 'ratingCount' => '2', 'reviewCount' => '1']], [$node['@type'] ?? null, $node['aggregateRating'] ?? null]);
check('schema: one rating on the page', 1, $count($raw));
check('schema: the reviews with a text that the list shows', [1, 'anna', '5', "Good \"one\" </script><b>bold</b>"], [count($node['review'] ?? []), $node['review'][0]['author']['name'] ?? null, $node['review'][0]['reviewRating']['ratingValue'] ?? null, $node['review'][0]['reviewBody'] ?? null]);
check('schema: a review is a Review of a Person, with its date', ['Review', 'Person', 1], [$node['review'][0]['@type'] ?? null, $node['review'][0]['author']['@type'] ?? null, preg_match('~^2026-03-0\dT\d\d:\d\d:00\+00:00$~', $node['review'][0]['datePublished'] ?? '')]);
check('schema: nothing pending', false, str_contains($raw, 'Pending text'));
check('schema: no tag of a review in the head', [false, false], [str_contains($raw, '</script><b>'), str_contains($html, '"Good \"one\" </script>')]);
check('schema: not a product', false, str_contains($raw, '"Product"'));

plugin(['categories' => [$child], 'show' => 'summary']);
[$blocks, $raw] = $schema(page($guest, $rated));
$node = nodeOf($blocks, $nodeId($rated)) ?? [];
check('schema: summary only: the rating, no reviews', [true, false], [isset($node['aggregateRating']), isset($node['review'])]);

plugin(['categories' => [$child], 'show' => 'list']);
options(['schema_contexts' => ['com_content.article'], 'list_limit' => 1]);
reviewOf($rated, 'vasyl', 3, 'Newest text');
$recount();
[$blocks] = $schema(page($guest, $rated));
$node = nodeOf($blocks, $nodeId($rated)) ?? [];
check('schema: only the reviews of the first page', [['vasyl'], '3'], [array_map(fn ($r) => $r['author']['name'], $node['review'] ?? []), $node['aggregateRating']['ratingCount'] ?? null]);
options(['schema_contexts' => ['com_content.article']]);
run("DELETE FROM #__mucronixreviews_reviews WHERE username = 'vasyl'");
$recount();

// The rating has to be seen
plugin([]);
[, $raw] = $schema(page($guest, $rated));
check('schema: no block on the page, no rating', 0, $count($raw));
$withText = function (int $id, string $text): void {
    global $db;

    run("UPDATE #__content SET introtext = '" . $db->real_escape_string($text) . "' WHERE id = $id");
};
$withText($rated, '<p>Rated {mucronixreviews summary} here</p>');
[$blocks] = $schema(page($guest, $rated));
$node = nodeOf($blocks, $nodeId($rated)) ?? [];
check('schema: a summary by a shortcode is enough, no list, no reviews', [true, false], [isset($node['aggregateRating']), isset($node['review'])]);
$withText($rated, '<p>Text of rated</p>');

// Only the object of the page
$withText($plain2, "<p>{mucronixreviews id=$rated}</p>");
[$blocks, $raw] = $schema(page($guest, $plain2));
check('schema: a block of another article gives no rating', [0, false], [$count($raw), isset(nodeOf($blocks, $nodeId($rated))['aggregateRating'])]);
reviewOf($plain2, 'anna', 1, 'Of plain');
$recount();
[, $raw] = $schema(page($guest, $plain2));
check('schema: nor does it make the rating of the page seen', 0, $count($raw));
$withText($plain2, '<p>Text of plain</p>');

// Published ratings only
plugin(['categories' => [$child]]);
run("UPDATE #__mucronixreviews_reviews SET state = 0 WHERE item_id = $rated");
$recount();
[, $raw] = $schema(page($guest, $rated));
check('schema: pending ones only, nothing', 0, $count($raw));
run("DELETE FROM #__mucronixreviews_reviews WHERE item_id = $rated");
$recount();
[, $raw] = $schema(page($guest, $rated));
check('schema: no rating, nothing', 0, $count($raw));
reviewOf($rated, 'boris', 3);
$recount();
[$blocks] = $schema(page($guest, $rated));
$node = nodeOf($blocks, $nodeId($rated)) ?? [];
check('schema: stars alone: no reviewCount, no review', [['@type' => 'AggregateRating', 'ratingValue' => '3.0', 'bestRating' => '5', 'worstRating' => '1', 'ratingCount' => '1'], false], [$node['aggregateRating'] ?? null, isset($node['review'])]);
run("DELETE FROM #__mucronixreviews_reviews WHERE item_id = $rated");
reviewOf($rated, 'anna', 4, 'Back');
$recount();

// A type chosen in the article
$schemaRow = function (string $type, string $schema) use ($rated): void {
    global $db;

    run("DELETE FROM #__schemaorg WHERE itemId = $rated AND context = 'com_content.article'");

    if ($type !== '') {
        run("INSERT INTO #__schemaorg (itemId, context, schemaType, `schema`) VALUES ($rated, 'com_content.article', '$type', '" . $db->real_escape_string($schema) . "')");
    }
};
$schemaRow('Book', '{"@type":"Book","name":"A book"}');
[$blocks] = $schema(page($guest, $rated));
$node = nodeOf($blocks, $nodeId($rated)) ?? [];
check('schema: a Book gets the rating', ['Book', '4.0'], [$node['@type'] ?? null, $node['aggregateRating']['ratingValue'] ?? null]);
$schemaRow('Person', '{"@type":"Person","name":"Somebody"}');
[$blocks, $raw] = $schema(page($guest, $rated));
check('schema: a Person gets none', ['Person', 0], [nodeOf($blocks, $nodeId($rated))['@type'] ?? null, $count($raw)]);
$schemaRow('Book', '{"@type":"Book","name":"A book","aggregateRating":{"@type":"AggregateRating","ratingValue":"3","ratingCount":"7"}}');
[$blocks, $raw] = $schema(page($guest, $rated));
check('schema: a rating the node has already stays the only one', [1, '7', false], [$count($raw), nodeOf($blocks, $nodeId($rated))['aggregateRating']['ratingCount'] ?? null, isset(nodeOf($blocks, $nodeId($rated))['review'])]);
$schemaRow('', '');

// A rating of somebody else stays alone: the vote plugin of Joomla
run("UPDATE #__extensions SET enabled = 1 WHERE element = 'vote' AND folder = 'content'");
run("DELETE FROM #__content_rating WHERE content_id = $rated");
run("INSERT INTO #__content_rating (content_id, rating_sum, rating_count, lastip) VALUES ($rated, 9, 3, '')");
[$blocks, $raw] = $schema(page($guest, $rated));
check('schema: the rating of the vote plugin stays the only one', [1, false, true], [$count($raw), isset(nodeOf($blocks, $nodeId($rated))['aggregateRating']), str_contains($raw, '"ratingCount":"3"')]);
run("DELETE FROM #__content_rating WHERE content_id = $rated");
[$blocks] = $schema(page($guest, $rated));
check('schema: no votes, ours again', true, isset(nodeOf($blocks, $nodeId($rated))['aggregateRating']));
run("UPDATE #__extensions SET enabled = 0 WHERE element = 'vote' AND folder = 'content'");

// From the cache of the component, where the plugin does not run
// The options were changed in the database, not by the administrator who would clean the cache of Joomla: clean it.
// Joomla keeps the cache of the site in administrator/cache.
$cleanCache = function () use ($site): void {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$site/administrator/cache", FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
        if ($file->getFilename() !== 'index.html' || $file->getPath() !== "$site/administrator/cache") {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
    }
};
$cleanCache();
$conf = file_get_contents("$site/configuration.php");
file_put_contents("$site/configuration.php", preg_replace('~public \$caching = .*?;~', 'public $caching = 1;', $conf));
sleep(3);
$cached = new Client();
page($cached, $rated);
run("UPDATE #__content SET introtext = '<p>Changed after caching</p>' WHERE id = $rated");
$html = page($cached, $rated);
[$blocks, $raw] = $schema($html);

// Joomla loads no content plugin for a view from its cache: "Content - Joomla" adds no node, there is nothing to put
// the rating into, and nothing of its own is made up
check('schema: a page from the cache, no node of Joomla, no rating', [false, true, null, 0], [str_contains($html, 'Changed after caching'), str_contains($html, 'data-mxr-summary="com_content.article.' . $rated . '"'), nodeOf($blocks, $nodeId($rated)), $count($raw)]);

// A type chosen in the article: the node comes from the database, the page from the cache gets the rating
$schemaRow('Book', '{"@type":"Book","name":"A book"}');
page($cached, $rated);
$html = page($cached, $rated);
[$blocks, $raw] = $schema($html);
check('schema: a page from the cache with a type of its own', [false, 'Book', true, 1], [str_contains($html, 'Changed after caching'), nodeOf($blocks, $nodeId($rated))['@type'] ?? null, isset(nodeOf($blocks, $nodeId($rated))['aggregateRating']), $count($raw)]);
$schemaRow('', '');
run("UPDATE #__content SET introtext = '<p>Text of rated</p>' WHERE id = $rated");
file_put_contents("$site/configuration.php", $conf);
sleep(3);
$cleanCache();

// Without the plugin of Joomla nothing of its own
run("UPDATE #__extensions SET enabled = 0 WHERE element = 'schemaorg' AND folder = 'system'");
$html = page($guest, $rated);
[$blocks, $raw] = $schema($html);
check('schema: "System - Schema.org" off, nothing of its own', [0, 0, 'block1'], [$graphs($blocks), $count($raw), explode(' ', parts($html))[0]]);
run("UPDATE #__extensions SET enabled = 1 WHERE element = 'schemaorg' AND folder = 'system'");

// Nor for a type that is off, nor while the component is disabled
options([]);
[, $raw] = $schema(page($guest, $rated));
check('schema: switched off again', 0, $count($raw));
options(['schema_contexts' => ['com_content.article']]);
run("UPDATE #__extensions SET enabled = 0 WHERE element = 'com_mucronixreviews'");
[, $raw] = $schema(page($guest, $rated));
check('schema: the component disabled', 0, $count($raw));
run("UPDATE #__extensions SET enabled = 1 WHERE element = 'com_mucronixreviews'");
options([]);
plugin([]);
