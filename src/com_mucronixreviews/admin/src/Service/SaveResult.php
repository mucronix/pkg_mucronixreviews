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
 * What has become of a review that was sent.
 *
 * @since  0.1.0
 */
final class SaveResult
{
    /**
     * Constructor
     *
     * @param   integer       $id        The id of the review
     * @param   integer       $state     Its state
     * @param   boolean       $created   Whether it is new
     * @param   integer|null  $previous  The state it had before, null for a new one (since 0.6.0)
     * @param   boolean       $hasLinks  Whether its text holds a link (since 0.6.0)
     *
     * @since   0.1.0
     */
    public function __construct(
        public readonly int $id,
        public readonly int $state,
        public readonly bool $created,
        public readonly ?int $previous = null,
        public readonly bool $hasLinks = false
    ) {
    }

    /**
     * Whether the moderators are told about the review: it has just come to wait for them.
     *
     * @return  boolean
     *
     * @since   0.6.0
     */
    public function needsModerator(): bool
    {
        return ModerationRules::comesToModeration($this->previous, $this->state);
    }

    /**
     * Whether visitors see the review.
     *
     * @return  boolean
     *
     * @since   0.1.0
     */
    public function isPublished(): bool
    {
        return $this->state === ModerationRules::PUBLISHED;
    }
}
