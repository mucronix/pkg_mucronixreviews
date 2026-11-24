<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  plg_system_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Mucronix\Plugin\System\MucronixReviews\YOOtheme;

use Joomla\CMS\Language\Text;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The fields of the rating and the list of reviews, on every type of YOOtheme Pro whose objects can be reviewed.
 * Published reviews only; nothing of an object the visitor may not see.
 *
 * @since  0.5.0
 */
final class SourceListener
{
    /**
     * The group of the fields in the builder
     *
     * @var    string
     * @since  0.5.0
     */
    private const GROUP = 'Mucronix Reviews';

    /**
     * Adds the fields to the types.
     *
     * @param   object  $source  The source of YOOtheme Pro
     *
     * @return  void
     *
     * @since   0.5.0
     */
    public static function handle($source): void
    {
        $handlers = Objects::handlers();

        if (!$handlers) {
            return;
        }

        $source->objectType('MucronixReview', self::reviewType());

        foreach ($handlers as $context => $handler) {
            foreach ($handler->getYoothemeTypes() as $type) {
                $source->objectType($type, ['fields' => self::fields($context)]);
            }
        }
    }

    /**
     * The value of a field of an object.
     *
     * @param   object  $item  The object of YOOtheme Pro
     * @param   array   $args  context and value from the field, the arguments the builder sets
     *
     * @return  mixed  Null if the object cannot be seen
     *
     * @since   0.5.0
     */
    public static function resolve(object $item, array $args): mixed
    {
        $context = (string) ($args['context'] ?? '');
        $handler = Objects::handlers()[$context] ?? null;
        $itemId  = $handler?->getYoothemeItemId($item) ?? 0;

        if (!Objects::visible($context, $itemId)) {
            return null;
        }

        $component = Objects::component();
        $reviews   = $component->getReviewService();
        $stats     = $reviews->getStats($context, $itemId);

        return match ($args['value'] ?? '') {
            'average' => number_format($stats->rating_average, 2, '.', ''),
            'rounded' => number_format($reviews->getSettings()->rounding() === 'whole' ? round($stats->rating_average) : round($stats->rating_average * 2) / 2, 1, '.', ''),
            'ratings' => $stats->rating_count,
            'texts'   => $stats->text_count,
            'stars'   => $component->getBlockRenderer()->renderSummary($context, $itemId),
            'reviews' => self::reviews($context, $itemId, $args),
            default   => null,
        };
    }

    /**
     * Returns the published reviews of an object, for the list.
     *
     * @param   string   $context  The type of the object
     * @param   integer  $itemId   The id of the object
     * @param   array    $args     limit, order, text_only
     *
     * @return  object[]  author, rating, stars, text (HTML, escaped), date
     *
     * @since   0.5.0
     */
    private static function reviews(string $context, int $itemId, array $args): array
    {
        $component = Objects::component();
        $renderer  = $component->getBlockRenderer();
        $options = ['order' => \in_array($args['order'] ?? '', ['newest', 'highest', 'lowest'], true) ? $args['order'] : ''];
        $limit   = (int) ($args['limit'] ?? 0);

        if ($limit > 0) {
            $options['limit'] = min($limit, 100);
        }

        if (!empty($args['text_only'])) {
            $options['withTextOnly'] = true;
        }

        $list = [];

        foreach ($component->getReviewService()->getReviews($context, $itemId, $options) as $review) {
            $rating = (int) $review->rating;
            $list[] = (object) [
                'author' => (string) $review->author,
                'rating' => $rating,
                'stars'  => $renderer->renderRating($rating),
                'text'   => nl2br(htmlspecialchars((string) $review->text, ENT_QUOTES, 'UTF-8'), false),
                'date'   => (string) $review->created,
            ];
        }

        return $list;
    }

