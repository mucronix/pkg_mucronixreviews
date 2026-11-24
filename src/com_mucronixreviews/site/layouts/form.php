<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  com_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/**
 * The form. It opens and sends without JavaScript, too.
 *
 * @var  string   $id         The start of the ids
 * @var  string   $action     Where it is sent
 * @var  string   $return     The page to come back to, encoded
 * @var  string   $context    The type of the object
 * @var  integer  $itemId     The id of the object
 * @var  boolean  $isEdit     Whether the user has a review already
 * @var  string   $unshown    Why that review is not shown: pending, hidden (rejected, in the trash, spam), or empty
 * @var  boolean  $isOpen     Whether the form is open
 * @var  boolean  $showList   Whether the list is shown with the form
 * @var  integer  $rating     The stars
 * @var  string   $text       The text
 * @var  string   $textMode   off, optional or required
 * @var  integer  $textMin    The shortest text
 * @var  integer  $textMax    The longest text
 * @var  array    $ui         The classes of the template (BlockRenderer::ui())
 */
extract($displayData);
$ui = ($ui ?? []) + ['kit' => '', 'button' => '', 'submit' => '', 'textarea' => '', 'notice' => '', 'success' => '', 'error' => ''];
$more = static fn (string $key): string => $ui[$key] !== '' ? ' ' . htmlspecialchars($ui[$key], ENT_QUOTES, 'UTF-8') : '';
?>
<details class="mxr-form"<?php echo $isOpen ? ' open' : ''; ?>>
    <summary>
        <span class="mxr-form__button<?php echo $more('button'); ?>"><?php echo $this->escape(Text::_($isEdit ? 'COM_MUCRONIXREVIEWS_FORM_EDIT' : 'COM_MUCRONIXREVIEWS_FORM_NEW')); ?></span>
        <?php if ($unshown !== '') : ?>
            <span class="mxr-form__state"><?php echo $this->escape(Text::_($unshown === 'pending' ? 'COM_MUCRONIXREVIEWS_FORM_PENDING' : 'COM_MUCRONIXREVIEWS_FORM_UNPUBLISHED')); ?></span>
        <?php endif; ?>
    </summary>
    <form action="<?php echo $this->escape($action); ?>" method="post" data-mxr-form>
        <fieldset class="mxr-field">
            <legend><?php echo $this->escape(Text::_('COM_MUCRONIXREVIEWS_FORM_RATING')); ?></legend>
            <div class="mxr-rate">
                <?php for ($stars = 1; $stars <= 5; $stars++) : ?>
                    <label>
                        <input type="radio" name="rating" value="<?php echo $stars; ?>" required<?php echo $stars === $rating ? ' checked' : ''; ?>>
                        <span class="mxr-rate__star" aria-hidden="true"></span>
                        <span class="mxr-visually-hidden"><?php echo $this->escape(Text::plural('COM_MUCRONIXREVIEWS_N_STARS', $stars)); ?></span>
                    </label>
                <?php endfor; ?>
            </div>
        </fieldset>
        <?php if ($textMode !== 'off') : ?>
            <div class="mxr-field">
                <label for="<?php echo $this->escape($id); ?>-text"><?php echo $this->escape(Text::_('COM_MUCRONIXREVIEWS_FORM_TEXT')); ?><?php echo $textMode === 'required' ? ' <span aria-hidden="true">*</span>' : ''; ?></label>
                <textarea class="mxr-textarea<?php echo $more('textarea'); ?>" id="<?php echo $this->escape($id); ?>-text" name="text" rows="5" maxlength="<?php echo (int) $textMax; ?>"<?php echo $textMode === 'required' ? ' required' . ($textMin > 0 ? ' minlength="' . (int) $textMin . '"' : '') : ''; ?>><?php echo $this->escape("\n" . $text); ?></textarea>
                <?php if ($textMode === 'required' && $textMin > 0) : ?>
                    <p class="mxr-hint"><?php echo $this->escape(Text::sprintf('COM_MUCRONIXREVIEWS_FORM_TEXT_MIN', $textMin)); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <input type="hidden" name="context" value="<?php echo $this->escape($context); ?>">
        <input type="hidden" name="item_id" value="<?php echo (int) $itemId; ?>">
        <input type="hidden" name="return" value="<?php echo $this->escape($return); ?>">
        <input type="hidden" name="list" value="<?php echo $showList ? 1 : 0; ?>">
        <?php echo HTMLHelper::_('form.token'); ?>
        <button type="submit" class="mxr-submit<?php echo $more('submit'); ?>"><?php echo $this->escape(Text::_($isEdit ? 'COM_MUCRONIXREVIEWS_FORM_SAVE' : 'COM_MUCRONIXREVIEWS_FORM_SEND')); ?></button>
    </form>
</details>
