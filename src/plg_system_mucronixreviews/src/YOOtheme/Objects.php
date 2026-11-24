<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  plg_system_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Mucronix\Plugin\System\MucronixReviews\YOOtheme;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\User\User;
use Mucronix\Component\MucronixReviews\Administrator\Context\YoothemeContextInterface;
use Mucronix\Component\MucronixReviews\Administrator\Extension\MucronixReviewsComponent;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The types of objects YOOtheme Pro knows, and whether the visitor may see an object of them.
 *
 * @since  0.5.0
 */
final class Objects
{
    /**
     * What has been asked already: whether an object may be seen
     *
     * @var    boolean[]
     * @since  0.5.0
     */
    private static array $visible = [];

    /**
     * Returns the component, for the output on the site: its addresses do not answer while it is disabled.
     *
     * @return  MucronixReviewsComponent|null  Null if it is not there or disabled
     *
     * @since   0.5.0
     */
    public static function component(): ?MucronixReviewsComponent
    {
        if (!ComponentHelper::isEnabled('com_mucronixreviews')) {
            return null;
        }

        $component = Factory::getApplication()->bootComponent('com_mucronixreviews');

        return $component instanceof MucronixReviewsComponent ? $component : null;
    }

    /**
     * Returns the types of objects YOOtheme Pro knows.
     *
     * @return  YoothemeContextInterface[]  By the name of the type, for example com_content.article
     *
     * @since   0.5.0
     */
    public static function handlers(): array
    {
        $component = self::component();

        if ($component === null) {
            return [];
        }

        return array_filter($component->getContextRegistry()->getHandlers(), static fn ($handler): bool => $handler instanceof YoothemeContextInterface);
    }

    /**
     * Whether the visitor may see an object. Of the object of the page the article has to be viewable, of any other
     * one open for reviews: the average of a hidden object is not told.
     *
     * @param   string   $context  The type of the object
     * @param   integer  $itemId   The id of the object
     *
     * @return  boolean
     *
     * @since   0.5.0
     */
    public static function visible(string $context, int $itemId): bool
    {
        $handler   = self::handlers()[$context] ?? null;
        $component = self::component();

        if ($itemId < 1 || $handler === null || $component === null) {
            return false;
        }

        $key = $context . '.' . $itemId;

        if (!isset(self::$visible[$key])) {
            $user     = Factory::getApplication()->getIdentity() ?? new User();
            $registry = $component->getContextRegistry();

            self::$visible[$key] = $itemId === $handler->getPageItemId()
                ? $registry->isViewable($context, $itemId, $user)
                : $handler->isAvailable($itemId, $user);
        }

        return self::$visible[$key];
    }
}
