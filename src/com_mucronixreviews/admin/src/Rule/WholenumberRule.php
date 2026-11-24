<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  com_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Mucronix\Component\MucronixReviews\Administrator\Rule;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormRule;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Checks a whole number between the attributes "min" and "max". The rule "number" of the core turns "abc" into 0.
 *
 * @since  0.1.0
 */
class WholenumberRule extends FormRule
{
    /**
     * Method to test the value.
     *
     * @param   \SimpleXMLElement  $element  The SimpleXMLElement object representing the form field.
     * @param   mixed              $value    The form field value to validate.
     * @param   ?string            $group    The field name group control value.
     * @param   ?Registry          $input    An optional Registry object with the entire data set to validate against the entire form.
     * @param   ?Form              $form     The form object for which the field is being tested.
     *
     * @return  boolean  True if the value is valid.
     *
     * @throws  \UnexpectedValueException  With what is wrong
     *
     * @since   0.1.0
     */
    public function test(\SimpleXMLElement $element, $value, $group = null, ?Registry $input = null, ?Form $form = null)
    {
        $label  = Text::_((string) $element['label']);
        $number = self::wholeNumber($value);

        if ($number === null) {
            throw new \UnexpectedValueException(Text::sprintf('COM_MUCRONIXREVIEWS_RULE_WHOLE_NUMBER', $label));
        }

        if (isset($element['min']) && $number < (int) $element['min']) {
            throw new \UnexpectedValueException(Text::sprintf('COM_MUCRONIXREVIEWS_RULE_MIN', $label, (int) $element['min']));
        }

        if (isset($element['max']) && $number > (int) $element['max']) {
            throw new \UnexpectedValueException(Text::sprintf('COM_MUCRONIXREVIEWS_RULE_MAX', $label, (int) $element['max']));
        }

        return true;
    }

    /**
     * Reads a whole number.
     *
     * @param   mixed  $value  The value
     *
     * @return  integer|null  Null if it is not one
     *
     * @since   0.1.0
     */
    protected static function wholeNumber(mixed $value): ?int
    {
        if (!\is_int($value) && !(\is_string($value) && preg_match('~^-?\d{1,9}$~D', $value))) {
            return null;
        }

        return (int) $value;
    }
}
