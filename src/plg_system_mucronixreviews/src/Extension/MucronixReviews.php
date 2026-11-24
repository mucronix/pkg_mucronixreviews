<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  plg_system_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Mucronix\Plugin\System\MucronixReviews\Extension;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Event\Menu\PreprocessMenuItemsEvent;
use Joomla\CMS\Event\Plugin\System\Schemaorg\BeforeCompileHeadEvent;
use Joomla\CMS\Event\User\AfterDeleteEvent;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\Priority;
use Joomla\Event\SubscriberInterface;
use Mucronix\Component\MucronixReviews\Administrator\Extension\MucronixReviewsComponent;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The system plugin of Mucronix Reviews: passes the deletion of a user on to the component, shows in the menu of the
 * administrator how many reviews wait for a moderator, takes the shortcode out of feeds, puts the rating into the
 * graph of Schema.org that Joomla builds and gives YOOtheme Pro the fields, the list of reviews and the element.
 *
 * @since  0.1.0
 */
final class MucronixReviews extends CMSPlugin implements SubscriberInterface
{
    /**
     * Returns the events this plugin listens to.
     *
     * @return  array
     *
     * @since   0.1.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            // After YOOtheme Pro, which starts there with a high priority
            'onAfterInitialise'         => 'onAfterInitialise',
            'onUserAfterDelete'         => 'onUserAfterDelete',
            'onPreprocessMenuItems'     => 'onPreprocessMenuItems',
            'onAfterRender'             => 'onAfterRender',
            // After the plugins of Joomla that add the node of the object
            'onSchemaBeforeCompileHead' => ['onSchemaBeforeCompileHead', Priority::LOW],
        ];
    }

    /**
     * Loads the module for YOOtheme Pro, if YOOtheme Pro has started on this request and the integration is on.
     * Without YOOtheme Pro nothing is loaded.
     *
     * @return  void
     *
     * @since   0.5.0
     */
    public function onAfterInitialise(): void
    {
        if (!$this->params->get('yootheme', 1) || !class_exists(\YOOtheme\Application::class, false)) {
            return;
        }

        // The labels of the fields and of the element
        $this->loadLanguage();

        \YOOtheme\Application::getInstance()->load(JPATH_PLUGINS . '/system/mucronixreviews/yootheme/bootstrap.php');
    }

    /**
     * Puts the rating of the object of the page into the graph of Schema.org of Joomla. The page is made by now: the
     * head comes last, after the component and the modules.
     *
     * @param   BeforeCompileHeadEvent  $event  The event
     *
     * @return  void
     *
     * @since   0.4.0
     */
    public function onSchemaBeforeCompileHead(BeforeCompileHeadEvent $event): void
    {
        $app      = $this->getApplication();
        $document = $app->getDocument();

        if (!$app->isClient('site') || !$document instanceof HtmlDocument || !ComponentHelper::isEnabled('com_mucronixreviews')) {
            return;
        }

        $component = $app->bootComponent('com_mucronixreviews');

        if (!$component instanceof MucronixReviewsComponent) {
            return;
        }

        $html = '';

        $buffer = $document->getBuffer();

        array_walk_recursive($buffer, static function ($part) use (&$html): void {
            $html .= \is_string($part) ? $part : '';
        });

        $component->getSchemaBuilder()->addToGraph($event->getSchema(), $event->getContext(), $html);
    }

    /**
     * Deals with the reviews of a deleted user.
     *
     * @param   AfterDeleteEvent  $event  The event
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function onUserAfterDelete(AfterDeleteEvent $event): void
    {
        // User::delete() fires the event for a failed deletion, too
        if (!$event->getDeletingResult()) {
            return;
        }

        $userId = (int) ($event->getUser()['id'] ?? 0);

        try {
            $component = $this->getApplication()->bootComponent('com_mucronixreviews');

            // Not there if the component was removed without this plugin
            if ($component instanceof MucronixReviewsComponent) {
                $component->getReviewService()->handleUserDelete($userId);
            }
        } catch (\Throwable $e) {
            // The user is gone already: the other plugins must get the event, the administrator must get to know
            Log::add('User ' . $userId . ': ' . $e->getMessage(), Log::ERROR, 'com_mucronixreviews');

            $this->loadLanguage();
            $this->getApplication()->enqueueMessage(Text::sprintf('PLG_SYSTEM_MUCRONIXREVIEWS_USER_DELETE_FAILED', $userId), 'error');
        }
    }

    /**
     * Takes the shortcode out of a feed: Joomla does not prepare the content of feeds, so the content plugin is not asked.
     *
     * @return  void
     *
     * @since   0.3.0
     */
    public function onAfterRender(): void
    {
        $app = $this->getApplication();

        if (!$app->isClient('site') || $app->getDocument()->getType() !== 'feed') {
            return;
        }

        $body = (string) $app->getBody();

        if (stripos($body, '{mucronixreviews') !== false) {
            $app->setBody(preg_replace('~\{mucronixreviews(?:\s+(?:summary|id=\d{1,10}))*\s*\}~i', '', $body) ?? $body);
        }
    }

    /**
     * Puts the number of reviews that wait for a moderator next to their item of the menu.
     *
     * @param   PreprocessMenuItemsEvent  $event  The event
     *
     * @return  void
     *
     * @since   0.2.0
     */
    public function onPreprocessMenuItems(PreprocessMenuItemsEvent $event): void
    {
        $app = $this->getApplication();

        if (!$app->isClient('administrator') || $event->getArgument('context') !== 'com_menus.administrator.module') {
            return;
        }

        foreach ($event->getArgument('subject') as $item) {
            $query = (new Uri(str_replace('&amp;', '&', (string) ($item->link ?? ''))))->getQuery(true);

            if (($query['option'] ?? '') !== 'com_mucronixreviews' || ($query['filter']['state'] ?? '') !== '0') {
                continue;
            }

            if (!$app->getIdentity()->authorise('core.manage', 'com_mucronixreviews')) {
                return;
            }

            try {
                $component = $app->bootComponent('com_mucronixreviews');
                $pending   = $component instanceof MucronixReviewsComponent ? $component->getReviewService()->countPending() : 0;
            } catch (\Throwable $e) {
                // A menu without a number is better than an administrator without a menu
                Log::add('Menu: ' . $e->getMessage(), Log::ERROR, 'com_mucronixreviews');

                return;
            }

            if ($pending > 0) {
                // How mod_menu takes a badge
                $item->icon = 'image:' . $pending;
            }
        }
    }
}
