<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  com_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Mucronix\Component\MucronixReviews\Administrator\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\AdminModel;
use Mucronix\Component\MucronixReviews\Administrator\Exception\ReviewException;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * A review that is edited. It is stored by the service.
 *
 * @since  0.2.0
 */
class ReviewModel extends AdminModel
{
    /**
     * Returns the form.
     *
     * @param   array    $data      Data for the form
     * @param   boolean  $loadData  True if the form is to load its own data
     *
     * @return  \Joomla\CMS\Form\Form|false
     *
     * @since   0.2.0
     */
    public function getForm($data = [], $loadData = true)
    {
        $form = $this->loadForm('com_mucronixreviews.review', 'review', ['control' => 'jform', 'load_data' => $loadData]);

        if ($form && !$this->mayEditState()) {
            $form->setFieldAttribute('state', 'disabled', 'true');
            $form->setFieldAttribute('state', 'filter', 'unset');
        }

        return $form;
    }

    /**
     * Stores the review.
     *
     * @param   array  $data  The data of the form
     *
     * @return  boolean
     *
     * @since   0.2.0
     */
    public function save($data)
    {
        $id    = (int) ($data['id'] ?? $this->getState('review.id'));
        $state = $this->mayEditState() && is_numeric($data['state'] ?? null) ? (int) $data['state'] : null;

        try {
            Factory::getApplication()->bootComponent('com_mucronixreviews')->getReviewService()
                ->updateReview($id, (string) ($data['rating'] ?? ''), (string) ($data['text'] ?? ''), $state);
        } catch (ReviewException $e) {
            $this->setError($e->getMessage());

            return false;
        }

        $this->setState('review.id', $id);
        $this->setState('review.new', false);

        return true;
    }

    /**
     * Returns the data of the form.
     *
     * @return  array|object
     *
     * @since   0.2.0
     */
    protected function loadFormData()
    {
        return Factory::getApplication()->getUserState('com_mucronixreviews.edit.review.data', []) ?: $this->getItem();
    }

    /**
     * Whether the user may change the state.
     *
     * @return  boolean
     *
     * @since   0.2.0
     */
    private function mayEditState(): bool
    {
        return $this->getCurrentUser()->authorise('core.edit.state', 'com_mucronixreviews');
    }
}
