<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  plg_system_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Mucronix\Plugin\System\MucronixReviews\YOOtheme;

use YOOtheme\Builder;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The module of Mucronix Reviews for YOOtheme Pro: the fields and the list of reviews of the types of objects, and the
 * element of the builder. YOOtheme Pro loads it as its own modules.
 */
return [
    'events' => [
        'source.init' => [
            SourceListener::class => 'handle',
        ],
    ],

    'extend' => [
        Builder::class => static function (Builder $builder): void {
            $builder->addType('mucronix_reviews', __DIR__ . '/elements/mucronix_reviews/element.php');
        },
    ],
];
