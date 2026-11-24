<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  com_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Mucronix\Component\MucronixReviews\Administrator\Service;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The stored statistics of the objects.
 *
 * @since  0.1.0
 */
class StatsTable
{
    /**
     * The columns that hold whole numbers
     *
     * @var    string[]
     * @since  0.1.0
     */
    private const COUNTS = ['rating_count', 'text_count', 'rating_sum', 'rating_1', 'rating_2', 'rating_3', 'rating_4', 'rating_5'];

    /**
     * Constructor
     *
     * @param   DatabaseInterface  $db  The database driver
     *
     * @since   0.1.0
     */
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * Reads the statistics of an object.
     *
     * @param   string   $context  The type of the object
     * @param   integer  $itemId   The id of the object
     *
     * @return  object  With zeros if there are no reviews. The average is the exact one, not rounded for display.
     *
     * @since   0.1.0
     */
    public function read(string $context, int $itemId): object
    {
        $query = $this->db->createQuery()
            ->select($this->db->quoteName([...self::COUNTS, 'rating_average']))
            ->from($this->db->quoteName('#__mucronixreviews_stats'))
            ->where($this->db->quoteName('context') . ' = :context')
            ->where($this->db->quoteName('item_id') . ' = :itemId')
            ->bind(':context', $context)
            ->bind(':itemId', $itemId, ParameterType::INTEGER);

        $row   = $this->db->setQuery($query)->loadObject();
        $stats = (object) ['context' => $context, 'item_id' => $itemId];

        foreach (self::COUNTS as $column) {
            $stats->$column = (int) ($row->$column ?? 0);
        }

        $stats->rating_average = (float) ($row->rating_average ?? 0);

        return $stats;
    }

    /**
     * Locks the statistics of an object until the end of the transaction, so that reviews of one object are written one
     * after another and each recalculation sees those before it. Must come before the first reading of the transaction.
     * The query builder has no ON DUPLICATE KEY for MySQL.
     *
     * @param   string   $context  The type of the object
     * @param   integer  $itemId   The id of the object
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function lock(string $context, int $itemId): void
    {
        $query = $this->db->createQuery()
            ->setQuery(
                'INSERT INTO ' . $this->db->quoteName('#__mucronixreviews_stats')
                . ' (' . $this->db->quoteName('context') . ', ' . $this->db->quoteName('item_id') . ') VALUES (:context, :itemId)'
                . ' ON DUPLICATE KEY UPDATE ' . $this->db->quoteName('item_id') . ' = ' . $this->db->quoteName('item_id')
            )
            ->bind(':context', $context)
            ->bind(':itemId', $itemId, ParameterType::INTEGER);

        $this->db->setQuery($query)->execute();
    }

    /**
     * Counts the published reviews of an object and stores the result. The row must be locked.
     *
     * @param   string   $context  The type of the object
     * @param   integer  $itemId   The id of the object
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function recalculate(string $context, int $itemId): void
    {
        $rating = $this->db->quoteName('rating');
        $text   = $this->db->quoteName('text');
        $state  = ModerationRules::PUBLISHED;

        $query = $this->db->createQuery()
            ->select(
                [
                    'COUNT(*) AS ' . $this->db->quoteName('rating_count'),
                    'COALESCE(SUM(CASE WHEN ' . $text . " <> '' THEN 1 ELSE 0 END), 0) AS " . $this->db->quoteName('text_count'),
                    'COALESCE(SUM(' . $rating . '), 0) AS ' . $this->db->quoteName('rating_sum'),
                ]
            )
            ->from($this->db->quoteName('#__mucronixreviews_reviews'))
            ->where($this->db->quoteName('context') . ' = :context')
            ->where($this->db->quoteName('item_id') . ' = :itemId')
            ->where($this->db->quoteName('state') . ' = :state')
            ->bind(':context', $context)
            ->bind(':itemId', $itemId, ParameterType::INTEGER)
            ->bind(':state', $state, ParameterType::INTEGER);

        for ($stars = 1; $stars <= 5; $stars++) {
            $query->select('COALESCE(SUM(CASE WHEN ' . $rating . ' = ' . $stars . ' THEN 1 ELSE 0 END), 0) AS ' . $this->db->quoteName('rating_' . $stars));
        }

        $values = array_map('intval', $this->db->setQuery($query)->loadAssoc());
        $count  = $values['rating_count'];

        $average  = number_format($count > 0 ? $values['rating_sum'] / $count : 0, 2, '.', '');
        $modified = Factory::getDate()->toSql();

        $update = $this->db->createQuery()
            ->update($this->db->quoteName('#__mucronixreviews_stats'))
            ->set($this->db->quoteName('rating_average') . ' = :average')
            ->set($this->db->quoteName('modified') . ' = :modified')
            ->where($this->db->quoteName('context') . ' = :context')
            ->where($this->db->quoteName('item_id') . ' = :itemId')
            ->bind(':average', $average)
            ->bind(':modified', $modified)
            ->bind(':context', $context)
            ->bind(':itemId', $itemId, ParameterType::INTEGER);

        foreach (self::COUNTS as $column) {
            $update->set($this->db->quoteName($column) . ' = :' . $column)
                ->bind(':' . $column, $values[$column], ParameterType::INTEGER);
        }

        $this->db->setQuery($update)->execute();
    }

    /**
     * Removes the statistics of an object.
     *
     * @param   string   $context  The type of the object
     * @param   integer  $itemId   The id of the object
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function delete(string $context, int $itemId): void
    {
        $query = $this->db->createQuery()
            ->delete($this->db->quoteName('#__mucronixreviews_stats'))
            ->where($this->db->quoteName('context') . ' = :context')
            ->where($this->db->quoteName('item_id') . ' = :itemId')
            ->bind(':context', $context)
            ->bind(':itemId', $itemId, ParameterType::INTEGER);

        $this->db->setQuery($query)->execute();
    }
}
