<?php

/**
 * Updates from the older versions. Included by drive.php. The archives of those versions are not in the public
 * repository: without them in archive/ these checks are skipped, and the output says so.
 */

$older   = ['0.1.0', '0.2.0', '0.3.0', '0.5.2'];
$missing = array_values(array_filter($older, fn ($v) => !is_file(__DIR__ . "/../../archive/pkg_mucronixreviews-$v.zip")));

if ($missing) {
    echo 'SKIPPED  the updates from older versions: no archive/pkg_mucronixreviews-' . implode('.zip, ', $missing) . ".zip\n";

    return;
}

// ---------------------------------------------------------------- an update from 0.1.0
check('0.1.0: ok', true, str_contains(install(__DIR__ . '/../../archive/pkg_mucronixreviews-0.1.0.zip'), '[OK]'));
options(['text_min' => '33', 'keep_data' => '1']);
run("INSERT INTO #__mucronixreviews_reviews (context, item_id, user_id, username, rating, title, text, state, created) VALUES ('com_test.item', 1, 1, 'old', 4, 'T', 'Text', 1, '2026-01-01')");
$old = array_map(fn ($row) => array_diff_key($row, ['title' => 1]), rows('SELECT * FROM #__mucronixreviews_reviews'));
$out = install($package);
check('update: ok', true, str_contains($out, '[OK]'));
quiet('update', $out);
check('update: the title is gone', '0', (string) count(rows("SHOW COLUMNS FROM #__mucronixreviews_reviews LIKE 'title'")));
check('update: data and options', [$old, '33'], [rows('SELECT * FROM #__mucronixreviews_reviews'), json_decode(one("SELECT params FROM #__extensions WHERE element = 'com_mucronixreviews'"))->text_min]);
check('update: menu', '4', (string) one("SELECT COUNT(*) FROM #__menu WHERE link LIKE '%mucronixreviews%'"));
check('update: the templates of the emails', '2', (string) one("SELECT COUNT(*) FROM #__mail_templates WHERE extension = 'com_mucronixreviews'"));
check('update: one schema', '0.6.0', one("SELECT GROUP_CONCAT(s.version_id) FROM #__schemas s JOIN #__extensions e USING (extension_id) WHERE e.element = 'com_mucronixreviews'"));
remove();
run('DROP TABLE #__mucronixreviews_reviews, #__mucronixreviews_stats');

// ---------------------------------------------------------------- an update from 0.2.0: the content plugin is new
install(__DIR__ . '/../../archive/pkg_mucronixreviews-0.2.0.zip');
run("UPDATE #__extensions SET enabled = 0 WHERE element = 'mucronixreviews' AND folder = 'system'");
options(['text_min' => '44']);
$enabled = fn () => array_column(rows("SELECT folder, enabled FROM #__extensions WHERE element = 'mucronixreviews' AND type = 'plugin' ORDER BY folder"), 'enabled', 'folder');
$out = install($package);
check('update from 0.2.0: ok', [true, '44'], [str_contains($out, '[OK]'), json_decode(one("SELECT params FROM #__extensions WHERE element = 'com_mucronixreviews'"))->text_min]);
quiet('update from 0.2.0', $out);
check('update from 0.2.0: the new plugin is enabled, the disabled one stays', ['content' => 1, 'system' => 0], array_map('intval', $enabled()));
run("UPDATE #__extensions SET enabled = 1 WHERE element = 'mucronixreviews' AND folder = 'system'");
run("UPDATE #__extensions SET enabled = 0 WHERE element = 'mucronixreviews' AND folder = 'content'");
install($package);
check('update again: what is disabled stays', ['content' => 0, 'system' => 1], array_map('intval', $enabled()));
check('update from 0.2.0: files', [true, true, true], [is_file("$site/media/com_mucronixreviews/css/reviews.css"), is_file("$site/components/com_mucronixreviews/layouts/block.php"), is_file("$site/plugins/content/mucronixreviews/src/Context/ArticleContext.php")]);
remove();
run('DROP TABLE IF EXISTS #__mucronixreviews_reviews, #__mucronixreviews_stats');

