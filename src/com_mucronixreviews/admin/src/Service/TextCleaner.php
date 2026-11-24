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
 * Turns what a user has sent into plain text.
 *
 * @since  0.1.0
 */
final class TextCleaner
{
    /**
     * A tag, a comment or a processing instruction. Not strip_tags(): that cuts "<3" and all that follows.
     *
     * @var    string
     * @since  0.1.0
     */
    private const TAG = '~<(?:/?[a-z]|!|\?)[^<>]*+>~i';

    /**
     * Cleans a text of several lines.
     *
     * @param   string  $value  What was sent
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public static function text(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", mb_scrub($value, 'UTF-8'));

        // Control characters except tab and line feed, and characters that take no place: first, they can hide a tag
        $value = (string) preg_replace('~[\x{0}-\x{8}\x{B}-\x{1F}\x{7F}-\x{9F}\x{200B}\x{2060}\x{FEFF}]~u', '', $value);
        $value = (string) preg_replace("~\n{3,}~", "\n\n", self::withoutTags($value));

        return self::trim($value);
    }

    /**
     * Takes the tags away, also those that appear when another tag between their parts is gone.
     *
     * @param   string  $value  The text
     *
     * @return  string
     *
     * @since   0.1.0
     */
    private static function withoutTags(string $value): string
    {
        for ($i = 0; $i < 50; $i++) {
            $value = (string) preg_replace(self::TAG, '', $value, -1, $count);

            if ($count === 0) {
                return $value;
            }
        }

        // Nobody nests tags this deep by accident
        return str_replace(['<', '>'], '', $value);
    }

    /**
     * Trims every kind of space.
     *
     * @param   string  $value  The text
     *
     * @return  string
     *
     * @since   0.1.0
     */
    private static function trim(string $value): string
    {
        return (string) preg_replace('~^[\s\p{Z}]++|(?<![\s\p{Z}])[\s\p{Z}]++$~u', '', $value);
    }
}
