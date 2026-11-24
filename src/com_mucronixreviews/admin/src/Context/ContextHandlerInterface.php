<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  com_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Mucronix\Component\MucronixReviews\Administrator\Context;

use Joomla\CMS\User\User;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * A type of objects that can be reviewed. An adapter implements it and registers it on the event
 * onMucronixReviewsRegisterContexts.
 *
 * @since  0.1.0
 */
interface ContextHandlerInterface
{
    /**
     * Returns the name of the type, for example "com_content.article".
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public function getContext(): string;

    /**
     * Returns the translated label of the type for the administrator.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public function getLabel(): string;

    /**
     * Whether the object exists, is published and the user has access to it.
     *
     * @param   integer  $itemId  The id of the object
     * @param   User     $user    The user
     *
     * @return  boolean
     *
     * @since   0.1.0
     */
    public function isAvailable(int $itemId, User $user): bool;

    /**
     * Returns the title of the object.
     *
     * @param   integer  $itemId  The id of the object
     *
     * @return  string  Empty if there is no such object
     *
     * @since   0.1.0
     */
    public function getTitle(int $itemId): string;

    /**
     * Returns the address of the object on the site, also when the administrator asks: with the host or from the root
     * of the host.
     *
     * @param   integer  $itemId  The id of the object
     *
     * @return  string  Empty if there is no such object
     *
     * @since   0.1.0
     */
    public function getUrl(int $itemId): string;

    /**
     * Returns the Schema.org type of the objects.
     *
     * @return  string|null  Null if they get no markup
     *
     * @since   0.1.0
     */
    public function getSchemaType(): ?string;
}
