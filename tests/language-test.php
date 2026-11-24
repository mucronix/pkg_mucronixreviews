<?php

/**
 * Checks the languages: the same keys with the same placeholders in all of them, every key of the code is there.
 * Since 0.6.1 also the rules of section 12 of TZ: the files as JED Checker reads them, the plural forms of the core,
 * names in quotes taken from the package or from the language files of Joomla, the words of the glossary, and the
 * names and terms the glossary of the project fixes (since 0.6.2: they follow it, not Joomla or YOOtheme). Not part of
 * the package.
 *
 * Usage: php tests/language-test.php --joomla=<a Joomla 6 site with en-GB, ru-RU and uk-UA, only read>
 */

$src    = __DIR__ . '/../src';
$failed = 0;
$all    = [];

function fail(string $text): void
{
    global $failed;

    $failed++;
    echo "FAIL  $text\n";
}

foreach (glob("$src/{*,*/*}/language/en-GB/*.ini", GLOB_BRACE) as $english) {
    $strings = parse_ini_file($english, false, INI_SCANNER_RAW);
    $name    = substr($english, strlen($src) + 1);
    $part    = explode('/', $name)[0];

    foreach (['ru-RU', 'uk-UA'] as $tag) {
        $path   = str_replace('en-GB', $tag, $english);
        $other  = is_file($path) ? parse_ini_file($path, false, INI_SCANNER_RAW) : [];
        $differ = array_merge(array_diff_key($strings, $other), array_diff_key($other, $strings));

        // The form for 2 to 4 is Russian and Ukrainian only: English has none (since 0.6.1)
        $differ = array_filter($differ, fn ($key) => !(isset($other[$key]) && preg_match('~^(.+)_2$~', $key, $m) && isset($strings[$m[1] . '_1'])), ARRAY_FILTER_USE_KEY);

        if ($differ) {
            fail("$name, $tag: " . implode(', ', array_keys($differ)));
        }

        foreach ($other as $key => $text) {
            preg_match_all('~%(\d\$)?[ds]~', $strings[$key] ?? $strings[preg_replace('~_2$~', '', $key)] ?? '', $a);
            preg_match_all('~%(\d\$)?[ds]~', $text, $b);

            if ($a[0] !== $b[0] || trim($text) === '') {
                fail("$name, $tag, $key");
            }
        }
    }

    $place = $part . (str_contains($name, '/site/') ? '/site' : '') . (str_ends_with($name, '.sys.ini') ? '/sys' : '');
    $all[$place] = array_keys($strings);
}

// The keys of the code, in the language file that is loaded there. Refusals of reviews are shown on the site.
$used = [];

// Keys that are put together in the code or by a layout of the core
foreach (['EMPTYSTATE_TITLE', 'EMPTYSTATE_CONTENT', 'N_ITEMS_PUBLISH', 'N_ITEMS_PENDING', 'N_ITEMS_REJECT', 'N_ITEMS_SPAM', 'N_ITEMS_TRASH'] as $key) {
    $used['com_mucronixreviews']['COM_MUCRONIXREVIEWS_' . $key] = true;
}

foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS)) as $file) {
    $path = str_replace('\\', '/', substr($file->getPathname(), strlen($src) + 1));

    // A template of an email: its subject and text by the SQL, its title and description by "Mail Templates" (since 0.6.0)
    if (preg_match('~^com_\w+/admin/sql/install\.mysql\.utf8\.sql$~', $path)) {
        preg_match_all("~\('com_mucronixreviews\.(\w+)', 'com_mucronixreviews', '', '(\w+)', '(\w+)'~", file_get_contents($file->getPathname()), $templates, PREG_SET_ORDER);

        foreach ($templates as [, $id, $subject, $body]) {
            $prefix = 'COM_MUCRONIXREVIEWS_MAIL_' . strtoupper($id);

            foreach ([$subject, $body, $prefix . '_TITLE', $prefix . '_DESC'] as $key) {
                $used['com_mucronixreviews'][$key] = true;
            }
        }

        continue;
    }

    if (!preg_match('~\.(php|xml)$~', $path) || str_contains($path, '/language/')) {
        continue;
    }

    $code  = file_get_contents($file->getPathname());
    $part  = explode('/', $path)[0];
    $place = $part . (str_contains($path, '/site/') ? '/site' : '');

    // The menu speaks with the system strings
    if (preg_match('~^com_\w+/\w+\.xml$~', $path)) {
        $place .= '/sys';
    }

    preg_match_all('~\b(?:COM|PLG_SYSTEM|PLG_CONTENT|PKG)_MUCRONIXREVIEWS(?:_[A-Z0-9_]*[A-Z0-9])?\b(?!_)~', $code, $keys);

    foreach ($keys[0] as $key) {
        if (str_contains($code, $key . "_' . ")) {
            continue;
        }

        $used[$place][$key] = true;
    }

    preg_match_all("~new ReviewException\('(\w+)'~", $code, $reasons);

    // Refusals are shown on the site, those of an administrator in the administrator
    foreach ($reasons[1] as $reason) {
        $key = 'COM_MUCRONIXREVIEWS_ERROR_' . strtoupper($reason);

        foreach (['com_mucronixreviews/site', 'com_mucronixreviews'] as $place) {
            if (in_array($key, $all[$place], true)) {
                $used[$place][$key] = true;
            }
        }

        if (!in_array($key, array_merge($all['com_mucronixreviews/site'], $all['com_mucronixreviews']), true)) {
            fail("no string $key");
        }
    }
}

