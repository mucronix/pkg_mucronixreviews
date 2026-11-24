<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  plg_system_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace YOOtheme;

use Joomla\CMS\Language\Text;
use Mucronix\Plugin\System\MucronixReviews\YOOtheme\Element;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The element "Mucronix Reviews" of the builder of YOOtheme Pro.
 */
return [
    'name'       => 'mucronix_reviews',
    'title'      => Text::_('PLG_SYSTEM_MUCRONIXREVIEWS_ELEMENT'),
    'group'      => 'Mucronix',
    'icon'       => '${url:images/icon.svg}',
    'iconSmall'  => '${url:images/iconSmall.svg}',
    'element'    => true,
    'width'      => 500,
    'defaults'   => [
        'mode'    => 'full',
        'context' => '',
    ],
    'templates'  => [
        'render' => __DIR__ . '/templates/template.php',
    ],
    'transforms' => [
        'render' => static function ($node) {
            $node->props['mucronix_html'] = Element::render($node->props, (bool) app(Config::class)->get('app.isCustomizer'));

            // Nothing to show: no element
            return $node->props['mucronix_html'] !== '';
        },
    ],
    'fields'     => [
        'mode'          => [
            'label'       => Text::_('PLG_SYSTEM_MUCRONIXREVIEWS_ELEMENT_MODE'),
            'description' => Text::_('PLG_SYSTEM_MUCRONIXREVIEWS_ELEMENT_MODE_DESC'),
            'type'        => 'select',
            'options'     => [
                Text::_('PLG_SYSTEM_MUCRONIXREVIEWS_ELEMENT_MODE_FULL')    => 'full',
                Text::_('PLG_SYSTEM_MUCRONIXREVIEWS_ELEMENT_MODE_LIST')    => 'list',
                Text::_('PLG_SYSTEM_MUCRONIXREVIEWS_ELEMENT_MODE_SUMMARY') => 'summary',
            ],
        ],
        'context'       => [
            'label'       => Text::_('PLG_SYSTEM_MUCRONIXREVIEWS_ELEMENT_CONTEXT'),
            'description' => Text::_('PLG_SYSTEM_MUCRONIXREVIEWS_ELEMENT_CONTEXT_DESC'),
            'type'        => 'select',
            'options'     => Element::contextOptions(),
        ],
        'item_id'       => [
            'label'       => Text::_('PLG_SYSTEM_MUCRONIXREVIEWS_ELEMENT_ITEM'),
            'description' => Text::_('PLG_SYSTEM_MUCRONIXREVIEWS_ELEMENT_ITEM_DESC'),
            'source'      => true,
        ],
        'margin_top'    => '${builder.margin_top}',
        'margin_bottom' => '${builder.margin_bottom}',
        'maxwidth'      => '${builder.maxwidth}',
        'block_align'   => '${builder.block_align}',
        'text_align'    => '${builder.text_align}',
        'visibility'    => '${builder.visibility}',
        'name'          => '${builder.name}',
        'source'        => '${builder.source}',
        'id'            => '${builder.id}',
        'status'        => '${builder.status}',
        'class'         => '${builder.cls}',
        'attributes'    => '${builder.attrs}',
    ],
    'fieldset'   => [
        'default' => [
            'type'   => 'tabs',
            'fields' => [
                [
                    'title'  => Text::_('PLG_SYSTEM_MUCRONIXREVIEWS_ELEMENT_TAB_CONTENT'),
                    'fields' => ['mode', 'context', 'item_id'],
                ],
                [
                    'title'  => Text::_('PLG_SYSTEM_MUCRONIXREVIEWS_ELEMENT_TAB_SETTINGS'),
                    'fields' => [
                        [
                            'label'  => Text::_('PLG_SYSTEM_MUCRONIXREVIEWS_ELEMENT_GENERAL'),
                            'type'   => 'group',
                            'fields' => ['margin_top', 'margin_bottom', 'maxwidth', 'block_align', 'text_align', 'visibility'],
                        ],
                    ],
                ],
                '${builder.advanced}',
            ],
        ],
    ],
];
