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
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Mucronix\Component\MucronixReviews\Administrator\View\Reviews\HtmlView $this */

$this->getDocument()->getWebAssetManager()
    ->useScript('table.columns')
    ->useScript('multiselect');

$user      = $this->getCurrentUser();
$canEdit   = $user->authorise('core.edit', 'com_mucronixreviews');
$canChange = $user->authorise('core.edit.state', 'com_mucronixreviews');
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$states    = $this->rowStates();
?>
<form action="<?php echo Route::_('index.php?option=com_mucronixreviews&view=reviews'); ?>" method="post" name="adminForm" id="adminForm">
    <div id="j-main-container" class="j-main-container">
        <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>
        <?php if (empty($this->items)) : ?>
            <div class="alert alert-info">
                <span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
                <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
            </div>
        <?php else : ?>
            <table class="table" id="reviewList">
                <caption class="visually-hidden">
                    <?php echo Text::_('COM_MUCRONIXREVIEWS_REVIEWS_TITLE'); ?>,
                    <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?> </span>,
                    <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
                </caption>
                <thead>
                    <tr>
                        <td class="w-1 text-center">
                            <?php echo HTMLHelper::_('grid.checkall'); ?>
                        </td>
                        <th scope="col" class="w-1 text-center">
                            <?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.state', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col" class="w-10">
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_MUCRONIXREVIEWS_FIELD_RATING_LABEL', 'a.rating', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col">
                            <?php echo Text::_('COM_MUCRONIXREVIEWS_HEADING_REVIEW'); ?>
                        </th>
                        <th scope="col" class="w-20 d-none d-md-table-cell">
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_MUCRONIXREVIEWS_HEADING_OBJECT', 'a.context', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col" class="w-10 d-none d-md-table-cell">
                            <?php echo HTMLHelper::_('searchtools.sort', 'JAUTHOR', 'author', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col" class="w-10 d-none d-lg-table-cell">
                            <?php echo HTMLHelper::_('searchtools.sort', 'JDATE', 'a.created', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col" class="w-3 d-none d-lg-table-cell">
                            <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->items as $i => $item) : ?>
                        <?php
                        $excerpt = mb_strlen((string) $item->excerpt) > 120 ? mb_substr($item->excerpt, 0, 120) . '…' : (string) $item->excerpt;
                        $name    = $excerpt !== '' ? $excerpt : Text::_('COM_MUCRONIXREVIEWS_STARS_ONLY');
                        ?>
                        <tr class="row<?php echo $i % 2; ?>">
                            <td class="text-center">
                                <?php echo HTMLHelper::_('grid.id', $i, $item->id, false, 'cid', 'cb', $this->escape($name)); ?>
                            </td>
                            <td class="text-center">
                                <?php echo HTMLHelper::_('jgrid.state', $states, (int) $item->state, $i, 'reviews.', $canChange); ?>
                            </td>
                            <td class="text-nowrap">
                                <span aria-hidden="true"><?php echo str_repeat('★', (int) $item->rating) . str_repeat('☆', 5 - (int) $item->rating); ?></span>
                                <span class="visually-hidden"><?php echo Text::sprintf('COM_MUCRONIXREVIEWS_N_OF_FIVE', (int) $item->rating); ?></span>
                            </td>
                            <th scope="row" class="has-context">
                                <div class="text-break">
                                    <?php if ($canEdit) : ?>
                                        <a href="<?php echo Route::_('index.php?option=com_mucronixreviews&task=review.edit&id=' . (int) $item->id); ?>"><?php echo $this->escape($name); ?></a>
                                    <?php else : ?>
                                        <?php echo $this->escape($name); ?>
                                    <?php endif; ?>
                                </div>
                            </th>
                            <td class="text-break d-none d-md-table-cell">
                                <?php if ($item->object->url !== '') : ?>
                                    <a href="<?php echo $this->escape($item->object->url); ?>" target="_blank" rel="noopener"><?php echo $this->escape($item->object->title); ?><span class="visually-hidden"> <?php echo Text::_('JBROWSERTARGET_NEW_TITLE'); ?></span></a>
                                <?php else : ?>
                                    <?php echo $this->escape($item->object->title); ?>
                                <?php endif; ?>
                                <div class="small"><?php echo $this->escape($item->object->type); ?></div>
                            </td>
                            <td class="small text-break d-none d-md-table-cell">
                                <?php echo $this->escape($item->author); ?>
                                <?php if (!$item->account_id) : ?>
                                    <div class="text-muted"><?php echo Text::_('COM_MUCRONIXREVIEWS_ACCOUNT_DELETED'); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="small d-none d-lg-table-cell">
                                <?php echo HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC5')); ?>
                            </td>
                            <td class="d-none d-lg-table-cell">
                                <?php echo (int) $item->id; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php echo $this->pagination->getListFooter(); ?>
        <?php endif; ?>
        <?php echo $this->filterForm->renderControlFields(); ?>
    </div>
</form>
