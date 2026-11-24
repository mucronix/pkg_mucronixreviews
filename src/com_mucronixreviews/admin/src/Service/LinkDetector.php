<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  com_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Mucronix\Component\MucronixReviews\Administrator\Service;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Finds links in plain text: an address with http://, https:// or www., and a bare domain of a common zone.
 * An email address holds such a domain, so it counts as a link too.
 *
 * @since  0.1.0
 */
final class LinkDetector
{
    /**
     * The zones that make a word with a dot a link. No zones that are words of a language (is, it, me, de, by ...):
     * a missing space after a full stop is not a link.
     *
     * @var    string[]
     * @since  0.1.0
     */
    private const ZONES = [
        'com', 'net', 'org', 'info', 'biz', 'io', 'eu', 'ua', 'ru', 'su', 'kz', 'pl', 'uk', 'cz', 'sk', 'lt', 'lv', 'ee',
        'md', 'tv', 'cc', 'xyz', 'online', 'site', 'website', 'store', 'shop', 'club', 'рф', 'укр', 'рус',
        'xn--p1ai', 'xn--j1amh', 'xn--p1acf',
    ];

    /**
     * Whether the text holds a link.
     *
     * @param   string  $text  The text
     *
     * @return  boolean
     *
     * @since   0.1.0
     */
    public static function containsLink(string $text): bool
    {
        $address = preg_match('~(?:https?://|(?<![\p{L}\p{N}])www\.)[\p{L}\p{N}]~iu', $text);
        $zones   = implode('|', self::ZONES);
        $domain  = preg_match('~(?<=[\p{L}\p{N}])\.(?:' . $zones . ')(?![\p{L}\p{N}-])~iu', $text);

        // A text that could not be searched may hold a link
        return $address !== 0 || $domain !== 0;
    }
}
