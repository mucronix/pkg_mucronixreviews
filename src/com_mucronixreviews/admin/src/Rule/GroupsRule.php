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
use Joomla\CMS\Helper\UserGroupsHelper;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Checks that at least one group is chosen and that the groups exist, each with a message of its own (since 0.6.1).
 * With allowempty="true" no group is right too (since 0.6.0).
 *
 * @since  0.1.0
 */
class GroupsRule extends FormRule
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
        $groups = array_map('intval', array_filter((array) $value, static fn ($group): bool => $group !== '' && $group !== null));

        if (!$groups && (string) $element['allowempty'] === 'true') {
            return true;
        }

        if (!$groups) {
            throw new \UnexpectedValueException(Text::sprintf('COM_MUCRONIXREVIEWS_RULE_GROUPS', Text::_((string) $element['label'])));
        }

        // A group that is not there: one deleted meanwhile, or a value sent by hand (since 0.6.1)
        if (array_diff($groups, array_map('intval', array_keys(UserGroupsHelper::getInstance()->getAll())))) {
            throw new \UnexpectedValueException(Text::sprintf('COM_MUCRONIXREVIEWS_RULE_GROUPS_UNKNOWN', Text::_((string) $element['label'])));
        }

        return true;
    }
}
