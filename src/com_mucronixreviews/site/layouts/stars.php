<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  com_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

/**
 * Stars that are only looked at.
 *
 * @var  integer  $fill   How many of 50 tenths are filled, in steps of five
 * @var  string   $label  What a screen reader says. Empty if the text next to the stars says it.
 */
extract($displayData);
?>
<span class="mxr-stars mxr-stars--<?php echo (int) $fill; ?>"<?php echo $label !== '' ? ' role="img" aria-label="' . $this->escape($label) . '"' : ' aria-hidden="true"'; ?>></span>