    /**
     * The fields a type gets.
     *
     * @param   string  $context  The type of objects
     *
     * @return  array
     *
     * @since   0.5.0
     */
    private static function fields(string $context): array
    {
        $field = static fn (string|array $type, string $value, string $label, array $more = []): array => array_replace_recursive([
            'type'       => $type,
            'metadata'   => ['label' => Text::_($label), 'group' => self::GROUP],
            'extensions' => ['call' => ['func' => self::class . '::resolve', 'args' => ['context' => $context, 'value' => $value]]],
        ], $more);

        return [
            'mucronixRating'        => $field('String', 'average', 'PLG_SYSTEM_MUCRONIXREVIEWS_FIELD_AVERAGE'),
            'mucronixRatingRounded' => $field('String', 'rounded', 'PLG_SYSTEM_MUCRONIXREVIEWS_FIELD_ROUNDED'),
            'mucronixRatingCount'   => $field('Int', 'ratings', 'PLG_SYSTEM_MUCRONIXREVIEWS_FIELD_RATINGS'),
            'mucronixReviewCount'   => $field('Int', 'texts', 'PLG_SYSTEM_MUCRONIXREVIEWS_FIELD_TEXTS'),
            'mucronixStars'         => $field('String', 'stars', 'PLG_SYSTEM_MUCRONIXREVIEWS_FIELD_STARS'),
            'mucronixReviews'       => $field(['listOf' => 'MucronixReview'], 'reviews', 'PLG_SYSTEM_MUCRONIXREVIEWS_FIELD_REVIEWS', [
                'args'     => [
                    'limit'     => ['type' => 'Int'],
                    'order'     => ['type' => 'String'],
                    'text_only' => ['type' => 'Boolean'],
                ],
                'metadata' => [
                    'arguments' => [
                        'limit'     => [
                            'label'   => Text::_('PLG_SYSTEM_MUCRONIXREVIEWS_FIELD_LIMIT'),
                            'type'    => 'limit',
                            'attrs'   => ['placeholder' => Text::_('PLG_SYSTEM_MUCRONIXREVIEWS_FIELD_LIMIT_DEFAULT'), 'min' => 0],
                        ],
                        'order'     => [
                            'label'   => Text::_('PLG_SYSTEM_MUCRONIXREVIEWS_FIELD_ORDER'),
                            'type'    => 'select',
                            'default' => '',
                            'options' => [
                                Text::_('PLG_SYSTEM_MUCRONIXREVIEWS_FIELD_ORDER_DEFAULT') => '',
                                Text::_('PLG_SYSTEM_MUCRONIXREVIEWS_FIELD_ORDER_NEWEST')  => 'newest',
                                Text::_('PLG_SYSTEM_MUCRONIXREVIEWS_FIELD_ORDER_HIGHEST') => 'highest',
                                Text::_('PLG_SYSTEM_MUCRONIXREVIEWS_FIELD_ORDER_LOWEST')  => 'lowest',
                            ],
                        ],
                        'text_only' => [
                            'label' => Text::_('PLG_SYSTEM_MUCRONIXREVIEWS_FIELD_TEXT_ONLY'),
                            'type'  => 'checkbox',
                            'text'  => Text::_('PLG_SYSTEM_MUCRONIXREVIEWS_FIELD_TEXT_ONLY_TEXT'),
                        ],
                    ],
                ],
            ]),
        ];
    }

    /**
     * The type of a review.
     *
     * @return  array
     *
     * @since   0.5.0
     */
    private static function reviewType(): array
    {
        $field = static fn (string $type, string $label, array $more = []): array => array_replace_recursive([
            'type'     => $type,
            'metadata' => ['label' => Text::_($label)],
        ], $more);

        return [
            'fields'   => [
                'author' => $field('String', 'PLG_SYSTEM_MUCRONIXREVIEWS_FIELD_AUTHOR'),
                'rating' => $field('Int', 'PLG_SYSTEM_MUCRONIXREVIEWS_FIELD_RATING'),
                'stars'  => $field('String', 'PLG_SYSTEM_MUCRONIXREVIEWS_FIELD_STARS_OF_REVIEW'),
                'text'   => $field('String', 'PLG_SYSTEM_MUCRONIXREVIEWS_FIELD_TEXT'),
                'date'   => $field('String', 'PLG_SYSTEM_MUCRONIXREVIEWS_FIELD_DATE', ['metadata' => ['filters' => ['date']]]),
            ],
            'metadata' => ['type' => true, 'label' => Text::_('PLG_SYSTEM_MUCRONIXREVIEWS_FIELD_REVIEW')],
        ];
    }
}
