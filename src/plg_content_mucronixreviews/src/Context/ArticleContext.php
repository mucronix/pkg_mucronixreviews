<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  plg_content_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Mucronix\Plugin\Content\MucronixReviews\Context;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\Component\Content\Site\Helper\RouteHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Mucronix\Component\MucronixReviews\Administrator\Context\ContextHandlerInterface;
use Mucronix\Component\MucronixReviews\Administrator\Context\GraphSchemaContextInterface;
use Mucronix\Component\MucronixReviews\Administrator\Context\ViewableContextInterface;
use Mucronix\Component\MucronixReviews\Administrator\Context\YoothemeContextInterface;

\defined('_JEXEC') or die;

/**
 * The articles of Joomla as objects of reviews.
 *
 * @since  0.3.0
 */
final class ArticleContext implements ContextHandlerInterface, ViewableContextInterface, GraphSchemaContextInterface, YoothemeContextInterface
{
    /**
     * The name of the type
     *
     * @var    string
     * @since  0.3.0
     */
    public const NAME = 'com_content.article';

    /**
     * The articles that were read, by id
     *
     * @var    array
     * @since  0.3.0
     */
    private array $articles = [];

    /**
     * Constructor
     *
     * @param   DatabaseInterface  $db  The database driver
     *
     * @since   0.3.0
     */
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * Returns the name of the type.
     *
     * @return  string
     *
     * @since   0.3.0
     */
    public function getContext(): string
    {
        return self::NAME;
    }

    /**
     * Returns the label of the type.
     *
     * @return  string
     *
     * @since   0.3.0
     */
    public function getLabel(): string
    {
        Factory::getApplication()->getLanguage()->load('plg_content_mucronixreviews', JPATH_ADMINISTRATOR);

        return Text::_('PLG_CONTENT_MUCRONIXREVIEWS_CONTEXT_ARTICLES');
    }

    /**
     * Whether the article is published and the user has access to it.
     *
     * @param   integer  $itemId  The id of the article
     * @param   User     $user    The user
     *
     * @return  boolean
     *
     * @since   0.3.0
     */
    public function isAvailable(int $itemId, User $user): bool
    {
        $article = $this->article($itemId);

        return $article !== null
            && (int) $article->state === 1
            && (int) $article->category_state === 1
            && (int) $article->closed_parents === 0
            && $this->isOpen($article, $user);
    }

    /**
     * Whether the user may see the article: as isAvailable(), and archived ones, too.
     *
     * @param   integer  $itemId  The id of the article
     * @param   User     $user    The user
     *
     * @return  boolean
     *
     * @since   0.3.0
     */
    public function isViewable(int $itemId, User $user): bool
    {
        $article = $this->article($itemId);

        return $article !== null
            && \in_array((int) $article->state, [1, 2], true)
            && (int) $article->category_state > 0
            && (int) $article->hidden_parents === 0
            && $this->isOpen($article, $user);
    }

    /**
     * Returns the title of the article.
     *
     * @param   integer  $itemId  The id of the article
     *
     * @return  string
     *
     * @since   0.3.0
     */
    public function getTitle(int $itemId): string
    {
        return (string) ($this->article($itemId)->title ?? '');
    }

    /**
     * Returns the address of the article on the site.
     *
     * @param   integer  $itemId  The id of the article
     *
     * @return  string
     *
     * @since   0.3.0
     */
    public function getUrl(int $itemId): string
    {
        $article = $this->article($itemId);

        if ($article === null) {
            return '';
        }

        return Route::link(
            'site',
            RouteHelper::getArticleRoute($itemId . ':' . $article->alias, (int) $article->catid, $article->language),
            false,
            Route::TLS_IGNORE,
            true
        );
    }

    /**
     * Returns the Schema.org type of the articles.
     *
     * @return  string|null
     *
     * @since   0.3.0
     */
    public function getSchemaType(): ?string
    {
        return 'Article';
    }

