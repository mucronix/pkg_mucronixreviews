<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  plg_system_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The element: the output of the component, as the transform made it.
 *
 * @var  array  $props  The settings, mucronix_html: what is shown
 * @var  array  $attrs  The attributes of the builder
 */
$el = $this->el('div');

echo $el($props, $attrs, $props['mucronix_html']);
