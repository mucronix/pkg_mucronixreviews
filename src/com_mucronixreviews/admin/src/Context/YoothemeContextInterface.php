<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  com_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Mucronix\Component\MucronixReviews\Administrator\Context;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * A type of objects that YOOtheme Pro knows: its types get the fields and the list of reviews, the element of the
 * builder shows its block.
 *
 * @since  0.5.0
 */
interface YoothemeContextInterface
{
    /**
     * Returns the types of YOOtheme whose objects are objects of this type.
     *
     * @return  string[]  For example Article
     *
     * @since   0.5.0
     */
    public function getYoothemeTypes(): array;

    /**
     * Returns the id of an object YOOtheme passes.
     *
     * @param   object  $item  The object of YOOtheme
     *
     * @return  integer  0 if it has none
     *
     * @since   0.5.0
     */
    public function getYoothemeItemId(object $item): int;

    /**
     * Returns the object of the page that is open.
     *
     * @return  integer  0 if the page is not of an object of this type
     *
     * @since   0.5.0
     */
    public function getPageItemId(): int;
}
