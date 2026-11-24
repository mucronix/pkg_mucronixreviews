<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  com_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Mucronix\Component\MucronixReviews\Site\Service;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Mucronix\Component\MucronixReviews\Administrator\Service\ContextRegistry;
use Mucronix\Component\MucronixReviews\Administrator\Service\ModerationRules;
use Mucronix\Component\MucronixReviews\Administrator\Service\ReviewService;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Shows the reviews on the site, by the layouts of the component. A template replaces a layout with
 * html/layouts/com_mucronixreviews/<name>.php.
 *
 * Whether the visitor may see the object is checked by the caller.
 *
 * @since  0.3.0
 */
class BlockRenderer
{
    /**
     * The classes of UIkit, for a template of YOOtheme Pro
     *
     * @var    string[]
     * @since  0.5.0
     */
    public const UIKIT = [
        'kit'      => 'uikit',
        'button'   => 'uk-button uk-button-default',
        'submit'   => 'uk-button uk-button-primary',
        'textarea' => 'uk-textarea',
        'notice'   => 'uk-alert',
        'success'  => 'uk-alert uk-alert-success',
        'error'    => 'uk-alert uk-alert-danger',
    ];

    /**
     * The objects whose whole block is on the page: one for a page, however it is put there
     *
     * @var    boolean[]
     * @since  0.5.0
     */
    private array $blocks = [];

    /**
     * The classes of the template, once worked out
     *
     * @var    string[]|null
     * @since  0.5.0
     */
    private ?array $ui = null;

    /**
     * Constructor
     *
     * @param   ReviewService    $reviews   The reviews
     * @param   ContextRegistry  $contexts  The types of objects
     *
     * @since   0.3.0
     */
    public function __construct(private readonly ReviewService $reviews, private readonly ContextRegistry $contexts)
    {
    }

    /**
     * Shows the block: the summary, the form or a notice instead of it, and the list.
     *
     * @param   string   $context  The type of the object
     * @param   integer  $itemId   The id of the object
     * @param   array    $options  form and list: whether they are shown, yes if left out; return: the address of the page
     *
     * @return  string  Empty if the block of the object is on the page already
     *
     * @since   0.3.0
     */
    public function renderBlock(string $context, int $itemId, array $options = []): string
    {
        if (isset($this->blocks[$context . '.' . $itemId])) {
            return '';
        }

        $this->blocks[$context . '.' . $itemId] = true;

        $this->prepare();

        return $this->layout(
            'block',
            [
                'anchor' => $this->anchor($context, $itemId),
                'body'   => $this->renderBody($context, $itemId, $options),
            ]
        );
    }

    /**
     * Shows what is in the block.
     *
     * @param   string   $context  The type of the object
     * @param   integer  $itemId   The id of the object
     * @param   array    $options  As for renderBlock()
     *
     * @return  string
     *
     * @since   0.3.0
     */
    public function renderBody(string $context, int $itemId, array $options = []): string
    {
        $this->prepare();

        $showList = $options['list'] ?? true;
        $stats    = $this->reviews->getStats($context, $itemId);

        return $this->layout(
            'body',
            [
                'summary' => $this->renderSummary($context, $itemId),
                'form'    => ($options['form'] ?? true) ? $this->renderForm($context, $itemId, $options['return'] ?? Uri::getInstance()->toString(), $showList) : '',
                'list'    => $showList && $stats->rating_count > 0 ? $this->renderList($context, $itemId, $stats->rating_count) : '',
            ]
        );
    }

    /**
     * Shows the summary.
     *
     * @param   string   $context  The type of the object
     * @param   integer  $itemId   The id of the object
     *
     * @return  string
     *
     * @since   0.3.0
     */
    public function renderSummary(string $context, int $itemId): string
    {
        $this->prepare();

        $stats = $this->reviews->getStats($context, $itemId);

        return $this->layout(
            'summary',
            [
                'stars'   => $this->renderStars($stats->rating_average),
                'average' => number_format($stats->rating_average, 1, Text::_('COM_MUCRONIXREVIEWS_DECIMAL_SEPARATOR'), ''),
                'ratings' => $stats->rating_count,
                'object'  => $context . '.' . $itemId,
            ]
        );
    }

    /**
     * Shows stars, rounded as the options say.
     *
     * @param   float   $rating  The rating, 0 to 5
     * @param   string  $label   What a screen reader says. Empty if the text next to the stars says it.
     *
     * @return  string
     *
     * @since   0.3.0
     */
    public function renderStars(float $rating, string $label = ''): string
    {
        $rating = min(5, max(0, $rating));
        $fill   = $this->reviews->getSettings()->rounding() === 'whole' ? 10 * (int) round($rating) : 5 * (int) round($rating * 2);

        return $this->layout('stars', ['fill' => $fill, 'label' => $label]);
    }

