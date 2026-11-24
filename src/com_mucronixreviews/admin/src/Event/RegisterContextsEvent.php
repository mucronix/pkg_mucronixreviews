<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  com_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Mucronix\Component\MucronixReviews\Administrator\Event;

use Joomla\Event\Event;
use Mucronix\Component\MucronixReviews\Administrator\Context\ContextHandlerInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The event on which adapters register the types of objects that can be reviewed.
 *
 * @since  0.1.0
 */
class RegisterContextsEvent extends Event
{
    /**
     * The name of the event
     *
     * @var    string
     * @since  0.1.0
     */
    public const NAME = 'onMucronixReviewsRegisterContexts';

    /**
     * The registered types, by name
     *
     * @var    ContextHandlerInterface[]
     * @since  0.1.0
     */
    private array $handlers = [];

    /**
     * Constructor
     *
     * @since   0.1.0
     */
    public function __construct()
    {
        parent::__construct(self::NAME);
    }

    /**
     * Registers a type. The first to register a name keeps it.
     *
     * @param   ContextHandlerInterface  $handler  The type
     *
     * @return  void
     *
     * @throws  \InvalidArgumentException  If the name cannot be stored
     *
     * @since   0.1.0
     */
    public function addHandler(ContextHandlerInterface $handler): void
    {
        $context = $handler->getContext();

        if (!preg_match('~^[a-z0-9_.-]{1,100}$~D', $context)) {
            throw new \InvalidArgumentException('Not a name of a type of objects: ' . $context);
        }

        $this->handlers[$context] ??= $handler;
    }

    /**
     * Returns the registered types.
     *
     * @return  ContextHandlerInterface[]
     *
     * @since   0.1.0
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }
}
