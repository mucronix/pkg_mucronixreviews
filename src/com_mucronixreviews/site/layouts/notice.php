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
 * What stands instead of the form.
 *
 * @var  string  $html  The text, with its links
 * @var  array   $ui    The classes of the template (BlockRenderer::ui())
 */
extract($displayData);
$ui = ($ui ?? []) + ['kit' => '', 'button' => '', 'submit' => '', 'textarea' => '', 'notice' => '', 'success' => '', 'error' => ''];
$more = static fn (string $key): string => $ui[$key] !== '' ? ' ' . htmlspecialchars($ui[$key], ENT_QUOTES, 'UTF-8') : '';
?>
<div class="mxr-notice<?php echo $more('notice'); ?>"><?php echo $html; ?></div>
