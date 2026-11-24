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
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;
use Mucronix\Component\MucronixReviews\Administrator\Service\ModerationRules;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The list of the reviews.
 *
 * @since  0.2.0
 */
class ReviewsModel extends ListModel
{
    /**
     * Constructor
     *
     * @param   array                 $config   An optional associative array of configuration settings
     * @param   ?MVCFactoryInterface  $factory  The factory
     *
     * @since   0.2.0
     */
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'a.id',
                'state', 'a.state',
                'rating', 'a.rating',
                'context', 'a.context',
                'created', 'a.created',
                'author',
                'user_id',
                'with_text',
            ];
        }

        parent::__construct($config, $factory);
    }

    /**
     * Returns the reviews of the page, with what their objects are.
     *
     * @return  object[]|false
     *
     * @since   0.2.0
     */
    public function getItems()
    {
        $items = parent::getItems();

        if ($items) {
            $registry = Factory::getApplication()->bootComponent('com_mucronixreviews')->getContextRegistry();

            foreach ($items as $item) {
                $item->object ??= $registry->describe($item->context, (int) $item->item_id);
            }
        }

        return $items;
    }

    /**
     * Sets the default order: the newest first.
     *
     * @param   string  $ordering   The column
     * @param   string  $direction  The direction
     *
     * @return  void
     *
     * @since   0.2.0
     */
    protected function populateState($ordering = 'a.created', $direction = 'desc')
    {
        parent::populateState($ordering, $direction);
    }

    /**
     * Returns an id for the cache of the list.
     *
     * @param   string  $id  A prefix
     *
     * @return  string
     *
     * @since   0.2.0
     */
    protected function getStoreId($id = '')
    {
        foreach (['search', 'state', 'context', 'rating', 'user_id', 'with_text'] as $filter) {
            $id .= ':' . $this->getState('filter.' . $filter);
        }

        return parent::getStoreId($id);
    }

    /**
     * Builds the query of the list.
     *
     * @return  QueryInterface
     *
     * @since   0.2.0
     */
    protected function getListQuery()
    {
        $db    = $this->getDatabase();
        $query = $db->createQuery()
            ->select($db->quoteName(['a.id', 'a.context', 'a.item_id', 'a.user_id', 'a.rating', 'a.state', 'a.created']))
            ->select('SUBSTRING(' . $db->quoteName('a.text') . ', 1, 200) AS ' . $db->quoteName('excerpt'))
            ->select('COALESCE(' . $db->quoteName('u.username') . ', ' . $db->quoteName('a.username') . ') AS ' . $db->quoteName('author'))
            ->select($db->quoteName('u.id', 'account_id'))
            ->from($db->quoteName('#__mucronixreviews_reviews', 'a'))
            ->join('LEFT', $db->quoteName('#__users', 'u'), $db->quoteName('u.id') . ' = ' . $db->quoteName('a.user_id'));

        $state = (string) $this->getState('filter.state');

        if (is_numeric($state)) {
            $state = (int) $state;
            $query->where($db->quoteName('a.state') . ' = :state')
                ->bind(':state', $state, ParameterType::INTEGER);
        } elseif ($state === '') {
            // Spam and the trash are shown when they are asked for
            $query->whereIn($db->quoteName('a.state'), [ModerationRules::PUBLISHED, ModerationRules::PENDING, ModerationRules::REJECTED]);
        }

        if (($context = (string) $this->getState('filter.context')) !== '') {
            $query->where($db->quoteName('a.context') . ' = :context')
                ->bind(':context', $context);
        }

        foreach (['rating', 'user_id'] as $filter) {
            if ($$filter = (int) $this->getState('filter.' . $filter)) {
                $query->where($db->quoteName('a.' . $filter) . ' = :' . $filter)
                    ->bind(':' . $filter, $$filter, ParameterType::INTEGER);
            }
        }

        if ($this->getState('filter.with_text')) {
            $query->where($db->quoteName('a.text') . " <> ''");
        }

        $this->addSearch($query, trim((string) $this->getState('filter.search')));

        $direction = strtoupper((string) $this->getState('list.direction')) === 'ASC' ? ' ASC' : ' DESC';
        $ordering  = (string) $this->getState('list.ordering', 'a.created');

        $query->order($db->quoteName($ordering) . $direction);

        if ($ordering === 'a.context') {
            $query->order($db->quoteName('a.item_id') . $direction);
        }

        return $query->order($db->quoteName('a.id') . $direction);
    }

    /**
     * Adds the search: the text, the present and the stored username, or the id after "id:".
     *
     * @param   QueryInterface  $query   The query
     * @param   string          $search  What is searched for
     *
     * @return  void
     *
     * @since   0.2.0
     */
    private function addSearch(QueryInterface $query, string $search): void
    {
        $db = $this->getDatabase();

        if (stripos($search, 'id:') === 0) {
            $id = (int) substr($search, 3);
            $query->where($db->quoteName('a.id') . ' = :id')
                ->bind(':id', $id, ParameterType::INTEGER);
        } elseif ($search !== '') {
            $like       = '%' . str_replace(' ', '%', addcslashes($search, '\\%_')) . '%';
            $conditions = [];

            foreach (['a.text', 'a.username', 'u.username'] as $i => $column) {
                $conditions[] = $db->quoteName($column) . ' LIKE :search' . $i;
                $query->bind(':search' . $i, $like);
            }

            $query->where('(' . implode(' OR ', $conditions) . ')');
        }
    }
}
