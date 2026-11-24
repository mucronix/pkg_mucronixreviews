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
 * A type of objects whose pages are in the graph of Schema.org that Joomla builds itself (the plugin "System -
 * Schema.org"). The rating goes into the node of the object there, no JSON-LD of its own is output.
 *
 * @since  0.4.0
 */
interface GraphSchemaContextInterface
{
    /**
     * Returns the object of a page from the context Joomla passes with its graph.
     *
     * @param   string  $schemaContext  For example com_content.article.5
     *
     * @return  integer  0 if the page is not of an object of this type
     *
     * @since   0.4.0
     */
    public function getSchemaItemId(string $schemaContext): int;

    /**
     * Returns the @id of the node of an object in the graph of Joomla.
     *
     * @param   integer  $itemId  The id of the object
     *
     * @return  string
     *
     * @since   0.4.0
     */
    public function getSchemaNodeId(int $itemId): string;
}
