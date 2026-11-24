<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  plg_content_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Mucronix\Plugin\Content\MucronixReviews\Extension;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Event\Content\AfterDisplayEvent;
use Joomla\CMS\Event\Content\BeforeDisplayEvent;
use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\Event\Model\AfterDeleteEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;
use Mucronix\Component\MucronixReviews\Administrator\Event\AfterChangeEvent;
use Mucronix\Component\MucronixReviews\Administrator\Event\RegisterContextsEvent;
use Mucronix\Component\MucronixReviews\Administrator\Extension\MucronixReviewsComponent;
use Mucronix\Component\MucronixReviews\Administrator\Service\Settings;
use Mucronix\Plugin\Content\MucronixReviews\Context\ArticleContext;
use Mucronix\Plugin\Content\MucronixReviews\Text\Shortcodes;

\defined('_JEXEC') or die;

/**
 * Reviews of the articles of Joomla: the block on the page of an article, the shortcode and the deletion of an article.
 *
 * @since  0.3.0
 */
final class MucronixReviews extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    /**
     * The groups of the cache that may hold reviews of articles
     *
     * @var    string[]
     * @since  0.3.0
     */
    private const CACHES = ['com_content', 'mod_custom', 'mod_articles', 'mod_articles_news'];

    /**
     * Whether the text of the article on the page holds a shortcode for it
     *
     * @var    boolean
     * @since  0.3.0
     */
    private bool $hasShortcode = false;

    /**
     * Returns the events this plugin listens to.
     *
     * @return  array
     *
     * @since   0.3.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onMucronixReviewsRegisterContexts' => 'registerContexts',
            'onMucronixReviewsAfterChange'      => 'cleanCaches',
            'onContentPrepare'                  => 'onContentPrepare',
            'onContentBeforeDisplay'            => 'onContentBeforeDisplay',
            'onContentAfterDisplay'             => 'onContentAfterDisplay',
            'onContentAfterDelete'              => 'onContentAfterDelete',
        ];
    }

    /**
     * Registers the articles as a type of objects.
     *
     * @param   RegisterContextsEvent  $event  The event
     *
     * @return  void
     *
     * @since   0.3.0
     */
    public function registerContexts(RegisterContextsEvent $event): void
    {
        $event->addHandler(new ArticleContext($this->getDatabase()));
    }

    /**
     * Cleans the caches that may hold reviews of articles. Not the cache of whole pages: Joomla does not clean it either
     * when an article changes.
     *
     * @param   AfterChangeEvent  $event  The event
     *
     * @return  void
     *
     * @since   0.3.0
     */
    public function cleanCaches(AfterChangeEvent $event): void
    {
        if (!\in_array(ArticleContext::NAME, $event->getContexts(), true)) {
            return;
        }

        $factory = Factory::getContainer()->get(CacheControllerFactoryInterface::class);

        foreach (self::CACHES as $group) {
            $factory->createCacheController('callback', ['defaultgroup' => $group])->clean();
        }
    }

    /**
     * Replaces the shortcodes.
     *
     * @param   ContentPrepareEvent  $event  The event
     *
     * @return  void
     *
     * @since   0.3.0
     */
    public function onContentPrepare(ContentPrepareEvent $event): void
    {
        $item = $event->getItem();

        if (!isset($item->text) || !\is_string($item->text) || stripos($item->text, '{mucronixreviews') === false) {
            return;
        }

        $context   = $event->getContext();
        $component = $this->enabledComponent();

        // An index, a feed, an answer of the API: there is nobody to see the stars
        if ($component === null || $context === 'com_finder.indexer' || !$this->isPage()) {
            $item->text = Shortcodes::replace($item->text, static fn (): array => ['', false]);

            return;
        }

        // Only an article has a category of its own. A list and a module pass the context of an article, too.
        $ownId  = str_starts_with($context, 'com_content.') && isset($item->id, $item->catid) ? (int) $item->id : 0;
        $pageId = $this->pageArticle();
        $isOwn  = $ownId > 0 && $ownId === $pageId && $context === ArticleContext::NAME;
        $isList = $ownId > 0 && !$isOwn;

        $item->text = Shortcodes::replace(
            $item->text,
            function (string $arguments) use ($component, $ownId, $pageId, $isOwn, $isList): array {
                $itemId    = preg_match('~id=(\d+)~', $arguments, $id) ? (int) $id[1] : ($ownId ?: $pageId);
                $isSummary = $isList || str_contains($arguments, 'summary');

                return [$this->shortcode($component, $itemId, $isOwn, $isSummary), !$isSummary];
            }
        );
    }

    /**
     * Shows the block before the text of an article.
     *
     * @param   BeforeDisplayEvent  $event  The event
     *
     * @return  void
     *
     * @since   0.3.0
     */
    public function onContentBeforeDisplay(BeforeDisplayEvent $event): void
    {
        if ($this->settings()?->articlePosition() === 'before') {
            $event->addResult($this->automatic($event->getContext(), $event->getItem()));
        }
    }

    /**
     * Shows the block after the text of an article.
     *
     * @param   AfterDisplayEvent  $event  The event
     *
     * @return  void
     *
     * @since   0.3.0
     */
    public function onContentAfterDisplay(AfterDisplayEvent $event): void
    {
        if ($this->settings()?->articlePosition() === 'after') {
            $event->addResult($this->automatic($event->getContext(), $event->getItem()));
        }
    }

    /**
     * Deletes the reviews of a deleted article.
     *
     * @param   AfterDeleteEvent  $event  The event
     *
     * @return  void
     *
     * @since   0.3.0
     */
    public function onContentAfterDelete(AfterDeleteEvent $event): void
    {
        $item = $event->getItem();

        if ($event->getContext() !== ArticleContext::NAME || empty($item->id)) {
            return;
        }

        $this->component()?->getReviewService()->deleteByItem(ArticleContext::NAME, (int) $item->id);
    }

    /**
     * Shows what a shortcode stands for.
     *
     * @param   MucronixReviewsComponent  $component  The component
     * @param   integer                   $itemId     The id of the article
     * @param   boolean                   $isOwn      Whether the shortcode is in the text of the article of the page
     * @param   boolean                   $isSummary  Whether it stands for the summary
     *
     * @return  string  Empty if the visitor may not see the article
     *
     * @since   0.3.0
     */
    private function shortcode(MucronixReviewsComponent $component, int $itemId, bool $isOwn, bool $isSummary): string
    {
        if ($itemId < 1) {
            return '';
        }

        $isPageArticle = $itemId === $this->pageArticle();

        if ($isOwn && $isPageArticle) {
            $this->hasShortcode = true;
        }

        $registry = $component->getContextRegistry();
        $user     = $this->getApplication()->getIdentity();

        // Of another article not even the average is told to somebody who may not see it
        $isVisible = $isPageArticle
            ? $registry->isViewable(ArticleContext::NAME, $itemId, $user)
            : (bool) $registry->getHandler(ArticleContext::NAME)?->isAvailable($itemId, $user);

        if (!$isVisible) {
            return '';
        }

        return $isSummary
            ? $component->getBlockRenderer()->renderSummary(ArticleContext::NAME, $itemId)
            : $this->block($component, $itemId, ['return' => $this->returnUrl($component, $itemId)]);
    }

    /**
     * Shows the block on the page of an article of the chosen categories.
     *
     * @param   string  $context  The context of the event
     * @param   mixed   $item     The article
     *
     * @return  string
     *
     * @since   0.3.0
     */
    private function automatic(string $context, mixed $item): string
    {
        if (
            $context !== ArticleContext::NAME
            || $this->hasShortcode
            || !isset($item->id, $item->catid)
            || (int) $item->id !== $this->pageArticle()
            || !\in_array((int) $item->catid, $this->settings()?->articleCategories() ?? [], true)
            || !$this->isPage()
        ) {
            return '';
        }

        $itemId    = (int) $item->id;
        $component = $this->enabledComponent();

        if ($component === null || !$component->getContextRegistry()->isViewable(ArticleContext::NAME, $itemId, $this->getApplication()->getIdentity())) {
            return '';
        }

        $show = $component->getReviewService()->getSettings()->articleShow();

        return $this->block(
            $component,
            $itemId,
            ['form' => $show === 'full', 'list' => $show !== 'summary', 'return' => $this->returnUrl($component, $itemId)]
        );
    }

    /**
     * Shows the block of an article. The block of one article is shown once on a page: the output of the component sees
     * to it, for the element of YOOtheme Pro, too.
     *
     * @param   MucronixReviewsComponent  $component  The component
     * @param   integer                   $itemId     The id of the article
     * @param   array                     $options    The options of the block
     *
     * @return  string
     *
     * @since   0.3.0
     */
    private function block(MucronixReviewsComponent $component, int $itemId, array $options): string
    {
        return $component->getBlockRenderer()->renderBlock(ArticleContext::NAME, $itemId, $options);
    }

    /**
     * Where to come back to from the login and the form. On the page of the article this is its own address: the page is
     * cached for guests and does not depend on foreign parameters.
     *
     * @param   MucronixReviewsComponent  $component  The component
     * @param   integer                   $itemId     The id of the article
     *
     * @return  string
     *
     * @since   0.3.0
     */
    private function returnUrl(MucronixReviewsComponent $component, int $itemId): string
    {
        $own = $itemId === $this->pageArticle() ? (string) $component->getContextRegistry()->getHandler(ArticleContext::NAME)?->getUrl($itemId) : '';

        return $own !== '' ? $own : Uri::getInstance()->toString();
    }

    /**
     * Returns the id of the article whose page is shown.
     *
     * @return  integer  0 on any other page
     *
     * @since   0.3.0
     */
    private function pageArticle(): int
    {
        $input = $this->getApplication()->getInput();

        return $input->getCmd('option') === 'com_content' && $input->getCmd('view') === 'article' ? $input->getInt('id') : 0;
    }

    /**
     * Whether a page of the site is made, not a feed or data.
     *
     * @return  boolean
     *
     * @since   0.3.0
     */
    private function isPage(): bool
    {
        $app = $this->getApplication();

        return $app->isClient('site') && $app->getDocument() instanceof HtmlDocument;
    }

    /**
     * Returns the component, for the output: its addresses do not answer while it is disabled.
     *
     * @return  MucronixReviewsComponent|null  Null if it is not there or disabled
     *
     * @since   0.3.0
     */
    private function enabledComponent(): ?MucronixReviewsComponent
    {
        return ComponentHelper::isEnabled('com_mucronixreviews') ? $this->component() : null;
    }

    /**
     * Returns the options of the component, where the block of the articles is set up.
     *
     * @return  Settings|null  Null if the component is not there or disabled
     *
     * @since   0.3.1
     */
    private function settings(): ?Settings
    {
        return $this->enabledComponent()?->getReviewService()->getSettings();
    }

    /**
     * Returns the component.
     *
     * @return  MucronixReviewsComponent|null  Null if it is not there
     *
     * @since   0.3.0
     */
    private function component(): ?MucronixReviewsComponent
    {
        $component = $this->getApplication()->bootComponent('com_mucronixreviews');

        return $component instanceof MucronixReviewsComponent ? $component : null;
    }
}