// ---------------------------------------------------------------- an update from 0.3.0: no title, the block of the articles moves to the component
install(__DIR__ . '/../../archive/pkg_mucronixreviews-0.3.0.zip');
options(['text_min' => '55']);
run("UPDATE #__extensions SET params = '{\"categories\":[2],\"position\":\"before\"}' WHERE element = 'mucronixreviews' AND folder = 'content'");
run("INSERT INTO #__mucronixreviews_reviews (context, item_id, user_id, username, rating, title, text, state, created) VALUES ('com_test.item', 1, 1, 'old', 4, 'T', 'Text', 1, '2026-01-01')");
$old = array_map(fn ($row) => array_diff_key($row, ['title' => 1]), rows('SELECT * FROM #__mucronixreviews_reviews'));
$out = install($package);
check('update from 0.3.0: ok', true, str_contains($out, '[OK]'));
quiet('update from 0.3.0', $out);
check('update from 0.3.0: no title, the rest stays', ['0', $old, '0.6.0'], [(string) count(rows("SHOW COLUMNS FROM #__mucronixreviews_reviews LIKE 'title'")), rows('SELECT * FROM #__mucronixreviews_reviews'), one("SELECT s.version_id FROM #__schemas s JOIN #__extensions e USING (extension_id) WHERE e.element = 'com_mucronixreviews'")]);
$options = json_decode(one("SELECT params FROM #__extensions WHERE element = 'com_mucronixreviews'"), true);
check('update from 0.3.0: options stay, the plugin options are not moved', ['55', false], [$options['text_min'] ?? null, isset($options['article_categories'])]);
check('update from 0.3.0: the new files, no fields in the plugin', [true, false], [is_file("$site/plugins/content/mucronixreviews/src/Text/Shortcodes.php"), str_contains((string) file_get_contents("$site/plugins/content/mucronixreviews/mucronixreviews.xml"), 'name="categories"')]);

// Kept tables of 0.3.0 under this version: the title goes, too
options(['keep_data' => 1]);
remove();
run("ALTER TABLE #__mucronixreviews_reviews ADD COLUMN title VARCHAR(255) NOT NULL DEFAULT '' AFTER rating");
run("ALTER TABLE #__mucronixreviews_stats COMMENT = 'Mucronix Reviews schema 0.1.0'");
$out = install($package);
check('kept tables of 0.3.0: updated', [true, '0', $old], [str_contains($out, '[OK]') && !str_contains($out, 'could not be updated'), (string) count(rows("SHOW COLUMNS FROM #__mucronixreviews_reviews LIKE 'title'")), rows('SELECT * FROM #__mucronixreviews_reviews')]);
options(['keep_data' => 0]);
remove();
run('DROP TABLE IF EXISTS #__mucronixreviews_reviews, #__mucronixreviews_stats');

// ---------------------------------------------------------------- an update from 0.5.2: the templates of the emails are new
install(__DIR__ . '/../../archive/pkg_mucronixreviews-0.5.2.zip');
options(['text_min' => '66']);
check('0.5.2: no templates', '0', (string) one("SELECT COUNT(*) FROM #__mail_templates WHERE extension = 'com_mucronixreviews'"));
$out = install($package);
check('update from 0.5.2: ok', [true, '66'], [str_contains($out, '[OK]'), json_decode(one("SELECT params FROM #__extensions WHERE element = 'com_mucronixreviews'"))->text_min]);
quiet('update from 0.5.2', $out);
check('update from 0.5.2: the templates, the schema', [['com_mucronixreviews.moderation', 'com_mucronixreviews.moderation_more'], '0.6.0'], [array_column(rows("SELECT template_id FROM #__mail_templates WHERE extension = 'com_mucronixreviews' ORDER BY template_id"), 'template_id'), one("SELECT s.version_id FROM #__schemas s JOIN #__extensions e USING (extension_id) WHERE e.element = 'com_mucronixreviews'")]);
options(['keep_data' => 0]);
remove();
run('DROP TABLE IF EXISTS #__mucronixreviews_reviews, #__mucronixreviews_stats');
