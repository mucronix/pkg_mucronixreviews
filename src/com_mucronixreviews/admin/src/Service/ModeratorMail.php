<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  com_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Mucronix\Component\MucronixReviews\Administrator\Service;

use Joomla\CMS\Access\Access;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\CMS\Language\LanguageFactoryInterface;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Mail\Exception\MailDisabledException;
use Joomla\CMS\Mail\MailHelper;
use Joomla\CMS\Mail\MailTemplate;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The email to the moderators about a review that has come to wait for them. It never stops the review: a failure is
 * written to the log of Joomla and nothing else.
 *
 * @since  0.6.0
 */
final class ModeratorMail
{
    /**
     * The email about one review
     *
     * @var    string
     * @since  0.6.0
     */
    public const TEMPLATE = 'com_mucronixreviews.moderation';

    /**
     * The email once the limit of an hour is reached
     *
     * @var    string
     * @since  0.6.0
     */
    public const TEMPLATE_MORE = 'com_mucronixreviews.moderation_more';

    /**
     * How much of the text the email shows, in characters
     *
     * @var    integer
     * @since  0.6.0
     */
    public const EXCERPT = 300;

    /**
     * Constructor
     *
     * @param   DatabaseInterface  $db        The database driver
     * @param   ContextRegistry    $contexts  The types of objects
     * @param   Settings           $settings  The options
     *
     * @since   0.6.0
     */
    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly ContextRegistry $contexts,
        private readonly Settings $settings
    ) {
    }

    /**
     * Tells the moderators about a review that has just come to wait for them.
     *
     * @param   SaveResult  $result   What has become of the review
     * @param   string      $context  The type of the object
     * @param   integer     $itemId   The object
     * @param   integer     $userId   The author
     * @param   integer     $rating   The rating
     *
     * @return  integer  How many emails went out
     *
     * @since   0.6.0
     */
    public function send(SaveResult $result, string $context, int $itemId, int $userId, int $rating): int
    {
        if (!$this->settings->notify() || !$result->needsModerator()) {
            return 0;
        }

        $app = Factory::getApplication();

        try {
            $recipients = $this->recipients($userId);

            if (!$recipients) {
                return 0;
            }

            $tag      = (string) ComponentHelper::getParams('com_languages')->get('administrator', 'en-GB');
            $language = $this->language($tag);
            $template = self::TEMPLATE;
            $waiting  = $this->waitingThisHour();
            $limit    = $this->settings->notifyHourly();

            if ($limit > 0 && $waiting > $limit + 1) {
                return 0;
            }

            if ($limit > 0 && $waiting === $limit + 1) {
                $template = self::TEMPLATE_MORE;
            }

            $data = $template === self::TEMPLATE ? $this->reviewData($language, $result, $context, $itemId, $userId, $rating) : ['count' => (string) $limit];

            $data['sitename'] = (string) $app->get('sitename');
            $data['list_url'] = Uri::root() . 'administrator/index.php?option=com_mucronixreviews&view=reviews&filter[state]=0';
        } catch (\Throwable $e) {
            Log::add('Email to the moderators: ' . $e->getMessage(), Log::ERROR, 'com_mucronixreviews');

            return 0;
        }

        $sent = 0;

        // One email to each address: the moderators do not see one another's address
        foreach ($recipients as $email => $name) {
            try {
                $mail = new MailTemplate($template, $tag);
                $mail->addRecipient($email, $name);
                $mail->addUnsafeTags(['sitename', 'reason', 'object', 'author', 'rating', 'text', 'count']);
                $mail->addTemplateData($data);

                if ($mail->send()) {
                    $sent++;
                }
            } catch (MailDisabledException) {
                // "Send Mail" is off in the Global Configuration: the site sends no email at all, that is no failure
                return $sent;
            } catch (\Throwable $e) {
                Log::add('Email to the moderators, ' . $email . ': ' . $e->getMessage(), Log::ERROR, 'com_mucronixreviews');
            }
        }

        return $sent;
    }

    /**
     * Who gets the email: the moderators of the chosen groups who receive system emails, and the additional addresses.
     * Never the author.
     *
     * @param   integer  $authorId  The author of the review
     *
     * @return  string[]  The name by the address, each address once
     *
     * @since   0.6.0
     */
    public function recipients(int $authorId): array
    {
        $users  = Factory::getContainer()->get(UserFactoryInterface::class);
        $author = strtolower((string) $users->loadUserById($authorId)->email);
        $found  = [];

        $ids = [];

        foreach ($this->settings->notifyGroups() as $group) {
            $ids = array_merge($ids, Access::getUsersByGroup($group, true));
        }

        foreach (array_unique(array_map('intval', $ids)) as $id) {
            $user = $users->loadUserById($id);

            if ($this->isModerator($user)) {
                $found[strtolower($user->email)] ??= [$user->email, (string) $user->name];
            }
        }

        foreach ($this->settings->notifyEmails() as $email) {
            if (MailHelper::isEmailAddress($email)) {
                $found[strtolower($email)] ??= [$email, ''];
            }
        }

        unset($found[$author]);

        return array_column(array_values($found), 1, 0);
    }

    /**
     * Whether a member of a chosen group gets the email: an account that is not blocked, receives system emails and
     * may moderate the reviews.
     *
     * @param   User  $user  The user
     *
     * @return  boolean
     *
     * @since   0.6.0
     */
    private function isModerator(User $user): bool
    {
        return $user->id > 0
            && !$user->block
            && (int) $user->sendEmail === 1
            && MailHelper::isEmailAddress((string) $user->email)
            && $user->authorise('core.manage', 'com_mucronixreviews')
            && $user->authorise('core.edit.state', 'com_mucronixreviews');
    }

    /**
     * How many reviews have come to wait for a moderator in the last hour and wait still, this one counted.
     *
     * @return  integer
     *
     * @since   0.6.0
     */
    private function waitingThisHour(): int
    {
        $since   = Factory::getDate('-1 hour')->toSql();
        $pending = ModerationRules::PENDING;
        $query   = $this->db->createQuery()
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__mucronixreviews_reviews'))
            ->where($this->db->quoteName('state') . ' = :state')
            ->where('COALESCE(' . $this->db->quoteName('modified') . ', ' . $this->db->quoteName('created') . ') >= :since')
            ->bind(':state', $pending, ParameterType::INTEGER)
            ->bind(':since', $since);

        return (int) $this->db->setQuery($query)->loadResult();
    }

    /**
     * The tags of the email about one review. Plain text: MailTemplate escapes the unsafe ones in an HTML email.
     *
     * @param   Language    $language  The language of the email
     * @param   SaveResult  $result   What has become of the review
     * @param   string      $context  The type of the object
     * @param   integer     $itemId   The object
     * @param   integer     $userId   The author
     * @param   integer     $rating   The rating
     *
     * @return  array
     *
     * @since   0.6.0
     */
    private function reviewData(Language $language, SaveResult $result, string $context, int $itemId, int $userId, int $rating): array
    {
        $object   = $this->contexts->describe($context, $itemId);
        $url      = $object->url;

        // An address of the site without the host
        if (str_starts_with($url, '/')) {
            $url = Uri::getInstance(Uri::root())->toString(['scheme', 'host', 'port']) . $url;
        }

        $reason = $language->_($result->created ? 'COM_MUCRONIXREVIEWS_MAIL_REASON_NEW' : 'COM_MUCRONIXREVIEWS_MAIL_REASON_CHANGED');

        if ($result->hasLinks) {
            $reason .= ' ' . $language->_('COM_MUCRONIXREVIEWS_MAIL_REASON_LINKS');
        }

        // What is stored: with the field of the text off, an edit keeps the earlier text
        $id    = $result->id;
        $query = $this->db->createQuery()
            ->select($this->db->quoteName('text'))
            ->from($this->db->quoteName('#__mucronixreviews_reviews'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);
        $text  = (string) $this->db->setQuery($query)->loadResult();

        $excerpt = $text === '' ? $language->_('COM_MUCRONIXREVIEWS_MAIL_NO_TEXT') : self::excerpt($text);

        return [
            'reason'     => $reason,
            'object'     => (string) $object->title,
            'object_url' => $url,
            'author'     => (string) $this->username($userId),
            'rating'     => \sprintf($language->_('COM_MUCRONIXREVIEWS_N_OF_FIVE'), $rating),
            'text'       => $excerpt,
            'review_url' => Uri::root() . 'administrator/index.php?option=com_mucronixreviews&task=review.edit&id=' . $result->id,
        ];
    }

    /**
     * The language of the email with the strings of the administrator of the component. MailTemplate takes the current
     * language as it is when the tag is the same, and the language of the factory of Joomla otherwise: the factory keeps
     * one for each tag, so the strings loaded here are in it for every address.
     *
     * @param   string  $tag  The language of the email
     *
     * @return  Language
     *
     * @since   0.6.0
     */
    private function language(string $tag): Language
    {
        $current = Factory::getApplication()->getLanguage();

        $language = $current->getTag() === $tag ? $current : Factory::getContainer()->get(LanguageFactoryInterface::class)->createLanguage($tag, (bool) Factory::getApplication()->get('debug_lang'));
        $language->load('com_mucronixreviews', JPATH_ADMINISTRATOR, $tag, true);

        return $language;
    }

    /**
     * The beginning of a text, on one line.
     *
     * @param   string  $text  The text
     *
     * @return  string
     *
     * @since   0.6.0
     */
    public static function excerpt(string $text): string
    {
        $text = trim((string) preg_replace('~\s+~u', ' ', $text));

        return mb_strlen($text) > self::EXCERPT ? rtrim(mb_substr($text, 0, self::EXCERPT)) . '…' : $text;
    }

    /**
     * The username stored with the reviews of a user.
     *
     * @param   integer  $userId  The user
     *
     * @return  string
     *
     * @since   0.6.0
     */
    private function username(int $userId): string
    {
        $query = $this->db->createQuery()
            ->select($this->db->quoteName('username'))
            ->from($this->db->quoteName('#__users'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $userId, ParameterType::INTEGER);

        return (string) $this->db->setQuery($query)->loadResult();
    }
}
