<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  plg_content_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Mucronix\Plugin\Content\MucronixReviews\Text;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Finds the shortcodes in a text and puts what they stand for in their place.
 *
 * - A paragraph that holds nothing but shortcodes, also inside the emphasis of an editor, with spaces or line breaks
 *   around, is replaced as a whole.
 * - A block in a paragraph with other text goes right after the paragraph: inside it the browser would close the
 *   paragraph itself and carry the emphasis into the block.
 * - Anything else stands where its shortcode was.
 *
 * The text is read once from the start to the end, so a shortcode in what is put in is not replaced again. The patterns
 * have no nested repetition: a long paragraph does not exhaust the stack of the regular expressions.
 *
 * @since  0.3.1
 */
final class Shortcodes
{
    /**
     * One shortcode. The arguments are in the group "arguments".
     *
     * @var    string
     * @since  0.3.1
     */
    private const CODE = '\{mucronixreviews(?<arguments>(?:\s+(?:summary|id=\d{1,10}))*)\s*\}';

    /**
     * What a paragraph may hold besides shortcodes and still be no more than them: the emphasis of an editor, line
     * breaks, spaces of any kind. The no-break space itself is put in by PHP (since 0.6.0): the same two bytes as the
     * pattern had, without the hexadecimal notation that checkers of code take for hidden code.
     *
     * @var    string
     * @since  0.3.1
     */
    private const FILLER = '~</?(?:em|strong|b|i|u|s|small|mark|span|font)(?:\s[^<>]*)?>|<br\s*/?>|&nbsp;|&#160;|&#x0*a0;|' . "\u{00A0}" . '|\s~i';

    /**
     * Tags a paragraph cannot hold: a paragraph that seems to go over one of them was not closed
     *
     * @var    string
     * @since  0.3.1
     */
    private const BLOCKS = 'p|div|section|article|aside|header|footer|nav|main|table|thead|tbody|tfoot|tr|td|th|ul|ol|li|dl|dt|dd'
        . '|h[1-6]|blockquote|pre|figure|figcaption|form|fieldset|hr|address|details|summary';

    /**
     * Replaces the shortcodes of a text.
     *
     * @param   string    $text    The text
     * @param   callable  $render  Gets the arguments of one shortcode in lower case and returns what it stands for, and
     *                             whether that is a block: array{0: string, 1: bool}
     *
     * @return  string
     *
     * @since   0.3.1
     */
    public static function replace(string $text, callable $render): string
    {
        if (stripos($text, '{mucronixreviews') === false) {
            return $text;
        }

        // The shortcodes and the tags of blocks, in their order
        preg_match_all('~' . self::CODE . '|<(?<close>/?)(?<tag>' . self::BLOCKS . ')\b[^<>]*>~i', $text, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $result = '';
        $done   = 0;
        $open   = null;
        $codes  = false;

        foreach ($found as $match) {
            [$whole, $at] = $match[0];
            $end          = $at + \strlen($whole);
            $tag          = strtolower($match['tag'][0] ?? '');

            if ($tag === '') {
                // A shortcode inside a paragraph waits for its end, any other is replaced at once
                if ($open !== null) {
                    $codes = true;

                    continue;
                }

                $result .= substr($text, $done, $at - $done) . $render(strtolower($match['arguments'][0]))[0];
                $done = $end;

                continue;
            }

            $closes = $match['close'][0] === '/';

            if ($open !== null && $codes) {
                $result .= substr($text, $done, $open - $done);

                if ($tag === 'p' && $closes) {
                    $start   = strpos($text, '>', $open) + 1;
                    $result .= self::paragraph(substr($text, $open, $start - $open), substr($text, $start, $at - $start), $whole, $render);
                    $done    = $end;
                } else {
                    // Not closed before another block: no paragraph to replace
                    $result .= self::inPlace(substr($text, $open, $at - $open), $render);
                    $done    = $at;
                }
            }

            $open  = $tag === 'p' && !$closes ? $at : null;
            $codes = false;
        }

        if ($open !== null && $codes) {
            $result .= substr($text, $done, $open - $done) . self::inPlace(substr($text, $open), $render);
            $done    = \strlen($text);
        }

        return $result . substr($text, $done);
    }

    /**
     * Replaces the shortcodes of one paragraph.
     *
     * @param   string    $open    The opening tag
     * @param   string    $inner   What is between the tags
     * @param   string    $close   The closing tag
     * @param   callable  $render  See replace()
     *
     * @return  string
     *
     * @since   0.3.1
     */
    private static function paragraph(string $open, string $inner, string $close, callable $render): string
    {
        $code  = '~' . self::CODE . '~i';
        $alone = (string) preg_replace(self::FILLER, '', (string) preg_replace($code, '', $inner)) === '';
        $after = '';
        $put   = '';

        $inner = (string) preg_replace_callback(
            $code,
            static function (array $match) use ($render, $alone, &$after, &$put): string {
                [$html, $isBlock] = $render(strtolower($match['arguments']));

                if ($alone) {
                    $put .= $html;

                    return '';
                }

                if ($isBlock) {
                    $after .= $html;

                    return '';
                }

                return $html;
            },
            $inner
        );

        return $alone ? $put : $open . $inner . $close . $after;
    }

    /**
     * Replaces each shortcode where it stands.
     *
     * @param   string    $text    The text
     * @param   callable  $render  See replace()
     *
     * @return  string
     *
     * @since   0.3.1
     */
    private static function inPlace(string $text, callable $render): string
    {
        return (string) preg_replace_callback('~' . self::CODE . '~i', static fn (array $match): string => $render(strtolower($match['arguments']))[0], $text);
    }
}
