<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  com_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Mucronix\Component\MucronixReviews\Site\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Answers a script.
 *
 * @since  0.3.0
 */
trait JsonAnswerTrait
{
    /**
     * Whether the answer is for a script.
     *
     * @return  boolean
     *
     * @since   0.3.0
     */
    private function isJson(): bool
    {
        return $this->input->get('format', '', 'raw') === 'json';
    }

    /**
     * Sends a JSON answer and ends the request.
     *
     * @param   integer  $status  The HTTP status
     * @param   array    $data    The answer
     *
     * @return  never
     *
     * @since   0.3.0
     */
    private function respond(int $status, array $data): never
    {
        $this->app->setHeader('status', $status, true);
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $this->app->setHeader('Cache-Control', 'no-store', true);
        $this->app->sendHeaders();

        echo json_encode($data);

        $this->app->close();
    }
}
