<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  com_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Mucronix\Component\MucronixReviews\Administrator\Extension;

use Joomla\CMS\Extension\MVCComponent;
use Mucronix\Component\MucronixReviews\Administrator\Service\ContextRegistry;
use Mucronix\Component\MucronixReviews\Administrator\Service\ReviewService;
use Mucronix\Component\MucronixReviews\Administrator\Service\SchemaBuilder;
use Mucronix\Component\MucronixReviews\Site\Service\BlockRenderer;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Component class for com_mucronixreviews. The way to the reviews for other extensions:
 * $app->bootComponent('com_mucronixreviews')->getReviewService()
 *
 * @since  0.1.0
 */
class MucronixReviewsComponent extends MVCComponent
{
    /**
     * The reviews
     *
     * @var    ReviewService
     * @since  0.1.0
     */
    private ReviewService $reviewService;

    /**
     * The types of objects
     *
     * @var    ContextRegistry
     * @since  0.1.0
     */
    private ContextRegistry $contextRegistry;

    /**
     * The output on the site
     *
     * @var    BlockRenderer|null
     * @since  0.3.0
     */
    private ?BlockRenderer $blockRenderer = null;

    /**
     * The markup of Schema.org
     *
     * @var    SchemaBuilder|null
     * @since  0.4.0
     */
    private ?SchemaBuilder $schemaBuilder = null;

    /**
     * Returns the reviews.
     *
     * @return  ReviewService
     *
     * @since   0.1.0
     */
    public function getReviewService(): ReviewService
    {
        return $this->reviewService;
    }

    /**
     * Sets the reviews.
     *
     * @param   ReviewService  $reviewService  The service
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function setReviewService(ReviewService $reviewService): void
    {
        $this->reviewService = $reviewService;
    }

    /**
     * Returns the types of objects.
     *
     * @return  ContextRegistry
     *
     * @since   0.1.0
     */
    public function getContextRegistry(): ContextRegistry
    {
        return $this->contextRegistry;
    }

    /**
     * Sets the types of objects.
     *
     * @param   ContextRegistry  $contextRegistry  The registry
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function setContextRegistry(ContextRegistry $contextRegistry): void
    {
        $this->contextRegistry = $contextRegistry;
    }

    /**
     * Returns the output on the site.
     *
     * @return  BlockRenderer
     *
     * @since   0.3.0
     */
    public function getBlockRenderer(): BlockRenderer
    {
        return $this->blockRenderer ??= new BlockRenderer($this->reviewService, $this->contextRegistry);
    }

    /**
     * Returns the markup of Schema.org.
     *
     * @return  SchemaBuilder
     *
     * @since   0.4.0
     */
    public function getSchemaBuilder(): SchemaBuilder
    {
        return $this->schemaBuilder ??= new SchemaBuilder($this->reviewService, $this->contextRegistry);
    }
}
