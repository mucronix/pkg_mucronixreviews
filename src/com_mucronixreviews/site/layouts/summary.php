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
 * The summary: stars, average, number of ratings. Only inline elements: it may stand inside a paragraph.
 *
 * @var  string   $stars    The stars
 * @var  string   $average  The average, with one digit after the separator
 * @var  integer  $ratings  The number of ratings
 * @var  string   $object   The type and the id of the object: the markup of Schema.org looks for it
 */
extract($displayData);
?>
<span class="mxr-summary" data-mxr-summary="<?php echo $this->escape($object); ?>">
    <?php echo $stars; ?>
    <?php if ($ratings > 0) : ?>
        <span class="mxr-summary__average"><?php echo $this->escape(Text::sprintf('COM_MUCRONIXREVIEWS_AVERAGE_OF_FIVE', $average)); ?></span>
        <span class="mxr-summary__count"><?php echo $this->escape(Text::plural('COM_MUCRONIXREVIEWS_N_RATINGS', $ratings)); ?></span>
    <?php else : ?>
        <span class="mxr-summary__count"><?php echo $this->escape(Text::_('COM_MUCRONIXREVIEWS_NO_RATINGS')); ?></span>
    <?php endif; ?>
</span>
