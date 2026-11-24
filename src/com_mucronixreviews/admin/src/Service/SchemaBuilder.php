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
use Joomla\Registry\Registry;
use Mucronix\Component\MucronixReviews\Administrator\Context\GraphSchemaContextInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The rating and the reviews of the object of a page, put into the graph of Schema.org that Joomla builds. Only real
 * data: published reviews, and only what the visitor sees on the page.
 *
 * @since  0.4.0
 */
class SchemaBuilder
{
    /**
     * Constructor
     *
     * @param   ReviewService    $reviews   The reviews
     * @param   ContextRegistry  $contexts  The types of objects
     *
     * @since   0.4.0
     */
    public function __construct(private readonly ReviewService $reviews, private readonly ContextRegistry $contexts)
    {
    }

    /**
     * Adds the rating of the object of the page to the graph.
     *
     * @param   Registry  $schema         The graph of Joomla
     * @param   string    $schemaContext  The context Joomla passes, for example com_content.article.5
     * @param   string    $html           What the page shows: the component and the modules
     *
     * @return  void
     *
     * @since   0.4.0
     */
    public function addToGraph(Registry $schema, string $schemaContext, string $html): void
    {
        $enabled = $this->reviews->getSettings()->schemaContexts();

        foreach ($this->contexts->getHandlers() as $context => $handler) {
            if (!$handler instanceof GraphSchemaContextInterface || !\in_array($context, $enabled, true)) {
                continue;
            }

            $itemId = $handler->getSchemaItemId($schemaContext);

            if ($itemId < 1) {
                continue;
            }

            $shown = SchemaGraph::shown($html, $context . '.' . $itemId);

            // The rating has to be on the page
            if (!$shown['summary']) {
                return;
            }

            $properties = $this->properties($context, $itemId, $shown['list']);
            $graph      = $schema->toArray()['@graph'] ?? null;

            if ($properties === null || !\is_array($graph)) {
                return;
            }

            $schema->set('@graph', SchemaGraph::attach($graph, $handler->getSchemaNodeId($itemId), $properties));

            return;
        }
    }

    /**
     * Returns the rating of an object and the reviews with a text that the list shows.
     *
     * @param   string   $context      The type of the object
     * @param   integer  $itemId       The id of the object
     * @param   boolean  $withReviews  Whether the list is on the page
     *
     * @return  array|null  Null if there is no published rating
     *
     * @since   0.4.0
     */
    public function properties(string $context, int $itemId, bool $withReviews): ?array
    {
        $stats = $this->reviews->getStats($context, $itemId);

        if ($stats->rating_count < 1) {
            return null;
        }

        $rating = [
            '@type'       => 'AggregateRating',
            'ratingValue' => number_format($stats->rating_average, 1, '.', ''),
            'bestRating'  => '5',
            'worstRating' => '1',
            'ratingCount' => (string) $stats->rating_count,
        ];

        if ($stats->text_count > 0) {
            $rating['reviewCount'] = (string) $stats->text_count;
        }

        $properties = ['aggregateRating' => $rating];

        if ($withReviews) {
            $reviews = [];

            // The first page of the list, as the block shows it
            foreach ($this->reviews->getReviews($context, $itemId) as $review) {
                if ((string) $review->text === '') {
                    continue;
                }

                $reviews[] = [
                    '@type'         => 'Review',
                    'author'        => ['@type' => 'Person', 'name' => (string) $review->author],
                    'reviewRating'  => ['@type' => 'Rating', 'ratingValue' => (string) (int) $review->rating, 'bestRating' => '5', 'worstRating' => '1'],
                    'reviewBody'    => (string) $review->text,
                    'datePublished' => Factory::getDate($review->created)->toISO8601(),
                ];
            }

            if ($reviews) {
                $properties['review'] = $reviews;
            }
        }

        return $properties;
    }
}