foreach ($used as $place => $keys) {
    foreach (array_keys($keys) as $key) {
        // A key with a variable end
        if (!in_array($key, $all[$place], true) && !preg_grep('~^' . $key . '_~', $all[$place])) {
            fail("$place: no string $key");
        }
    }
}

foreach ($all as $place => $keys) {
    $code = $used[$place] ?? [];

    if (str_ends_with($place, '/sys')) {
        continue;
    }

    foreach (array_diff($keys, array_keys($code)) as $key) {
        // A plural form, or a description of the manifest
        if (isset($code[preg_replace('~_[012]$~', '', $key)]) || str_ends_with($key, '_XML_DESCRIPTION')) {
            continue;
        }

        if (!preg_match('~^(COM|PLG_SYSTEM|PLG_CONTENT|PKG)_MUCRONIXREVIEWS(_CONFIGURATION)?$~', $key)) {
            fail("$place: nobody uses $key");
        }
    }
}

// ---------------------------------------------------------------- since 0.6.1: the rules of TZ, section 12
$joomla = rtrim(array_values(array_filter(array_map(fn ($a) => str_starts_with($a, '--joomla=') ? substr($a, 9) : null, $argv)))[0] ?? '', '/\\');

if ($joomla === '') {
    fwrite(STDERR, "pass --joomla=<a Joomla 6 site that has en-GB, ru-RU and uk-UA>, it is only read\n");
    exit(2);
}
$files  = [];

foreach (['en-GB', 'ru-RU', 'uk-UA'] as $tag) {
    foreach (glob("$src/{*,*/*}/language/$tag/*.ini", GLOB_BRACE) as $file) {
        $files[$tag][substr($file, strlen($src) + 1)] = parse_ini_file($file, false, INI_SCANNER_RAW);
    }
}

// The file as it is written: what Joomla and JED Checker look at
foreach (array_merge(...array_values(array_map('array_keys', $files))) as $name) {
    $raw  = file_get_contents("$src/$name");
    $seen = [];

    if (str_starts_with($raw, "\xEF\xBB\xBF")) {
        fail("$name: a BOM");
    }

    if (!str_ends_with($raw, "\n") || str_contains($raw, "\r")) {
        fail("$name: the lines do not end with LF only");
    }

    foreach (explode("\n", rtrim($raw, "\n")) as $number => $line) {
        $at = "$name:" . ($number + 1);

        if ($line === '' || str_starts_with($line, ';')) {
            continue;
        }

        if (!preg_match('~^([A-Z][A-Z0-9_]*)="(.*)"$~', $line, $m)) {
            fail("$at: not KEY=\"value\": $line");

            continue;
        }

        if (isset($seen[$m[1]])) {
            fail("$at: {$m[1]} again");
        }

        $seen[$m[1]] = true;

        if (trim($m[2]) === '' || $m[2] !== trim($m[2])) {
            fail("$at: {$m[1]} is empty or has spaces at an end");
        }
    }
}

// Plural forms as Joomla reads them: no suffix for many, _1, and _2 in Russian and Ukrainian; English looks for no _2
foreach ($files['en-GB'] as $name => $english) {
    foreach (array_keys($english) as $key) {
        if (!preg_match('~^(.+)_1$~', $key, $m)) {
            continue;
        }

        $family = $m[1];

        foreach (['en-GB' => [$family, "{$family}_1"], 'ru-RU' => [$family, "{$family}_1", "{$family}_2"], 'uk-UA' => [$family, "{$family}_1", "{$family}_2"]] as $tag => $forms) {
            $strings = $files[$tag][str_replace('en-GB', $tag, $name)] ?? [];

            foreach ($forms as $form) {
                if (!isset($strings[$form])) {
                    fail("$tag: the plural form $form is missing");
                }
            }

            foreach (['_MORE', '_OTHER', '_ONE'] + ($tag === 'en-GB' ? [3 => '_2'] : []) as $unread) {
                if (isset($strings[$family . $unread])) {
                    fail("$tag: $family$unread is not read the way the strings are written");
                }
            }
        }
    }
}

// Names in quotes: one of our own strings, or exactly a string of the core of Joomla in that language
$core = [];

foreach (['en-GB', 'ru-RU', 'uk-UA'] as $tag) {
    foreach (glob("$joomla/{administrator/language,language}/$tag/*.ini", GLOB_BRACE) as $file) {
        foreach ((array) @parse_ini_file($file, false, INI_SCANNER_RAW) as $text) {
            $core[$tag][html_entity_decode(str_replace('&#160;', ' ', $text))] = true;
        }
    }

    if (count($core[$tag] ?? []) < 1000) {
        fail("$tag: the language files of the core are not at $joomla, pass --joomla=");
    }
}

