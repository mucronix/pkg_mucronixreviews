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
 * What is in the block.
 *
 * @var  string  $summary  The summary
 * @var  string  $form     The form, or the notice instead of it. Empty if it is not shown.
 * @var  string  $list     The list. Empty if it is not shown.
 */
extract($displayData);

echo $summary . $form . $list;
