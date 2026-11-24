<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  com_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/**
 * The whole block.
 *
 * @var  string  $anchor  The id of the block
 * @var  string  $body    What the script replaces after a review was sent
 * @var  array   $ui      The classes of the template (BlockRenderer::ui())
 */
extract($displayData);
$ui = ($ui ?? []) + ['kit' => '', 'button' => '', 'submit' => '', 'textarea' => '', 'notice' => '', 'success' => '', 'error' => ''];
$more = static fn (string $key): string => $ui[$key] !== '' ? ' ' . htmlspecialchars($ui[$key], ENT_QUOTES, 'UTF-8') : '';
?>
<section class="mxr-block<?php echo $ui['kit'] !== '' ? ' mxr-block--' . $this->escape($ui['kit']) : ''; ?>" data-mxr-success="<?php echo $this->escape($ui['success']); ?>" data-mxr-failure="<?php echo $this->escape($ui['error']); ?>" id="<?php echo $this->escape($anchor); ?>" aria-label="<?php echo $this->escape(Text::_('COM_MUCRONIXREVIEWS_BLOCK_LABEL')); ?>" data-mxr-error="<?php echo $this->escape(Text::_('COM_MUCRONIXREVIEWS_ERROR_UNAVAILABLE_NOW')); ?>">
    <div class="mxr-status" role="status" tabindex="-1"></div>
    <div class="mxr-body"><?php echo $body; ?></div>
</section>
