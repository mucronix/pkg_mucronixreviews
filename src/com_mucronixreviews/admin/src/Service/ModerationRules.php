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
 * The states of a review and how they come about.
 *
 * @since  0.1.0
 */
final class ModerationRules
{
    public const PUBLISHED = 1;
    public const PENDING   = 0;
    public const REJECTED  = -1;
    public const TRASHED   = -2;
    public const SPAM      = -3;

    /**
     * All the states
     *
     * @var    integer[]
     * @since  0.1.0
     */
    public const STATES = [self::PUBLISHED, self::PENDING, self::REJECTED, self::TRASHED, self::SPAM];

    /**
     * The state of a new review.
     *
     * @param   Settings  $settings    The options
     * @param   boolean   $hasContent  Whether it has a text
     * @param   boolean   $hasLinks    Whether it holds a link
     *
     * @return  integer
     *
     * @since   0.1.0
     */
    public static function stateOfNew(Settings $settings, bool $hasContent, bool $hasLinks): int
    {
        $waits = $settings->moderation() === 'all'
            || ($settings->moderation() === 'text' && $hasContent)
            || ($settings->links() === 'moderate' && $hasLinks);

        return $waits ? self::PENDING : self::PUBLISHED;
    }

    /**
     * The state of a review its author has changed.
     *
     * @param   Settings  $settings        The options
     * @param   integer   $state           The state it had
     * @param   boolean   $contentChanged  Whether the text has changed
     * @param   boolean   $hasLinks        Whether they hold a link
     *
     * @return  integer
     *
     * @since   0.1.0
     */
    public static function stateOfEdited(Settings $settings, int $state, bool $contentChanged, bool $hasLinks): int
    {
        if (!$contentChanged || $state === self::SPAM) {
            return $state;
        }

        // What a moderator took down does not come back without a moderator
        if ($state !== self::PUBLISHED) {
            return self::PENDING;
        }

        $waits = $settings->moderation() !== 'none' || ($settings->links() === 'moderate' && $hasLinks);

        return $waits ? self::PENDING : self::PUBLISHED;
    }

    /**
     * Whether a review its author has sent has just come to wait for a moderator. One that waited already does not
     * come again: the moderators know of it.
     *
     * @param   integer|null  $before  The state it had, null for a new one
     * @param   integer       $after   The state it has now
     *
     * @return  boolean
     *
     * @since   0.6.0
     */
    public static function comesToModeration(?int $before, int $after): bool
    {
        return $after === self::PENDING && $before !== self::PENDING;
    }
}
