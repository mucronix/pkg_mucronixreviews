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
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The types of objects to filter by: the registered ones and those that only have reviews left.
 *
 * @since  0.2.0
 */
class ContexttypeField extends ListField
{
    /**
     * The form field type
     *
     * @var    string
     * @since  0.2.0
     */
    protected $type = 'Contexttype';

    /**
     * Returns the types.
     *
     * @return  object[]
     *
     * @since   0.2.0
     */
    protected function getOptions()
    {
        $db    = $this->getDatabase();
        $query = $db->createQuery()
            ->select('DISTINCT ' . $db->quoteName('context'))
            ->from($db->quoteName('#__mucronixreviews_reviews'));

        $registry = Factory::getApplication()->bootComponent('com_mucronixreviews')->getContextRegistry();
        $options  = [];

        foreach (array_unique([...array_keys($registry->getHandlers()), ...$db->setQuery($query)->loadColumn()]) as $context) {
            // The list escapes neither the text nor the value
            $options[] = HTMLHelper::_(
                'select.option',
                htmlspecialchars($context, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($registry->describe($context)->type, ENT_QUOTES, 'UTF-8')
            );
        }

        return array_merge(parent::getOptions(), $options);
    }
}