    /**
     * Shows the stars of one review, with what a screen reader says.
     *
     * @param   integer  $rating  The stars, 1 to 5
     *
     * @return  string
     *
     * @since   0.5.0
     */
    public function renderRating(int $rating): string
    {
        $this->prepare();

        return $this->renderStars($rating, Text::sprintf('COM_MUCRONIXREVIEWS_N_OF_FIVE', $rating));
    }

    /**
     * Shows a page of the list, the reviews alone.
     *
     * @param   string   $context  The type of the object
     * @param   integer  $itemId   The id of the object
     * @param   integer  $offset   Where the page starts
     *
     * @return  string
     *
     * @since   0.3.0
     */
    public function renderReviews(string $context, int $itemId, int $offset = 0): string
    {
        $this->prepare();

        $settings = $this->reviews->getSettings();
        $format   = Text::_($settings->dateFormat());
        $html     = '';

        foreach ($this->reviews->getReviews($context, $itemId, ['offset' => $offset]) as $review) {
            $html .= $this->layout(
                'review',
                [
                    'review' => $review,
                    'stars'  => $this->renderStars((int) $review->rating, Text::sprintf('COM_MUCRONIXREVIEWS_N_OF_FIVE', (int) $review->rating)),
                    'date'   => $settings->showDate() ? HTMLHelper::_('date', $review->created, $format) : '',
                    'time'   => HTMLHelper::_('date', $review->created, 'c'),
                ]
            );
        }

        return $html;
    }

    /**
     * Returns where the page after another one starts.
     *
     * @param   integer  $offset   Where the other page starts
     * @param   integer  $ratings  The number of published reviews
     *
     * @return  integer|null  Null if there is none
     *
     * @since   0.3.0
     */
    public function next(int $offset, int $ratings): ?int
    {
        $next = $offset + $this->reviews->getSettings()->listLimit();

        return $next < $ratings ? $next : null;
    }

    /**
     * Returns the id of the block.
     *
     * @param   string   $context  The type of the object
     * @param   integer  $itemId   The id of the object
     *
     * @return  string
     *
     * @since   0.3.0
     */
    public function anchor(string $context, int $itemId): string
    {
        return 'mxr-' . str_replace(['.', '_'], '-', $context) . '-' . $itemId;
    }

    /**
     * Shows the list with its button.
     *
     * @param   string   $context  The type of the object
     * @param   integer  $itemId   The id of the object
     * @param   integer  $ratings  The number of published reviews
     *
     * @return  string
     *
     * @since   0.3.0
     */
    private function renderList(string $context, int $itemId, int $ratings): string
    {
        return $this->layout(
            'list',
            [
                'reviews' => $this->renderReviews($context, $itemId),
                'next'    => $this->next(0, $ratings),
                'url'     => Route::_('index.php?option=com_mucronixreviews&task=list.more&context=' . $context . '&item_id=' . $itemId, false),
                'object'  => $context . '.' . $itemId,
            ]
        );
    }

    /**
     * Shows the form, or the notice that stands instead of it.
     *
     * @param   string   $context   The type of the object
     * @param   integer  $itemId    The id of the object
     * @param   string   $return    The address of the page
     * @param   boolean  $showList  Whether the list is shown with the form
     *
     * @return  string  Empty if the object cannot be reviewed any more
     *
     * @since   0.3.0
     */
    private function renderForm(string $context, int $itemId, string $return, bool $showList): string
    {
        $user    = Factory::getApplication()->getIdentity() ?? new User();
        $handler = $this->contexts->getHandler($context);

        if ($handler === null || !$handler->isAvailable($itemId, $user)) {
            // Who may not see it as a guest may be allowed to after logging in, but then would not be here
            return '';
        }

        if ($user->guest) {
            return $this->notice($this->guestText($return));
        }

        if (!$this->reviews->isAllowed($user)) {
            return $this->notice(htmlspecialchars(Text::_('COM_MUCRONIXREVIEWS_ERROR_GROUP'), ENT_QUOTES, 'UTF-8'));
        }

        $settings = $this->reviews->getSettings();
        $existing = $this->reviews->getUserReview($context, $itemId, (int) $user->id);

        if ($existing && !$settings->allowEdit()) {
            return $this->notice(htmlspecialchars(Text::_('COM_MUCRONIXREVIEWS_ERROR_EDIT'), ENT_QUOTES, 'UTF-8'));
        }

        $kept = $this->takeKept($context, $itemId);

        return $this->layout(
            'form',
            [
                'id'        => $this->anchor($context, $itemId),
                'action'    => Route::_('index.php?option=com_mucronixreviews&task=review.save', false),
                'return'    => base64_encode($return),
                'context'   => $context,
                'itemId'    => $itemId,
                'isEdit'    => $existing !== null,
                'unshown'   => $existing === null || $existing->state === ModerationRules::PUBLISHED ? '' : ($existing->state === ModerationRules::PENDING ? 'pending' : 'hidden'),
                'isOpen'    => $kept !== null,
                'showList'  => $showList,
                'rating'    => (int) ($kept['rating'] ?? $existing->rating ?? 0),
                'text'      => (string) ($kept['text'] ?? $existing->text ?? ''),
                'textMode'  => $settings->textMode(),
                'textMin'   => $settings->textMin(),
                'textMax'   => $settings->textMax(),
            ]
        );
    }

