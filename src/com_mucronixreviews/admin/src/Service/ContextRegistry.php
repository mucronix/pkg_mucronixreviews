<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  com_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Mucronix\Component\MucronixReviews\Administrator\Service;

use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\User\User;
use Joomla\Event\DispatcherInterface;
use Mucronix\Component\MucronixReviews\Administrator\Context\ContextHandlerInterface;
use Mucronix\Component\MucronixReviews\Administrator\Context\ViewableContextInterface;
use Mucronix\Component\MucronixReviews\Administrator\Event\RegisterContextsEvent;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The types of objects that can be reviewed, as the adapters have registered them.
 *
 * @since  0.1.0
 */
class ContextRegistry
{
    /**
     * The types, by name. Null until they are asked for.
     *
     * @var    ContextHandlerInterface[]|null
     * @since  0.1.0
     */
    private ?array $handlers = null;

    /**
     * The objects that were described, by type and id
     *
     * @var    object[]
     * @since  0.2.0
     */
    private array $described = [];

    /**
     * Constructor
     *
     * @param   DispatcherInterface  $dispatcher  The event dispatcher
     *
     * @since   0.1.0
     */
    public function __construct(private readonly DispatcherInterface $dispatcher)
    {
    }

    /**
     * Returns all the types.
     *
     * @return  ContextHandlerInterface[]
     *
     * @since   0.1.0
     */
    public function getHandlers(): array
    {
        if ($this->handlers === null) {
            // The system plugins are there already, the adapters of other groups are not
            PluginHelper::importPlugin('content', null, true, $this->dispatcher);
            PluginHelper::importPlugin('mucronixreviews', null, true, $this->dispatcher);

            $event = new RegisterContextsEvent();

            $this->dispatcher->dispatch(RegisterContextsEvent::NAME, $event);

            $this->handlers = $event->getHandlers();
        }

        return $this->handlers;
    }

    /**
     * Returns a type.
     *
     * @param   string  $context  The name of the type
     *
     * @return  ContextHandlerInterface|null  Null if nobody has registered it
     *
     * @since   0.1.0
     */
    public function getHandler(string $context): ?ContextHandlerInterface
    {
        return $this->getHandlers()[$context] ?? null;
    }

    /**
     * Whether a user may see an object and its reviews.
     *
     * @param   string   $context  The name of the type
     * @param   integer  $itemId   The id of the object
     * @param   User     $user     The user
     *
     * @return  boolean
     *
     * @since   0.3.0
     */
    public function isViewable(string $context, int $itemId, User $user): bool
    {
        $handler = $this->getHandler($context);

        if ($handler === null || $itemId < 1) {
            return false;
        }

        return $handler instanceof ViewableContextInterface ? $handler->isViewable($itemId, $user) : $handler->isAvailable($itemId, $user);
    }

    /**
     * Describes an object for the administrator. Never fails: without its adapter, or with a broken one, the object goes
     * by its type and id.
     *
     * @param   string   $context  The name of the type
     * @param   integer  $itemId   The id of the object, 0 for the type alone
     *
     * @return  object  type, title and url. Plain text: it has to be escaped. The url may be empty.
     *
     * @since   0.2.0
     */
    public function describe(string $context, int $itemId = 0): object
    {
        $key = $context . '.' . $itemId;

        if (isset($this->described[$key])) {
            return $this->described[$key];
        }

        $object  = (object) ['type' => $context, 'title' => '#' . $itemId, 'url' => ''];
        $handler = $this->getHandler($context);

        if ($handler !== null) {
            try {
                $object->type = $handler->getLabel() ?: $context;

                if ($itemId > 0) {
                    $object->title = $handler->getTitle($itemId) ?: $object->title;
                    $url           = $handler->getUrl($itemId);

                    // An address of this or another site, not a script
                    $object->url = preg_match('~^(?:https?://|/(?!/))~i', $url) ? $url : '';
                }
            } catch (\Throwable $e) {
                Log::add($key . ': ' . $e->getMessage(), Log::ERROR, 'com_mucronixreviews');
            }
        }

        return $this->described[$key] = $object;
    }
}
