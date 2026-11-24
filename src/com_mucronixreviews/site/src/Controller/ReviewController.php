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
use Joomla\CMS\Uri\Uri;
use Mucronix\Component\MucronixReviews\Administrator\Exception\ReviewException;
use Mucronix\Component\MucronixReviews\Administrator\Extension\MucronixReviewsComponent;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Takes a review from the form: with format=json the answer is JSON, without it a redirect back to the page.
 * The checks are in the service.
 *
 * @since  0.1.0
 */
class ReviewController extends BaseController
{
    use JsonAnswerTrait;

    /**
     * Saves the review of the user who is logged in.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function save(): void
    {
        $context = $this->text('context');
        $itemId  = $this->itemId();
        $text    = $this->text('text');
        $rating  = $this->text('rating');

        try {
            if ($this->input->getMethod() !== 'POST') {
                $this->app->setHeader('Allow', 'POST', true);

                throw new ReviewException('method', 405);
            }

            $result = $this->component()->getReviewService()
                ->saveReview($context, $itemId, (int) $this->app->getIdentity()->id, $rating, $text);
        } catch (ReviewException $e) {
            $this->keepForm($context, $itemId, ['rating' => $rating, 'text' => $text], $e);
            $this->refuse($e->getCode(), $e->getReason(), $e->getMessage(), $context, $itemId);

            return;
        } catch (\Throwable $e) {
            // What went wrong inside is for the log, not for the visitor
            Log::add(\get_class($e) . ': ' . $e->getMessage(), Log::ERROR, 'com_mucronixreviews');

            $this->refuse(500, 'unavailable_now', Text::_('COM_MUCRONIXREVIEWS_ERROR_UNAVAILABLE_NOW'), $context, $itemId);

            return;
        }

        $this->app->setUserState($this->formKey($context, $itemId), null);

        $message = Text::_($result->isPublished() ? 'COM_MUCRONIXREVIEWS_SAVED_PUBLISHED' : 'COM_MUCRONIXREVIEWS_SAVED_PENDING');

        if ($this->isJson()) {
            $stats   = $this->component()->getReviewService()->getStats($context, $itemId);
            $options = ['list' => $this->text('list') !== '0', 'return' => $this->returnUrl($context, $itemId)];

            $this->respond(
                200,
                [
                    'success'   => true,
                    'published' => $result->isPublished(),
                    'created'   => $result->created,
                    'message'   => $message,
                    'stats'     => ['average' => $stats->rating_average, 'ratings' => $stats->rating_count, 'texts' => $stats->text_count],
                    'html'      => $this->component()->getBlockRenderer()->renderBody($context, $itemId, $options),
                ]
            );
        }

        $this->setRedirect($this->returnUrl($context, $itemId), $message, 'success');
    }

    /**
     * Answers a review that is refused.
     *
     * @param   integer  $status   The HTTP status
     * @param   string   $reason   The reason, for the script
     * @param   string   $message  The reason, for the visitor
     * @param   string   $context  The type of the object
     * @param   integer  $itemId   The id of the object
     *
     * @return  void
     *
     * @since   0.1.0
     */
    private function refuse(int $status, string $reason, string $message, string $context, int $itemId): void
    {
        if ($this->isJson()) {
            $this->respond($status, ['success' => false, 'error' => $reason, 'message' => $message]);
        }

        $this->setRedirect($this->returnUrl($context, $itemId), $message, 'error');
    }

    /**
     * Keeps what was typed for the form that is shown again. Only for an object that exists: a form for anything else
     * is not shown.
     *
     * @param   string           $context  The type of the object
     * @param   integer          $itemId   The id of the object
     * @param   array            $values   What was typed
     * @param   ReviewException  $refusal  Why it was refused
     *
     * @return  void
     *
     * @since   0.1.0
     */
    private function keepForm(string $context, int $itemId, array $values, ReviewException $refusal): void
    {
        if ($this->isJson() || !\in_array($refusal->getCode(), [422, 429], true)) {
            return;
        }

        $this->app->setUserState($this->formKey($context, $itemId), array_map(static fn (string $value): string => mb_substr(mb_scrub($value), 0, 20000), $values));
    }

    /**
     * The place in the session of what was typed.
     *
     * @param   string   $context  The type of the object
     * @param   integer  $itemId   The id of the object
     *
     * @return  string
     *
     * @since   0.1.0
     */
    private function formKey(string $context, int $itemId): string
    {
        return 'com_mucronixreviews.form.' . $context . '.' . $itemId;
    }

    /**
     * Where to go back to: the page of the form, else the object, else the home page. Never another site.
     *
     * @param   string   $context  The type of the object
     * @param   integer  $itemId   The id of the object
     *
     * @return  string
     *
     * @since   0.1.0
     */
    private function returnUrl(string $context, int $itemId): string
    {
        $url = (string) base64_decode($this->text('return'), true);

        if ($url !== '' && Uri::isInternal($url)) {
            return $url;
        }

        $handler = $this->component()->getContextRegistry()->getHandler($context);
        $url     = $handler && $itemId > 0 && $handler->isAvailable($itemId, $this->app->getIdentity()) ? $handler->getUrl($itemId) : '';

        return $url !== '' ? $url : Uri::base();
    }

    /**
     * Returns the component.
     *
     * @return  MucronixReviewsComponent
     *
     * @since   0.1.0
     */
    private function component(): MucronixReviewsComponent
    {
        return $this->app->bootComponent('com_mucronixreviews');
    }

    /**
     * Reads the id of the object.
     *
     * @return  integer  0 if it is not one
     *
     * @since   0.1.0
     */
    private function itemId(): int
    {
        $value = $this->text('item_id');

        return preg_match('~^[1-9]\d{0,9}$~D', $value) ? (int) $value : 0;
    }

    /**
     * Reads a value of the form as it was sent. An array is not a value.
     *
     * @param   string  $name  The name
     *
     * @return  string
     *
     * @since   0.1.0
     */
    private function text(string $name): string
    {
        $value = $this->input->post->get($name, '', 'raw');

        return \is_string($value) ? $value : '';
    }
}
