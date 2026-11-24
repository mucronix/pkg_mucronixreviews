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
use Joomla\CMS\Router\Route;

/** @var \Mucronix\Component\MucronixReviews\Administrator\View\Review\HtmlView $this */

$this->getDocument()->getWebAssetManager()
    ->useScript('keepalive')
    ->useScript('form.validate');
?>
<form action="<?php echo Route::_('index.php?option=com_mucronixreviews&layout=edit&id=' . (int) $this->item->id); ?>" method="post" name="adminForm" id="review-form" class="form-validate" aria-label="<?php echo Text::_('COM_MUCRONIXREVIEWS_REVIEW_TITLE', true); ?>">
    <div class="row main-card p-3">
        <div class="col-lg-8">
            <?php echo $this->form->renderFieldset('review'); ?>
        </div>
        <div class="col-lg-4">
            <dl>
                <dt><?php echo Text::_('COM_MUCRONIXREVIEWS_HEADING_OBJECT'); ?></dt>
                <dd class="text-break">
                    <?php if ($this->object->url !== '') : ?>
                        <a href="<?php echo $this->escape($this->object->url); ?>" target="_blank" rel="noopener"><?php echo $this->escape($this->object->title); ?><span class="visually-hidden"> <?php echo Text::_('JBROWSERTARGET_NEW_TITLE'); ?></span></a>
                    <?php else : ?>
                        <?php echo $this->escape($this->object->title); ?>
                    <?php endif; ?>
                    <div class="small"><?php echo $this->escape($this->object->type); ?></div>
                </dd>
                <dt><?php echo Text::_('JAUTHOR'); ?></dt>
                <dd class="text-break">
                    <?php echo $this->escape($this->account ?? $this->item->username); ?>
                    <?php if ($this->account === null) : ?>
                        <span class="text-muted">(<?php echo Text::_('COM_MUCRONIXREVIEWS_ACCOUNT_DELETED'); ?>)</span>
                    <?php endif; ?>
                </dd>
                <dt><?php echo Text::_('COM_MUCRONIXREVIEWS_CREATED'); ?></dt>
                <dd><?php echo HTMLHelper::_('date', $this->item->created, Text::_('DATE_FORMAT_LC5')); ?></dd>
                <?php if ($this->item->modified) : ?>
                    <dt><?php echo Text::_('COM_MUCRONIXREVIEWS_CHANGED_BY_AUTHOR'); ?></dt>
                    <dd><?php echo HTMLHelper::_('date', $this->item->modified, Text::_('DATE_FORMAT_LC5')); ?></dd>
                <?php endif; ?>
                <dt><?php echo Text::_('JGRID_HEADING_ID'); ?></dt>
                <dd><?php echo (int) $this->item->id; ?></dd>
            </dl>
        </div>
    </div>
    <?php echo $this->form->renderControlFields(); ?>
</form>