    /**
     * Returns the article of a page from the context Joomla passes with its graph of Schema.org.
     *
     * @param   string  $schemaContext  com_content.article.5 on the page of an article
     *
     * @return  integer
     *
     * @since   0.4.0
     */
    public function getSchemaItemId(string $schemaContext): int
    {
        return preg_match('~^com_content\.article\.([1-9]\d{0,9})$~D', $schemaContext, $match) ? (int) $match[1] : 0;
    }

    /**
     * Returns the @id the node of the article has in the graph of Joomla: the plugins "System - Schema.org" and
     * "Content - Joomla" build it so.
     *
     * @param   integer  $itemId  The id of the article
     *
     * @return  string
     *
     * @since   0.4.0
     */
    public function getSchemaNodeId(int $itemId): string
    {
        return Uri::root() . '#/schema/com_content/article/' . $itemId;
    }

    /**
     * Returns the types of YOOtheme Pro whose objects are articles.
     *
     * @return  string[]
     *
     * @since   0.5.0
     */
    public function getYoothemeTypes(): array
    {
        return ['Article'];
    }

    /**
     * Returns the id of an article YOOtheme Pro passes.
     *
     * @param   object  $item  The article
     *
     * @return  integer
     *
     * @since   0.5.0
     */
    public function getYoothemeItemId(object $item): int
    {
        return isset($item->id) && is_numeric($item->id) ? max(0, (int) $item->id) : 0;
    }

    /**
     * Returns the article of the page that is open.
     *
     * @return  integer  0 on any other page
     *
     * @since   0.5.0
     */
    public function getPageItemId(): int
    {
        $input = Factory::getApplication()->getInput();

        return $input->getCmd('option') === 'com_content' && $input->getCmd('view') === 'article' ? max(0, $input->getInt('id')) : 0;
    }

    /**
     * Whether the time of the article has come and not passed, and the access levels let the user in.
     *
     * @param   object  $article  The article
     * @param   User    $user     The user
     *
     * @return  boolean
     *
     * @since   0.3.0
     */
    private function isOpen(object $article, User $user): bool
    {
        $now    = Factory::getDate()->toSql();
        $levels = array_map('intval', $user->getAuthorisedViewLevels());

        return ($article->publish_up === null || $article->publish_up <= $now)
            && ($article->publish_down === null || $article->publish_down >= $now)
            && \in_array((int) $article->access, $levels, true)
            && \in_array((int) $article->category_access, $levels, true);
    }

    /**
     * Reads an article.
     *
     * @param   integer  $itemId  The id of the article
     *
     * @return  object|null
     *
     * @since   0.3.0
     */
    private function article(int $itemId): ?object
    {
        if (\array_key_exists($itemId, $this->articles)) {
            return $this->articles[$itemId];
        }

        $db = $this->db;

        // The parents that are not published, and those that cannot be seen at all
        $parents = static fn (string $condition): string => '(SELECT COUNT(*) FROM ' . $db->quoteName('#__categories', 'p')
            . ' WHERE ' . $db->quoteName('p.lft') . ' < ' . $db->quoteName('c.lft')
            . ' AND ' . $db->quoteName('p.rgt') . ' > ' . $db->quoteName('c.rgt')
            . ' AND ' . $db->quoteName('p.parent_id') . ' > 0 AND ' . $db->quoteName('p.published') . $condition . ')';

        $query = $db->createQuery()
            ->select($db->quoteName(['a.title', 'a.alias', 'a.catid', 'a.state', 'a.access', 'a.publish_up', 'a.publish_down', 'a.language']))
            ->select($db->quoteName('c.published', 'category_state'))
            ->select($db->quoteName('c.access', 'category_access'))
            ->select($parents(' <> 1') . ' AS ' . $db->quoteName('closed_parents'))
            ->select($parents(' < 1') . ' AS ' . $db->quoteName('hidden_parents'))
            ->from($db->quoteName('#__content', 'a'))
            ->join('INNER', $db->quoteName('#__categories', 'c'), $db->quoteName('c.id') . ' = ' . $db->quoteName('a.catid'))
            ->where($db->quoteName('a.id') . ' = :id')
            ->bind(':id', $itemId, ParameterType::INTEGER);

        return $this->articles[$itemId] = $db->setQuery($query)->loadObject() ?: null;
    }
}
