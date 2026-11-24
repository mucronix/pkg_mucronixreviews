<?php

/**
 * What mutants.php --yootheme breaks: the file in the site, what stands there once, what replaces it.
 * Each has to be caught by a check of yootheme.php.
 */

$yoo     = 'plugins/system/mucronixreviews/src/YOOtheme/';
$plugin  = 'plugins/system/mucronixreviews/src/Extension/MucronixReviews.php';
$block   = 'components/com_mucronixreviews/src/Service/BlockRenderer.php';
$layouts = 'components/com_mucronixreviews/layouts/';

return [
    'yoo-load'          => [$plugin, "if (!\$this->params->get('yootheme', 1) || ", 'if (true || '],
    'yoo-param'         => [$plugin, "!\$this->params->get('yootheme', 1) || ", ''],
    'yoo-visible'       => [$yoo . 'Objects.php', '? $registry->isViewable($context, $itemId, $user)' . "\n" . '                : $handler->isAvailable($itemId, $user);', "? true\n                : true;"],
    'yoo-component'     => [$yoo . 'Objects.php', "if (!ComponentHelper::isEnabled('com_mucronixreviews')) {", 'if (false) {'],
    'yoo-mode-summary'  => [$yoo . 'Element.php', "if (\$mode === 'summary') {", 'if (false) {'],
    'yoo-mode-form'     => [$yoo . 'Element.php', "'form' => \$mode === 'full'", "'form' => true"],
    'yoo-item'          => [$yoo . 'Element.php', "\$itemId   = (int) (\$props['item_id'] ?? 0);", '$itemId   = 0;'],
    'yoo-page-type'     => [$yoo . 'Element.php', 'if ($handler->getPageItemId() > 0) {', 'if (false) {'],
    'src-average'       => [$yoo . 'SourceListener.php', "'average' => number_format(\$stats->rating_average, 2, '.', '')", "'average' => number_format(\$stats->rating_average, 1, '.', '')"],
    'src-rounded'       => [$yoo . 'SourceListener.php', "rounding() === 'whole' ? round(", "rounding() === 'x' ? round("],
    'src-texts'         => [$yoo . 'SourceListener.php', "'texts'   => \$stats->text_count,", "'texts'   => \$stats->rating_count,"],
    'src-text-only'     => [$yoo . 'SourceListener.php', "if (!empty(\$args['text_only'])) {", 'if (false) {'],
    'src-limit'         => [$yoo . 'SourceListener.php', "\$options['limit'] = min(\$limit, 100);", ''],
    'src-order'         => [$yoo . 'SourceListener.php', "\\in_array(\$args['order'] ?? '', ['newest', 'highest', 'lowest'], true) ? \$args['order'] : ''", "''"],
    'src-escape'        => [$yoo . 'SourceListener.php', "nl2br(htmlspecialchars((string) \$review->text, ENT_QUOTES, 'UTF-8'), false)", 'nl2br((string) $review->text, false)'],
    'src-type'          => [$yoo . 'SourceListener.php', "\$source->objectType(\$type, ['fields' => self::fields(\$context)]);", ''],
    'src-review-type'   => [$yoo . 'SourceListener.php', "\$source->objectType('MucronixReview', self::reviewType());", ''],
    'ui-template'       => [$block, "(\$template->template === 'yootheme' || (\$template->parent ?? '') === 'yootheme')", 'false'],
    'ui-own'            => [$block, "controlStyle() === 'auto'", "controlStyle() !== 'x'"],
    'ui-button'         => [$layouts . 'form.php', '<span class="mxr-form__button<?php echo $more(\'button\'); ?>">', '<span class="mxr-form__button">'],
    'ui-submit'         => [$layouts . 'form.php', '<button type="submit" class="mxr-submit<?php echo $more(\'submit\'); ?>">', '<button type="submit" class="mxr-submit">'],
    'ui-textarea'       => [$layouts . 'form.php', '<textarea class="mxr-textarea<?php echo $more(\'textarea\'); ?>"', '<textarea class="mxr-textarea"'],
    'ui-notice'         => [$layouts . 'notice.php', '<div class="mxr-notice<?php echo $more(\'notice\'); ?>">', '<div class="mxr-notice">'],
    'ui-block'          => [$layouts . 'block.php', "<?php echo \$ui['kit'] !== '' ? ' mxr-block--' . \$this->escape(\$ui['kit']) : ''; ?>", ''],
    'ui-failure'        => [$layouts . 'block.php', 'data-mxr-failure="<?php echo $this->escape($ui[\'error\']); ?>"', 'data-mxr-failure=""'],
    'id-article'        => [$yoo . 'Element.php', "if (\$context === '' && \$itemId > 0) {", 'if (false) {'],
    'id-article-type'   => [$yoo . 'Element.php', "\$context = 'com_content.article';", "\$context = 'com_content.item';"],
    'once-preview'      => [$yoo . 'Element.php', "if (\$html === '' && \$preview) {", "if (\$html === '') {"],
    'once-empty'        => [$yoo . 'Element.php', "if (\$html === '' && \$preview) {", 'if ($preview) {'],
    'once-flag'         => ['plugins/system/mucronixreviews/yootheme/elements/mucronix_reviews/element.php', "(bool) app(Config::class)->get('app.isCustomizer')", 'false'],
    'once'              => [$block, "if (isset(\$this->blocks[\$context . '.' . \$itemId])) {", 'if (false) {'],
];
