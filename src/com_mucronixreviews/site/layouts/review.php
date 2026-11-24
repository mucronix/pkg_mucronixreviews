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
 * A review of the list.
 *
 * @var  object  $review  author and text are plain text
 * @var  string  $stars   The stars
 * @var  string  $date    The date. Empty if it is not shown.
 * @var  string  $time    The date for machines
 */
extract($displayData);
?>
<li class="mxr-review">
    <div class="mxr-review__head">
        <span class="mxr-review__author"><?php echo $this->escape($review->author); ?></span>
        <?php echo $stars; ?>
        <?php if ($date !== '') : ?>
            <time class="mxr-review__date" datetime="<?php echo $this->escape($time); ?>"><?php echo $this->escape($date); ?></time>
        <?php endif; ?>
    </div>
    <?php if ((string) $review->text !== '') : ?>
        <p class="mxr-review__text"><?php echo nl2br($this->escape($review->text), false); ?></p>
    <?php endif; ?>
</li>