foreach ($files as $tag => $byFile) {
    $own = [];

    foreach ($byFile as $strings) {
        foreach ($strings as $text) {
            $own[$text] = true;
        }
    }

    foreach ($byFile as $name => $strings) {
        foreach ($strings as $key => $text) {
            // An attribute of HTML holds no name and no quotes of a text
            $text    = preg_replace('~=\\\\"[^"]*?\\\\"~', '=', $text);
            $pattern = $tag === 'en-GB' ? '~\\\\"(.+?)\\\\"~u' : '~«((?:[^«»]|«[^«»]*»)+)»~u';
            preg_match_all($pattern, $text, $names);

            foreach ($names[1] as $quoted) {
                foreach (explode(' → ', $quoted) as $part) {
                    if (!isset($own[$part]) && !isset($core[$tag][$part])) {
                        fail("$tag, $key: \"$part\" is neither a string of the package nor one of the core");
                    }
                }
            }

            $forbidden = [
                'en-GB' => ['~[«»]~u' => 'quotes of Russian'],
                'ru-RU' => ['~аккаунт~iu' => 'аккаунт: учётная запись', '~(?<!e-)\bemail\b~iu' => 'email: e-mail', '~(?<!=)\\\\"~' => 'straight quotes: «»'],
                'uk-UA' => ['~’~u' => "the apostrophe ’: '", '~зміст~iu' => 'зміст: контент', '~(?<![Ee]-)\bemail\b~iu' => 'email: електронна пошта', '~(?<!=)\\\\"~' => 'straight quotes: «»'],
            ][$tag];

            // Dynamic content is "контент" in both, the decision of the owner (since 0.6.2)
            $forbidden += ['ru-RU' => ['~динамическ\w*\s+(?!контент)\w+~iu' => 'динамический контент'], 'uk-UA' => ['~динамічн\w*\s+(?!контент)\w+~iu' => 'динамічний контент']][$tag] ?? [];

            foreach ($forbidden as $rule => $instead) {
                if (preg_match($rule, $text)) {
                    fail("$tag, $key: $instead");
                }
            }

            // "Вміст" only inside the name of an item of the core in quotes, such as «Підготувати вміст» (since 0.6.2)
            if ($tag === 'uk-UA' && preg_match('~вміст~iu', preg_replace('~«[^«»]*»~u', '', $text))) {
                fail("$tag, $key: вміст: контент");
            }
        }
    }
}

// The glossary of the project, section 12 of TZ: it, not the translations of Joomla or YOOtheme, names these (since 0.6.2)
$glossary = [
    'plg_content_mucronixreviews/language/{L}/plg_content_mucronixreviews.ini'     => ['PLG_CONTENT_MUCRONIXREVIEWS' => ['en-GB' => 'Content - Mucronix Reviews', 'ru-RU' => 'Контент - Mucronix Reviews', 'uk-UA' => 'Контент. Mucronix Reviews']],
    'plg_content_mucronixreviews/language/{L}/plg_content_mucronixreviews.sys.ini' => ['PLG_CONTENT_MUCRONIXREVIEWS' => ['en-GB' => 'Content - Mucronix Reviews', 'ru-RU' => 'Контент - Mucronix Reviews', 'uk-UA' => 'Контент. Mucronix Reviews']],
    'plg_system_mucronixreviews/language/{L}/plg_system_mucronixreviews.ini'       => [
        'PLG_SYSTEM_MUCRONIXREVIEWS'                   => ['en-GB' => 'System - Mucronix Reviews', 'ru-RU' => 'Система - Mucronix Reviews', 'uk-UA' => 'Система. Mucronix Reviews'],
        'PLG_SYSTEM_MUCRONIXREVIEWS_ELEMENT_TAB_CONTENT' => ['en-GB' => 'Content', 'ru-RU' => 'Контент', 'uk-UA' => 'Контент'],
    ],
    'plg_system_mucronixreviews/language/{L}/plg_system_mucronixreviews.sys.ini'   => ['PLG_SYSTEM_MUCRONIXREVIEWS' => ['en-GB' => 'System - Mucronix Reviews', 'ru-RU' => 'Система - Mucronix Reviews', 'uk-UA' => 'Система. Mucronix Reviews']],
    'com_mucronixreviews/admin/language/{L}/com_mucronixreviews.ini'               => ['COM_MUCRONIXREVIEWS_TO_SPAM' => ['en-GB' => 'Mark as Spam', 'ru-RU' => 'В спам', 'uk-UA' => 'В спам']],
];

foreach ($glossary as $pattern => $keys) {
    foreach ($keys as $key => $byTag) {
        foreach ($byTag as $tag => $want) {
            $have = $files[$tag][str_replace('{L}', $tag, $pattern)][$key] ?? null;

            if ($have !== $want) {
                fail("$tag, $key: " . json_encode($have, JSON_UNESCAPED_UNICODE) . ', the glossary says ' . json_encode($want, JSON_UNESCAPED_UNICODE));
            }
        }
    }
}

echo $failed ? "\n$failed failed\n" : 'all ' . array_sum(array_map('count', $all)) . " strings passed\n";
exit($failed ? 1 : 0);