    /**
     * The text for a guest, with the links to the standard pages of Joomla.
     *
     * @param   string  $return  The address of the page
     *
     * @return  string
     *
     * @since   0.3.0
     */
    private function guestText(string $return): string
    {
        $link = static fn (string $url, string $key): string => '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars(Text::_($key), ENT_QUOTES, 'UTF-8') . '</a>';

        $login = $link(Route::_('index.php?option=com_users&view=login&return=' . urlencode(base64_encode($return)), false), 'COM_MUCRONIXREVIEWS_GUEST_LOGIN');

        if (!ComponentHelper::getParams('com_users')->get('allowUserRegistration')) {
            return Text::sprintf('COM_MUCRONIXREVIEWS_GUEST', $login);
        }

        return Text::sprintf(
            'COM_MUCRONIXREVIEWS_GUEST_OR_REGISTER',
            $login,
            $link(Route::_('index.php?option=com_users&view=registration', false), 'COM_MUCRONIXREVIEWS_GUEST_REGISTER')
        );
    }

    /**
     * Shows a notice.
     *
     * @param   string  $html  The text, with its links
     *
     * @return  string
     *
     * @since   0.3.0
     */
    private function notice(string $html): string
    {
        return $this->layout('notice', ['html' => $html]);
    }

    /**
     * Takes what was typed in a form that was refused without JavaScript. It is shown once.
     *
     * @param   string   $context  The type of the object
     * @param   integer  $itemId   The id of the object
     *
     * @return  array|null
     *
     * @since   0.3.0
     */
    private function takeKept(string $context, int $itemId): ?array
    {
        $app  = Factory::getApplication();
        $key  = 'com_mucronixreviews.form.' . $context . '.' . $itemId;
        $kept = $app->getUserState($key);

        if (!\is_array($kept)) {
            return null;
        }

        $app->setUserState($key, null);

        return $kept;
    }

    /**
     * Loads the language and the assets.
     *
     * @return  void
     *
     * @since   0.3.0
     */
    private function prepare(): void
    {
        $app = Factory::getApplication();

        $app->getLanguage()->load('com_mucronixreviews', JPATH_SITE);

        $document = $app->getDocument();

        if ($document instanceof HtmlDocument) {
            $assets = $document->getWebAssetManager();

            $assets->getRegistry()->addExtensionRegistryFile('com_mucronixreviews');
            $assets->useStyle('com_mucronixreviews.reviews')->useScript('com_mucronixreviews.reviews');
        }
    }

    /**
     * Returns the classes for the buttons, the fields and the messages: those of UIkit on a template of YOOtheme Pro,
     * unless the options say the own look only.
     *
     * @return  string[]  kit, button, submit, textarea, notice, success, error; empty for the own look
     *
     * @since   0.5.0
     */
    public function ui(): array
    {
        if ($this->ui !== null) {
            return $this->ui;
        }

        $this->ui = array_fill_keys(array_keys(self::UIKIT), '');

        if ($this->reviews->getSettings()->controlStyle() === 'auto') {
            $app      = Factory::getApplication();
            $template = $app->isClient('site') ? $app->getTemplate(true) : null;

            if (\is_object($template) && ($template->template === 'yootheme' || ($template->parent ?? '') === 'yootheme')) {
                $this->ui = self::UIKIT;
            }
        }

        return $this->ui;
    }

    /**
     * Renders a layout of the component, or the one a template has instead of it.
     *
     * @param   string  $name  The name
     * @param   array   $data  The data
     *
     * @return  string
     *
     * @since   0.3.0
     */
    private function layout(string $name, array $data): string
    {
        return (new FileLayout($name, null, ['component' => 'com_mucronixreviews', 'client' => 'site']))->render($data + ['ui' => $this->ui()]);
    }
}
