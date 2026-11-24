<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  com_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Mucronix\Component\MucronixReviews\Administrator\View\Reviews;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Registry\Registry;
use Mucronix\Component\MucronixReviews\Administrator\Model\ReviewsModel;
use Mucronix\Component\MucronixReviews\Administrator\Service\ModerationRules;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The list of the reviews.
 *
 * @since  0.2.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * The form of the filters
     *
     * @var    Form
     * @since  0.2.0
     */
    public $filterForm;

    /**
     * The filters in use
     *
     * @var    array
     * @since  0.2.0
     */
    public $activeFilters = [];

    /**
     * The reviews
     *
     * @var    object[]
     * @since  0.2.0
     */
    protected $items = [];

    /**
     * The pagination
     *
     * @var    Pagination
     * @since  0.2.0
     */
    protected $pagination;

    /**
     * The state of the model
     *
     * @var    Registry
     * @since  0.2.0
     */
    protected $state;

    /**
     * Shows the page.
     *
     * @param   string  $tpl  The name of the template file to parse
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function display($tpl = null): void
    {
        /** @var ReviewsModel $model */
        $model = $this->getModel();
        $model->setUseExceptions(true);

        $this->items         = $model->getItems();
        $this->pagination    = $model->getPagination();
        $this->state         = $model->getState();
        $this->filterForm    = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();

        $isEmpty = !\count($this->items) && $model->getIsEmptyState();

        if ($isEmpty) {
            $this->setLayout('emptystate');
        }

        $this->addToolbar($isEmpty);

        $this->filterForm
            ->addControlField('task')
            ->addControlField('boxchecked', '0');

        parent::display($tpl);
    }

    /**
     * The states for the buttons in the rows: task, label, title of the button, label without a button, tip, icons.
     * A published review goes back to moderation, any other gets published.
     *
     * @return  array
     *
     * @since   0.2.0
     */
    protected function rowStates(): array
    {
        return [
            ModerationRules::PUBLISHED => ['pending', 'COM_MUCRONIXREVIEWS_STATE_PUBLISHED', 'COM_MUCRONIXREVIEWS_TO_PENDING', 'COM_MUCRONIXREVIEWS_STATE_PUBLISHED', true, 'publish', 'publish'],
            ModerationRules::PENDING   => ['publish', 'COM_MUCRONIXREVIEWS_STATE_PENDING', 'JTOOLBAR_PUBLISH', 'COM_MUCRONIXREVIEWS_STATE_PENDING', true, 'pending', 'pending'],
            ModerationRules::REJECTED  => ['publish', 'COM_MUCRONIXREVIEWS_STATE_REJECTED', 'JTOOLBAR_PUBLISH', 'COM_MUCRONIXREVIEWS_STATE_REJECTED', true, 'unpublish', 'unpublish'],
            ModerationRules::SPAM      => ['publish', 'COM_MUCRONIXREVIEWS_STATE_SPAM', 'JTOOLBAR_PUBLISH', 'COM_MUCRONIXREVIEWS_STATE_SPAM', true, 'flag', 'flag'],
            ModerationRules::TRASHED   => ['publish', 'JTRASHED', 'JTOOLBAR_PUBLISH', 'JTRASHED', true, 'trash', 'trash'],
        ];
    }

    /**
     * Adds the buttons.
     *
     * @param   boolean  $isEmpty  Whether there are no reviews at all
     *
     * @return  void
     *
     * @since   0.2.0
     */
    private function addToolbar(bool $isEmpty): void
    {
        $canDo   = ContentHelper::getActions('com_mucronixreviews');
        $toolbar = $this->getDocument()->getToolbar();
        $trash   = (string) $this->state->get('filter.state') === (string) ModerationRules::TRASHED;

        ToolbarHelper::title(Text::_('COM_MUCRONIXREVIEWS_REVIEWS_TITLE'), 'star');

        if (!$isEmpty && $canDo->get('core.edit.state')) {
            $bar = $toolbar->dropdownButton('status-group', 'JTOOLBAR_CHANGE_STATUS')
                ->toggleSplit(false)
                ->icon('icon-ellipsis-h')
                ->buttonClass('btn btn-action')
                ->listCheck(true)
                ->getChildToolbar();

            $bar->publish('reviews.publish')->listCheck(true);
            $bar->standardButton('pending', 'COM_MUCRONIXREVIEWS_TO_PENDING', 'reviews.pending')->icon('icon-pending')->listCheck(true);
            $bar->standardButton('reject', 'COM_MUCRONIXREVIEWS_REJECT', 'reviews.reject')->icon('icon-unpublish')->listCheck(true);
            $bar->standardButton('spam', 'COM_MUCRONIXREVIEWS_TO_SPAM', 'reviews.spam')->icon('icon-flag')->listCheck(true);

            if (!$trash) {
                $bar->trash('reviews.trash')->listCheck(true);
            }
        }

        if (!$isEmpty && $trash && $canDo->get('core.delete')) {
            $toolbar->delete('reviews.delete', 'COM_MUCRONIXREVIEWS_DELETE_FOR_GOOD')
                ->message('JGLOBAL_CONFIRM_DELETE')
                ->listCheck(true);
        }

        if ($canDo->get('core.edit.state')) {
            $toolbar->standardButton('recalculate', 'COM_MUCRONIXREVIEWS_RECALCULATE', 'reviews.recalculate')->icon('icon-refresh');
        }

        if ($canDo->get('core.admin') || $canDo->get('core.options')) {
            $toolbar->preferences('com_mucronixreviews');
        }
    }
}
