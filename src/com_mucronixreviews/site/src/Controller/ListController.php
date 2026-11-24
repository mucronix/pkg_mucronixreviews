<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  com_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Mucronix\Component\MucronixReviews\Site\Controller;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\BaseController;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Gives the next page of a list to the script. It only reads: no token is needed.
 *
 * @since  0.3.0
 */
class ListController extends BaseController
{
    use JsonAnswerTrait;

    /**
     * Gives a page of the list.
     *
     * @return  void
     *
     * @throws  \Exception  If it is not the script that asks
     *
     * @since   0.3.0
     */
    public function more(): void
    {
        if (!$this->isJson()) {
            throw new \Exception(Text::_('JERROR_PAGE_NOT_FOUND'), 404);
        }

        $context = $this->number('item_id') > 0 ? $this->input->get('context', '', 'raw') : '';
        $itemId  = $this->number('item_id');
        $offset  = $this->number('offset');

        try {
            $component = $this->app->bootComponent('com_mucronixreviews');

            if (!\is_string($context) || !$component->getContextRegistry()->isViewable($context, $itemId, $this->app->getIdentity())) {
                $this->respond(404, ['success' => false, 'error' => 'unavailable', 'message' => Text::_('COM_MUCRONIXREVIEWS_ERROR_UNAVAILABLE')]);
            }

            $renderer = $component->getBlockRenderer();
            $answer   = [
                'success' => true,
                'html'    => $renderer->renderReviews($context, $itemId, $offset),
                'next'    => $renderer->next($offset, $component->getReviewService()->getStats($context, $itemId)->rating_count),
            ];
        } catch (\Throwable $e) {
            Log::add(\get_class($e) . ': ' . $e->getMessage(), Log::ERROR, 'com_mucronixreviews');

            $this->respond(500, ['success' => false, 'error' => 'unavailable_now', 'message' => Text::_('COM_MUCRONIXREVIEWS_LIST_UNAVAILABLE')]);
        }

        $this->respond(200, $answer);
    }

    /**
     * Reads a number.
     *
     * @param   string  $name  The name
     *
     * @return  integer  0 if it is not one
     *
     * @since   0.3.0
     */
    private function number(string $name): int
    {
        $value = $this->input->get($name, '', 'raw');

        return \is_string($value) && preg_match('~^[1-9]\d{0,9}$~D', $value) ? (int) $value : 0;
    }
}
