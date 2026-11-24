<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  com_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Mucronix\Component\MucronixReviews\Administrator\Field;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\CheckboxesField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Mucronix\Component\MucronixReviews\Administrator\Extension\MucronixReviewsComponent;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The registered types of objects, as checkboxes.
 *
 * @since  0.1.0
 */
class ContextsField extends CheckboxesField
{
    /**
     * The form field type
     *
     * @var    string
     * @since  0.1.0
     */
    protected $type = 'Contexts';

    /**
     * Returns the markup of the field.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    protected function getInput()
    {
        if (!$this->getOptions()) {
            return '<div class="form-control-plaintext">' . Text::_('COM_MUCRONIXREVIEWS_FIELD_SCHEMA_CONTEXTS_NONE') . '</div>';
        }

        return parent::getInput();
    }

    /**
     * Returns the types.
     *
     * @return  object[]
     *
     * @since   0.1.0
     */
    protected function getOptions()
    {
        $options   = [];
        $component = Factory::getApplication()->bootComponent('com_mucronixreviews');

        if ($component instanceof MucronixReviewsComponent) {
            foreach ($component->getContextRegistry()->getHandlers() as $context => $handler) {
                if ($handler->getSchemaType() !== null) {
                    $options[$context] = HTMLHelper::_('select.option', $context, htmlspecialchars($handler->getLabel(), ENT_QUOTES, 'UTF-8'));
                }
            }
        }

        // A type whose adapter is disabled for the time being must not lose its setting when the options are saved
        foreach (array_filter((array) $this->value, static fn ($value): bool => \is_string($value) && $value !== '') as $context) {
            $options[$context] ??= HTMLHelper::_('select.option', $context, htmlspecialchars($context, ENT_QUOTES, 'UTF-8'));
        }

        foreach ($options as $option) {
            $option->checked = false;
            $option->class   = '';
        }

        return array_values($options);
    }
}
