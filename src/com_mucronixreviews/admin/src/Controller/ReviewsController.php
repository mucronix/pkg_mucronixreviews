<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  com_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Mucronix\Component\MucronixReviews\Administrator\Controller;

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Application\CMSWebApplicationInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Router\Route;
use Joomla\Input\Input;
use Mucronix\Component\MucronixReviews\Administrator\Service\ModerationRules;
use Mucronix\Component\MucronixReviews\Administrator\Service\ReviewService;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The actions of the list. Not AdminController: it checks no rights, does not know "reject" and reports "spam" as
 * moved to the trash.
 *
 * @since  0.2.0
 */
class ReviewsController extends BaseController
{
    /**
     * The state each task sets
     *
     * @var    integer[]
     * @since  0.2.0
     */
    private const TASKS = [
        'publish' => ModerationRules::PUBLISHED,
        'pending' => ModerationRules::PENDING,
        'reject'  => ModerationRules::REJECTED,
        'spam'    => ModerationRules::SPAM,
        'trash'   => ModerationRules::TRASHED,
    ];

    /**
     * Constructor
     *
     * @param   array                         $config   An optional associative array of configuration settings
     * @param   ?MVCFactoryInterface          $factory  The factory
     * @param   ?CMSWebApplicationInterface   $app      The application
     * @param   ?Input                        $input    The input
     *
     * @since   0.2.0
     */
    public function __construct($config = [], ?MVCFactoryInterface $factory = null, ?CMSWebApplicationInterface $app = null, ?Input $input = null)
    {
        parent::__construct($config, $factory, $app, $input);

        foreach (array_keys(self::TASKS) as $task) {
            $this->registerTask($task, 'setState');
        }
    }

    /**
     * Sets the state of the chosen reviews.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function setState(): void
    {
        $this->checkToken();
        $this->requireRight('core.edit.state');

        $task = $this->getTask();

        if (!isset(self::TASKS[$task])) {
            throw new \Exception(Text::_('JERROR_PAGE_NOT_FOUND'), 404);
        }

        if ($ids = $this->chosen()) {
            $changed = $this->service()->setState($ids, self::TASKS[$task]);

            $this->setMessage(Text::plural('COM_MUCRONIXREVIEWS_N_ITEMS_' . strtoupper($task), $changed));
        }

        $this->toList();
    }

    /**
     * Deletes the chosen reviews for good. The service deletes only those in the trash.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function delete(): void
    {
        $this->checkToken();
        $this->requireRight('core.delete');

        if ($ids = $this->chosen()) {
            $this->setMessage(Text::plural('COM_MUCRONIXREVIEWS_N_ITEMS_DELETED', $this->service()->deleteTrashed($ids)));
        }

        $this->toList();
    }

    /**
     * Counts the statistics of all objects anew.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function recalculate(): void
    {
        $this->checkToken();
        $this->requireRight('core.edit.state');

        $this->setMessage(Text::plural('COM_MUCRONIXREVIEWS_N_OBJECTS_RECALCULATED', $this->service()->recalculateAll()));
        $this->toList();
    }

    /**
     * Returns the ids of the chosen reviews, or tells that there are none.
     *
     * @return  integer[]
     *
     * @since   0.2.0
     */
    private function chosen(): array
    {
        $ids = array_filter((array) $this->input->post->get('cid', [], 'int'));

        if (!$ids) {
            $this->setMessage(Text::_('JLIB_HTML_PLEASE_MAKE_A_SELECTION_FROM_THE_LIST'), 'warning');
        }

        return $ids;
    }

    /**
     * Checks a right of the user.
     *
     * @param   string  $action  The right
     *
     * @return  void
     *
     * @throws  NotAllowed
     *
     * @since   0.2.0
     */
    private function requireRight(string $action): void
    {
        if (!$this->app->getIdentity()->authorise($action, 'com_mucronixreviews')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    /**
     * Returns the reviews.
     *
     * @return  ReviewService
     *
     * @since   0.2.0
     */
    private function service(): ReviewService
    {
        return $this->app->bootComponent('com_mucronixreviews')->getReviewService();
    }

    /**
     * Leads back to the list.
     *
     * @return  void
     *
     * @since   0.2.0
     */
    private function toList(): void
    {
        $this->setRedirect(Route::_('index.php?option=com_mucronixreviews&view=reviews', false));
    }
}
