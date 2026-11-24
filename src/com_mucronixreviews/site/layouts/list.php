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
 * The list of the reviews, the first page of it.
 *
 * @var  string        $reviews  The reviews
 * @var  integer|null  $next     Where the next page starts. Null if there is none.
 * @var  string        $url      Where the next page comes from
 * @var  string        $object   The type and the id of the object: the markup of Schema.org looks for it
 * @var  array         $ui       The classes of the template (BlockRenderer::ui())
 */
extract($displayData);
$ui = ($ui ?? []) + ['kit' => '', 'button' => '', 'submit' => '', 'textarea' => '', 'notice' => '', 'success' => '', 'error' => ''];
$more = static fn (string $key): string => $ui[$key] !== '' ? ' ' . htmlspecialchars($ui[$key], ENT_QUOTES, 'UTF-8') : '';
?>
<ul class="mxr-list" data-mxr-list="<?php echo $this->escape($object); ?>"><?php echo $reviews; ?></ul>
<?php if ($next !== null) : ?>
    <button type="button" class="mxr-more<?php echo $more('button'); ?>" hidden data-mxr-url="<?php echo $this->escape($url); ?>" data-mxr-offset="<?php echo (int) $next; ?>"><?php echo $this->escape(Text::_('COM_MUCRONIXREVIEWS_SHOW_MORE')); ?></button>
<?php endif; ?>
