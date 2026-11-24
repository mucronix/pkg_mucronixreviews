# Stand

End-to-end checks of the package on a throwaway Joomla with a MySQL of its own, in a temporary folder. No other site and no other database of the machine is touched.

1. Unpack the full package of Joomla (6.1.3 and 6.2.0 from their releases on GitHub, the sha256 checked against the one in the release) into `<stand>/site`, give all the files today's date.
2. `mysqld --no-defaults --initialize-insecure --datadir=<stand>/mysql-data`, then `mysqld --no-defaults --datadir=<stand>/mysql-data --port=33998 --bind-address=127.0.0.1 --mysqlx=OFF`.
3. Database `stand`, user `stand` / `stand-only-pass-1`; install Joomla with `installation/joomla.php` (prefix `st_`, administrator `standadmin` / `stand-only-pass-1`). `error_reporting = maximum`.
   Russian and Ukrainian: copy `language/ru-RU`, `language/uk-UA` and the same of `administrator/language` from a Joomla 6 site that has those languages, without any `*mucronix*` file, then `cli/joomla.php extension:discover` and `extension:discover:install`. Before the package is installed: Joomla installs the languages of an extension only for the languages a site has.
4. Six web servers: `php -S 127.0.0.1:8097 -t <stand>/site` and the same on 8098 to 8102. One has a single worker on Windows, the checks of requests sent at once need several.
5. `php tests/stand/drive.php <stand>`.

The mail of the stand: `drive.php` points `configuration.php` of the stand at `smtp-sink.php` (127.0.0.1:2526), starts it and stops it at the end. Every message becomes a file in `<stand>/mail`; nothing leaves the machine. It also logs the category `com_mucronixreviews` (`administrator/logs/custom-logging.php`), as an administrator can choose in the Global Configuration. The checks of the email to the moderators are `stage5.inc.php` (`ONLY=5` runs them alone).

`php tests/stand/mutants.php <stand>` breaks one thing at a time in the installed copy (`mutations.php`): the run must stop at a red check that names it.

YOOtheme Pro: a second clean stand of 6.2.0 the same way, then YOOtheme Pro installed from its archive (`cli/joomla.php extension:install --path=<yootheme_j_5.0.56.zip>`) and made the default template of the site. `php tests/stand/yootheme.php <stand>` installs the package of `build/` and checks the fields, the list of reviews, the element "Mucronix Reviews" and the classes of UIkit; `php tests/stand/mutants.php <stand> --yootheme` breaks one thing at a time for it (`mutations-yootheme.php`).

`plg_content_mxrtest` registers a type of objects for the checks. It is not part of the package.
