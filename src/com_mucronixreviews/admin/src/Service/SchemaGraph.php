<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  com_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Mucronix\Component\MucronixReviews\Administrator\Service;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The rules for the graph of Schema.org that Joomla builds: which node may take a rating, whether one is there already,
 * and whether the visitor sees the rating on the page. Needs nothing of Joomla.
 *
 * @since  0.4.0
 */
final class SchemaGraph
{
    /**
     * The types whose nodes may get a rating: CreativeWork and its kinds, and the other types Schema.org gives an
     * aggregateRating. Person and JobPosting have none.
     *
     * @var    string[]
     * @since  0.4.0
     */
    public const RATED_TYPES = [
        'CreativeWork', 'Article', 'BlogPosting', 'NewsArticle', 'TechArticle', 'Book', 'Recipe', 'Course', 'Movie',
        'SoftwareApplication', 'WebApplication', 'MobileApplication', 'VideoGame', 'Game', 'HowTo', 'Event',
        'Organization', 'LocalBusiness', 'Place', 'Product', 'Service', 'Offer', 'Brand',
    ];

    /**
     * Puts the rating into the node of an object.
     *
     * @param   array   $graph       The entries of the graph
     * @param   string  $nodeId      The @id of the node
     * @param   array   $properties  aggregateRating and, if there are, review
     *
     * @return  array  The graph as it was if there is no such node, its type takes no rating, or a rating of it is
     *                 there already: that one stays
     *
     * @since   0.4.0
     */
    public static function attach(array $graph, string $nodeId, array $properties): array
    {
        $node = null;

        foreach ($graph as $key => $entry) {
            if (!\is_array($entry)) {
                continue;
            }

            if (($entry['@id'] ?? null) === $nodeId) {
                if (isset($entry['aggregateRating'])) {
                    return $graph;
                }

                $node ??= $key;
            }

            // The way the vote plugin of Joomla adds its rating: an entry of its own that points at the node
            if (\is_array($entry['@isPartOf'] ?? null) && ($entry['@isPartOf']['@id'] ?? null) === $nodeId && isset($entry['@isPartOf']['aggregateRating'])) {
                return $graph;
            }
        }

        if ($node === null || !self::takesRating($graph[$node]['@type'] ?? null)) {
            return $graph;
        }

        $graph[$node] += $properties;

        return $graph;
    }

    /**
     * Whether a node of this type may get a rating.
     *
     * @param   mixed  $type  A type or several
     *
     * @return  boolean
     *
     * @since   0.4.0
     */
    public static function takesRating(mixed $type): bool
    {
        foreach ((array) $type as $one) {
            if (\is_string($one) && \in_array($one, self::RATED_TYPES, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tells what of an object the page shows, by the marks the layouts put on the summary and the list.
     *
     * @param   string  $html    What the page shows
     * @param   string  $object  The type and the id of the object, for example com_content.article.5
     *
     * @return  array  summary, list: booleans
     *
     * @since   0.4.0
     */
    public static function shown(string $html, string $object): array
    {
        $mark = '="' . htmlspecialchars($object, ENT_QUOTES, 'UTF-8') . '"';

        return [
            'summary' => str_contains($html, 'data-mxr-summary' . $mark),
            'list'    => str_contains($html, 'data-mxr-list' . $mark),
        ];
    }
}
