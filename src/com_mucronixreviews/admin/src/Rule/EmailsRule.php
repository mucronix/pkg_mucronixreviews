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
use Joomla\CMS\Mail\MailHelper;
use Joomla\Registry\Registry;
use Mucronix\Component\MucronixReviews\Administrator\Service\Settings;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Checks a list of addresses: each one valid, no more than ten. An empty list is right.
 *
 * @since  0.6.0
 */
class EmailsRule extends FormRule
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
     * @since   0.6.0
     */
    public function test(\SimpleXMLElement $element, $value, $group = null, ?Registry $input = null, ?Form $form = null)
    {
        $emails = Settings::emailList(\is_string($value) ? $value : '');

        foreach ($emails as $email) {
            if (!MailHelper::isEmailAddress($email)) {
                throw new \UnexpectedValueException(Text::sprintf('COM_MUCRONIXREVIEWS_RULE_EMAIL', $email));
            }
        }

        if (\count(array_unique(array_map('strtolower', $emails))) > Settings::NOTIFY_EMAILS_MAX) {
            throw new \UnexpectedValueException(Text::sprintf('COM_MUCRONIXREVIEWS_RULE_EMAILS_MAX', Text::_((string) $element['label']), Settings::NOTIFY_EMAILS_MAX));
        }

        return true;
    }
}
