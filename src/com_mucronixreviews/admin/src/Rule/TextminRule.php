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
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Checks the shortest text: a whole number that is not greater than the longest text.
 *
 * @since  0.1.0
 */
class TextminRule extends WholenumberRule
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
        parent::test($element, $value, $group, $input, $form);

        $maximum = self::wholeNumber($input?->get('text_max'));

        // A wrong maximum has a message of its own
        if ($maximum !== null && $maximum > 0 && (int) $value > $maximum) {
            throw new \UnexpectedValueException(Text::_('COM_MUCRONIXREVIEWS_RULE_MIN_OVER_MAX'));
        }

        return true;
    }
}
