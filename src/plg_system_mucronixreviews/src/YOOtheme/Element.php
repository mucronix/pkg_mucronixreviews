<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  plg_system_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Mucronix\Plugin\System\MucronixReviews\YOOtheme;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The element "Mucronix Reviews" of the builder: the summary, the summary and the reviews, or all with the form, of the
 * object of the page or of the one its field names. It stands where the block under an article cannot: in the templates
 * of the builder.
 *
 * @since  0.5.0
 */
final class Element
{
    /**
     * What the element can show
     *
     * @var    string[]
     * @since  0.5.0
     */
    public const MODES = ['full', 'list', 'summary'];

    /**
     * Shows what the element is set to.
     *
     * @param   array    $props    mode, context, item_id
     * @param   boolean  $preview  Whether the page is the preview of the builder (since 0.5.1)
     *
     * @return  string  Empty if there is no object or the visitor may not see it, or if the block of the object is on
     *                  the page already and it is not the preview: the element is not shown
     *
     * @since   0.5.0
     */
    public static function render(array $props, bool $preview = false): string
    {
        $handlers = Objects::handlers();
        $context  = (string) ($props['context'] ?? '');
        $itemId   = (int) ($props['item_id'] ?? 0);

        if ($context === '') {
            // The type of the object of the page
            foreach ($handlers as $name => $handler) {
                if ($handler->getPageItemId() > 0) {
                    $context = $name;
                    break;
                }
            }

            // No object on the page, but an id: an article, as for the shortcode (since 0.5.2)
            if ($context === '' && $itemId > 0) {
                $context = 'com_content.article';
            }
        }

        $handler = $handlers[$context] ?? null;

        if ($handler === null) {
            return '';
        }

        if ($itemId < 1) {
            $itemId = $handler->getPageItemId();
        }

        if (!Objects::visible($context, $itemId)) {
            return '';
        }

        $renderer = Objects::component()->getBlockRenderer();
        $mode     = \in_array($props['mode'] ?? '', self::MODES, true) ? $props['mode'] : 'full';

        if ($mode === 'summary') {
            return $renderer->renderSummary($context, $itemId);
        }

        // From the page of the object the login comes back to its own address: the page is cached for guests
        $own = $itemId === $handler->getPageItemId() ? $handler->getUrl($itemId) : '';

        $html = $renderer->renderBlock($context, $itemId, ['form' => $mode === 'full', 'list' => true, 'return' => $own !== '' ? $own : Uri::getInstance()->toString()]);

        if ($html === '' && $preview) {
            // The block is on the page already: the builder says why the element is empty, the site shows nothing
            return '<div class="uk-alert uk-alert-warning">' . htmlspecialchars(Text::_('PLG_SYSTEM_MUCRONIXREVIEWS_ELEMENT_ONCE'), ENT_QUOTES, 'UTF-8') . '</div>';
        }

        return $html;
    }

    /**
     * The choice of the type of objects for the settings of the element.
     *
     * @return  string[]  The label by the value
     *
     * @since   0.5.0
     */
    public static function contextOptions(): array
    {
        $options = [Text::_('PLG_SYSTEM_MUCRONIXREVIEWS_ELEMENT_CONTEXT_PAGE') => ''];

        foreach (Objects::handlers() as $context => $handler) {
            $options[$handler->getLabel()] = $context;
        }

        return $options;
    }
}
