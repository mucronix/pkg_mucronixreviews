<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  com_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Mucronix\Component\MucronixReviews\Administrator\Service;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Session\Session;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Utilities\ArrayHelper;
use Mucronix\Component\MucronixReviews\Administrator\Event\AfterChangeEvent;
use Mucronix\Component\MucronixReviews\Administrator\Exception\ReviewException;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The reviews. Everything that reads or writes them goes through this class: the adapters, the plugins and the
 * administrator have no SQL of their own for the tables of the reviews.
 *
 * Every change and the recalculation after it are one transaction. The locks are always taken in one order, or two
 * requests could wait for each other: first the author, then the statistics sorted by type and id of the object.
 *
 * The rights of an administrator are checked by the caller: saveReview() is the only method for authors.
 *
 * @since  0.1.0
 */
class ReviewService
{
    /**
     * The seconds between two reviews sent by one user
     *
     * @var    integer
     * @since  0.1.0
     */
    private const FLOOD_SECONDS = 5;

    /**
     * The largest id the column holds
     *
     * @var    integer
     * @since  0.1.0
     */
    private const MAX_ID = 4294967295;

    /**
     * The longest text that is looked at, in bytes, as it was sent: tags included
     *
     * @var    integer
     * @since  0.1.0
     */
    private const MAX_BYTES = 131072;

    /**
     * The statistics
     *
     * @var    StatsTable
     * @since  0.1.0
     */
    private readonly StatsTable $stats;

    /**
     * Constructor
     *
     * @param   DatabaseInterface    $db          The database driver
     * @param   ContextRegistry      $contexts    The types of objects
     * @param   DispatcherInterface  $dispatcher  The event dispatcher
     *
     * @since   0.1.0
     */
    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly ContextRegistry $contexts,
        private readonly DispatcherInterface $dispatcher
    ) {
        $this->stats = new StatsTable($db);
    }

    /**
     * Returns the options. Joomla does not give those of a disabled component, and a user can be deleted meanwhile:
     * the defaults would keep the reviews somebody wants deleted.
     *
     * @return  Settings
     *
     * @since   0.1.0
     */
    public function getSettings(): Settings
    {
        if (ComponentHelper::isEnabled('com_mucronixreviews')) {
            return new Settings(ComponentHelper::getParams('com_mucronixreviews')->toArray());
        }

        $query = $this->db->createQuery()
            ->select($this->db->quoteName('params'))
            ->from($this->db->quoteName('#__extensions'))
            ->where($this->db->quoteName('type') . ' = ' . $this->db->quote('component'))
            ->where($this->db->quoteName('element') . ' = ' . $this->db->quote('com_mucronixreviews'));

        return new Settings((array) json_decode((string) $this->db->setQuery($query)->loadResult(), true));
    }

    /**
     * Whether the groups of a user may leave reviews. The groups count with those above them, as for access rights.
     *
     * @param   User  $user  The user
     *
     * @return  boolean
     *
     * @since   0.3.0
     */
    public function isAllowed(User $user): bool
    {
        return (bool) array_intersect($this->getSettings()->allowedGroups(), ArrayHelper::toInteger($user->getAuthorisedGroups()));
    }

    /**
     * Returns the stored statistics of an object.
     *
     * @param   string   $context  The type of the object
     * @param   integer  $itemId   The id of the object
     *
     * @return  object
     *
     * @since   0.1.0
     */
    public function getStats(string $context, int $itemId): object
    {
        return $this->stats->read($context, $itemId);
    }

    /**
     * Returns the published reviews of an object.
     *
     * @param   string   $context  The type of the object
     * @param   integer  $itemId   The id of the object
     * @param   array    $options  limit, offset, order (newest, highest, lowest), withTextOnly
     *
     * @return  object[]  The text is plain: it has to be escaped. The author is the username, of a deleted account the stored one.
     *
     * @since   0.1.0
     */
    public function getReviews(string $context, int $itemId, array $options = []): array
    {
        $settings = $this->getSettings();
        $state    = ModerationRules::PUBLISHED;
        $limit    = max(1, (int) ($options['limit'] ?? $settings->listLimit()));
        $offset   = max(0, (int) ($options['offset'] ?? 0));
        $orders   = ['newest' => '', 'highest' => 'r.rating DESC, ', 'lowest' => 'r.rating ASC, '];
        $order    = $orders[$options['order'] ?? ''] ?? $orders[$settings->listOrder()];

        $query = $this->db->createQuery()
            ->select($this->db->quoteName(['r.id', 'r.context', 'r.item_id', 'r.user_id', 'r.rating', 'r.text', 'r.created', 'r.modified']))
            ->select('COALESCE(' . $this->db->quoteName('u.username') . ', ' . $this->db->quoteName('r.username') . ') AS ' . $this->db->quoteName('author'))
            ->from($this->db->quoteName('#__mucronixreviews_reviews', 'r'))
            ->join('LEFT', $this->db->quoteName('#__users', 'u'), $this->db->quoteName('u.id') . ' = ' . $this->db->quoteName('r.user_id'))
            ->where($this->db->quoteName('r.context') . ' = :context')
            ->where($this->db->quoteName('r.item_id') . ' = :itemId')
            ->where($this->db->quoteName('r.state') . ' = :state')
            ->bind(':context', $context)
            ->bind(':itemId', $itemId, ParameterType::INTEGER)
            ->bind(':state', $state, ParameterType::INTEGER)
            ->order($order . 'r.created DESC, r.id DESC')
            ->setLimit($limit, $offset);

        if (!empty($options['withTextOnly'])) {
            $query->where($this->db->quoteName('r.text') . " <> ''");
        }

        return $this->db->setQuery($query)->loadObjectList();
    }

    /**
     * Returns the review a user has left, in any state.
     *
     * @param   string   $context  The type of the object
     * @param   integer  $itemId   The id of the object
     * @param   integer  $userId   The id of the user
     *
     * @return  object|null
     *
     * @since   0.1.0
     */
    public function getUserReview(string $context, int $itemId, int $userId): ?object
    {
        if ($userId < 1) {
            return null;
        }

        $query = $this->db->createQuery()
            ->select($this->db->quoteName(['id', 'rating', 'text', 'state', 'created', 'modified']))
            ->from($this->db->quoteName('#__mucronixreviews_reviews'))
            ->where($this->db->quoteName('context') . ' = :context')
            ->where($this->db->quoteName('item_id') . ' = :itemId')
            ->where($this->db->quoteName('user_id') . ' = :userId')
            ->bind(':context', $context)
            ->bind(':itemId', $itemId, ParameterType::INTEGER)
            ->bind(':userId', $userId, ParameterType::INTEGER);

        $review = $this->db->setQuery($query)->loadObject();

        if (!$review) {
            return null;
        }

        foreach (['id', 'rating', 'state'] as $column) {
            $review->$column = (int) $review->$column;
        }

        $review->text = (string) $review->text;

        return $review;
    }

    /**
     * Creates or changes the review of the user who is logged in. All the checks are here, so no caller can leave one out.
     *
     * @param   string          $context  The type of the object
     * @param   integer         $itemId   The id of the object
     * @param   integer         $userId   The id of the author: the user who is logged in
     * @param   integer|string  $rating   The stars, 1 to 5
     * @param   string          $text     The text as it was sent
     *
     * @return  SaveResult
     *
     * @throws  ReviewException  If the review is refused
     *
     * @since   0.1.0
     */
    public function saveReview(string $context, int $itemId, int $userId, int|string $rating, string $text = ''): SaveResult
    {
        $settings = $this->getSettings();
        $user     = $this->requireAuthor($userId);

        $this->requireToken();
        $this->requireObject($context, $itemId, $user);

        $rating = $this->checkRating($rating);
        $text   = $this->cleanText($text, $settings);

        if ($settings->links() === 'forbid' && LinkDetector::containsLink($text)) {
            throw new ReviewException('links', 422);
        }

        $result = $this->change(
            [$context],
            fn (): SaveResult => $this->store($settings, $context, $itemId, $userId, $rating, $text)
        );

        // After the transaction: the review is there whatever becomes of the email
        (new ModeratorMail($this->db, $this->contexts, $settings))->send($result, $context, $itemId, $userId, $rating);

        return $result;
    }

    /**
     * Changes the state of reviews.
     *
     * @param   integer[]  $ids    The ids of the reviews
     * @param   integer    $state  The new state
     *
     * @return  integer  How many reviews have changed
     *
     * @throws  \InvalidArgumentException  If there is no such state
     *
     * @since   0.1.0
     */
    public function setState(array $ids, int $state): int
    {
        if (!\in_array($state, ModerationRules::STATES, true)) {
            throw new \InvalidArgumentException('Not a state of a review: ' . $state);
        }

        $query = $this->db->createQuery()
            ->update($this->db->quoteName('#__mucronixreviews_reviews'))
            ->set($this->db->quoteName('state') . ' = :state')
            ->bind(':state', $state, ParameterType::INTEGER);

        return $this->changeByIds($ids, $query);
    }

    /**
     * Changes a review for an administrator. What an author has to comply with, the required fields, the shortest text
     * and the links, does not count here. The longest text is the one the table holds: a review from before the options
     * were changed can still be stored. The time of the last change stays that of the author, the pause counts from it.
     *
     * @param   integer         $id      The id of the review
     * @param   integer|string  $rating  The stars, 1 to 5
     * @param   string          $text    The text
     * @param   integer|null    $state   The state, null to leave it as it is
     *
     * @return  void
     *
     * @throws  ReviewException            If the review is refused
     * @throws  \InvalidArgumentException  If there is no such state
     *
     * @since   0.2.0
     */
    public function updateReview(int $id, int|string $rating, string $text, ?int $state = null): void
    {
        if ($state !== null && !\in_array($state, ModerationRules::STATES, true)) {
            throw new \InvalidArgumentException('Not a state of a review: ' . $state);
        }

        $rating = $this->checkRating($rating);
        $text   = $this->plainText($text, Settings::TEXT_LIMIT);

        $objects = $this->objects($this->whereId($this->db->createQuery(), $id));

        if (!$objects) {
            throw new ReviewException('not_found', 404);
        }

        $this->change(
            $this->contextsOf($objects),
            function () use ($id, $rating, $text, $state, $objects): void {
                $this->lockObjects($objects);

                $values = [
                    'rating' => [$rating, ParameterType::INTEGER],
                    'text'   => [$text === '' ? null : $text, $text === '' ? ParameterType::NULL : ParameterType::STRING],
                ];

                if ($state !== null) {
                    $values['state'] = [$state, ParameterType::INTEGER];
                }

                if (($username = $this->username($id)) !== null) {
                    $values['username'] = [$username, ParameterType::STRING];
                }

                $this->update($id, $values);
                $this->recalculateObjects($objects);
            }
        );
    }

    /**
     * Returns how many reviews wait for a moderator.
     *
     * @return  integer
     *
     * @since   0.2.0
     */
    public function countPending(): int
    {
        $state = ModerationRules::PENDING;

        $query = $this->db->createQuery()
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__mucronixreviews_reviews'))
            ->where($this->db->quoteName('state') . ' = :state')
            ->bind(':state', $state, ParameterType::INTEGER);

        return (int) $this->db->setQuery($query)->loadResult();
    }

    /**
     * Deletes those of the reviews that are in the trash.
     *
     * @param   integer[]  $ids  The ids of the reviews
     *
     * @return  integer  How many reviews are gone
     *
     * @since   0.2.0
     */
    public function deleteTrashed(array $ids): int
    {
        $state = ModerationRules::TRASHED;

        return $this->changeByIds(
            $ids,
            $this->db->createQuery()
                ->delete($this->db->quoteName('#__mucronixreviews_reviews'))
                ->where($this->db->quoteName('state') . ' = :state')
                ->bind(':state', $state, ParameterType::INTEGER)
        );
    }

    /**
     * Deletes reviews.
     *
     * @param   integer[]  $ids  The ids of the reviews
     *
     * @return  integer  How many reviews are gone
     *
     * @since   0.1.0
     */
    public function deleteReviews(array $ids): int
    {
        return $this->changeByIds($ids, $this->db->createQuery()->delete($this->db->quoteName('#__mucronixreviews_reviews')));
    }

    /**
     * Deletes the reviews and the statistics of an object.
     *
     * @param   string   $context  The type of the object
     * @param   integer  $itemId   The id of the object
     *
     * @return  integer  How many reviews are gone
     *
     * @since   0.1.0
     */
    public function deleteByItem(string $context, int $itemId): int
    {
        return $this->change(
            [$context],
            function () use ($context, $itemId): int {
                $this->stats->lock($context, $itemId);

                $query = $this->db->createQuery()
                    ->delete($this->db->quoteName('#__mucronixreviews_reviews'))
                    ->where($this->db->quoteName('context') . ' = :context')
                    ->where($this->db->quoteName('item_id') . ' = :itemId')
                    ->bind(':context', $context)
                    ->bind(':itemId', $itemId, ParameterType::INTEGER);

                $this->db->setQuery($query)->execute();

                $deleted = $this->db->getAffectedRows();

                $this->stats->delete($context, $itemId);

                return $deleted;
            }
        );
    }

    /**
     * Deals with the reviews of a deleted account: as the options say, they stay under the stored username or go.
     *
     * @param   integer  $userId  The id of the deleted user
     *
     * @return  integer  How many reviews were concerned
     *
     * @since   0.1.0
     */
    public function handleUserDelete(int $userId): int
    {
        if ($userId < 1) {
            return 0;
        }

        $delete  = $this->getSettings()->deleteWithUser();
        $touched = $this->objects($this->whereUser($this->db->createQuery(), $userId));

        // The statistics stay as they are when the reviews stay
        $objects = $delete ? $touched : [];

        return $this->change(
            $this->contextsOf($touched),
            function () use ($userId, $delete, $objects): int {
                $this->lockObjects($objects);

                $query = $this->db->createQuery();

                if ($delete) {
                    $query->delete($this->db->quoteName('#__mucronixreviews_reviews'));
                } else {
                    $query->update($this->db->quoteName('#__mucronixreviews_reviews'))
                        ->set($this->db->quoteName('user_id') . ' = NULL');
                }

                $this->db->setQuery($this->whereUser($query, $userId))->execute();

                $changed = $this->db->getAffectedRows();

                $this->recalculateObjects($objects);

                return $changed;
            }
        );
    }

    /**
     * Counts the statistics of an object anew.
     *
     * @param   string   $context  The type of the object
     * @param   integer  $itemId   The id of the object
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function recalculate(string $context, int $itemId): void
    {
        $this->change(
            [$context],
            function () use ($context, $itemId): void {
                $this->stats->lock($context, $itemId);
                $this->stats->recalculate($context, $itemId);
            }
        );
    }

    /**
     * Counts the statistics of all objects anew. Statistics of objects without reviews go.
     *
     * @return  integer  How many objects have reviews
     *
     * @since   0.1.0
     */
    public function recalculateAll(): int
    {
        $reviewed = $this->objects($this->db->createQuery());

        foreach ($reviewed as $object) {
            $this->recalculate($object->context, (int) $object->item_id);
        }

        $query = $this->db->createQuery()
            ->select($this->db->quoteName(['s.context', 's.item_id']))
            ->from($this->db->quoteName('#__mucronixreviews_stats', 's'))
            ->join(
                'LEFT',
                $this->db->quoteName('#__mucronixreviews_reviews', 'r'),
                $this->db->quoteName('r.context') . ' = ' . $this->db->quoteName('s.context')
                . ' AND ' . $this->db->quoteName('r.item_id') . ' = ' . $this->db->quoteName('s.item_id')
            )
            ->where($this->db->quoteName('r.id') . ' IS NULL');

        foreach ($this->db->setQuery($query)->loadObjectList() as $object) {
            $this->stats->delete($object->context, (int) $object->item_id);
        }

        return \count($reviewed);
    }

    /**
     * Checks that the author is the user who is logged in and that the user may rate.
     *
     * @param   integer  $userId  The id of the author
     *
     * @return  User
     *
     * @throws  ReviewException
     *
     * @since   0.1.0
     */
    private function requireAuthor(int $userId): User
    {
        $user = Factory::getApplication()->getIdentity();

        if (!$user || $user->guest || $user->block || $userId < 1 || (int) $user->id !== $userId) {
            throw new ReviewException('login', 401);
        }

        if (!$this->isAllowed($user)) {
            throw new ReviewException('group', 403);
        }

        return $user;
    }

    /**
     * Checks the token of the session, in the header or in the form.
     * Not Session::checkToken(): it answers a new session with a redirect to the home page.
     *
     * @return  void
     *
     * @throws  ReviewException
     *
     * @since   0.1.0
     */
    private function requireToken(): void
    {
        $token = Session::getFormToken();
        $input = Factory::getApplication()->getInput();

        if (
            !hash_equals($token, (string) $input->server->get('HTTP_X_CSRF_TOKEN', '', 'alnum'))
            && $input->post->get($token, '', 'raw') !== '1'
        ) {
            throw new ReviewException('token', 403);
        }
    }

    /**
     * Checks that the object can be reviewed by the user.
     *
     * @param   string   $context  The type of the object
     * @param   integer  $itemId   The id of the object
     * @param   User     $user     The user
     *
     * @return  void
     *
     * @throws  ReviewException
     *
     * @since   0.1.0
     */
    private function requireObject(string $context, int $itemId, User $user): void
    {
        $handler = $this->contexts->getHandler($context);

        if ($handler === null) {
            throw new ReviewException('context', 404);
        }

        if ($itemId < 1 || $itemId > self::MAX_ID || !$handler->isAvailable($itemId, $user)) {
            throw new ReviewException('unavailable', 404);
        }
    }

    /**
     * Checks the stars.
     *
     * @param   integer|string  $rating  The stars as they were sent
     *
     * @return  integer
     *
     * @throws  ReviewException
     *
     * @since   0.2.0
     */
    private function checkRating(int|string $rating): int
    {
        if (!preg_match('~^[1-5]$~D', (string) $rating)) {
            throw new ReviewException('rating', 422);
        }

        return (int) $rating;
    }

    /**
     * Cleans a text and checks its length.
     *
     * @param   string   $text     The text as it was sent
     * @param   integer  $maximum  The longest text
     *
     * @return  string
     *
     * @throws  ReviewException
     *
     * @since   0.2.0
     */
    private function plainText(string $text, int $maximum): string
    {
        $text = \strlen($text) > self::MAX_BYTES ? null : TextCleaner::text($text);

        if ($text === null || mb_strlen($text) > $maximum) {
            throw new ReviewException('text_long', 422, [$maximum]);
        }

        return $text;
    }

    /**
     * Cleans and checks the text.
     *
     * @param   string    $text      The text as it was sent
     * @param   Settings  $settings  The options
     *
     * @return  string  Empty if texts are off
     *
     * @throws  ReviewException
     *
     * @since   0.1.0
     */
    private function cleanText(string $text, Settings $settings): string
    {
        if ($settings->textMode() === 'off') {
            return '';
        }

        $text   = $this->plainText($text, $settings->textMax());
        $length = mb_strlen($text);

        if ($settings->textMode() === 'required') {
            if ($length === 0) {
                throw new ReviewException('text_required', 422);
            }

            if ($length < $settings->textMin()) {
                throw new ReviewException('text_short', 422, [$settings->textMin()]);
            }
        }

        return $text;
    }

    /**
     * Writes the review. Runs in a transaction.
     *
     * @param   Settings  $settings  The options
     * @param   string    $context   The type of the object
     * @param   integer   $itemId    The id of the object
     * @param   integer   $userId    The id of the author
     * @param   integer   $rating    The stars
     * @param   string    $text      The clean text
     *
     * @return  SaveResult
     *
     * @throws  ReviewException
     *
     * @since   0.1.0
     */
    private function store(Settings $settings, string $context, int $itemId, int $userId, int $rating, string $text): SaveResult
    {
        $username = $this->lockAuthor($userId);

        $this->stats->lock($context, $itemId);

        $now      = Factory::getDate();
        $existing = $this->getUserReview($context, $itemId, $userId);

        if ($existing && !$settings->allowEdit()) {
            throw new ReviewException('edit', 403);
        }

        $this->requirePause($userId, $now->toUnix());

        if (!$existing) {
            $this->requireLimit($userId, $now->toUnix(), $settings);
        }

        // A field that is off does not touch what was written while it was on
        if ($existing && $settings->textMode() === 'off') {
            $text = $existing->text;
        }

        $hasLinks = LinkDetector::containsLink($text);

        if ($existing) {
            $changed = $text !== $existing->text;
            $state   = ModerationRules::stateOfEdited($settings, $existing->state, $changed, $hasLinks);
        } else {
            $state = ModerationRules::stateOfNew($settings, $text !== '', $hasLinks);
        }

        $values = [
            'username' => [$username, ParameterType::STRING],
            'rating'   => [$rating, ParameterType::INTEGER],
            'text'     => [$text === '' ? null : $text, $text === '' ? ParameterType::NULL : ParameterType::STRING],
            'state'    => [$state, ParameterType::INTEGER],
        ];

        if ($existing) {
            $id = $existing->id;

            $this->update($id, $values + ['modified' => [$now->toSql(), ParameterType::STRING]]);
        } else {
            $id = $this->insert(
                $values + [
                    'context' => [$context, ParameterType::STRING],
                    'item_id' => [$itemId, ParameterType::INTEGER],
                    'user_id' => [$userId, ParameterType::INTEGER],
                    'created' => [$now->toSql(), ParameterType::STRING],
                ]
            );
        }

        $this->stats->recalculate($context, $itemId);

        return new SaveResult($id, $state, !$existing, $existing ? (int) $existing->state : null, $hasLinks);
    }

    /**
     * Locks the account of the author until the end of the transaction: two requests of one user come one after
     * another, so neither the limit nor the pause can be outrun, and an account deleted meanwhile leaves no review.
     * The query builder has no FOR UPDATE for MySQL.
     *
     * @param   integer  $userId  The id of the author
     *
     * @return  string  The username
     *
     * @throws  ReviewException  If the account is gone or blocked
     *
     * @since   0.1.0
     */
    private function lockAuthor(int $userId): string
    {
        $query = $this->db->createQuery()
            ->setQuery(
                'SELECT ' . $this->db->quoteName('username') . ' FROM ' . $this->db->quoteName('#__users')
                . ' WHERE ' . $this->db->quoteName('id') . ' = :userId AND ' . $this->db->quoteName('block') . ' = 0 FOR UPDATE'
            )
            ->bind(':userId', $userId, ParameterType::INTEGER);

        $username = $this->db->setQuery($query)->loadResult();

        if ($username === null) {
            throw new ReviewException('login', 401);
        }

        return $username;
    }

    /**
     * Checks the pause since the last review the user has sent.
     *
     * @param   integer  $userId  The id of the author
     * @param   integer  $now     The time
     *
     * @return  void
     *
     * @throws  ReviewException
     *
     * @since   0.1.0
     */
    private function requirePause(int $userId, int $now): void
    {
        $query = $this->db->createQuery()
            ->select('MAX(COALESCE(' . $this->db->quoteName('modified') . ', ' . $this->db->quoteName('created') . '))')
            ->from($this->db->quoteName('#__mucronixreviews_reviews'));

        $last = $this->db->setQuery($this->whereUser($query, $userId))->loadResult();

        if ($last !== null && $now - Factory::getDate($last)->toUnix() < self::FLOOD_SECONDS) {
            throw new ReviewException('flood', 429);
        }
    }

    /**
     * Checks the limit of new reviews in the last 24 hours.
     *
     * @param   integer   $userId    The id of the author
     * @param   integer   $now       The time
     * @param   Settings  $settings  The options
     *
     * @return  void
     *
     * @throws  ReviewException
     *
     * @since   0.1.0
     */
    private function requireLimit(int $userId, int $now, Settings $settings): void
    {
        $limit = $settings->dailyLimit();

        if ($limit === 0) {
            return;
        }

        $since = Factory::getDate($now - 86400)->toSql();

        $query = $this->db->createQuery()
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__mucronixreviews_reviews'))
            ->where($this->db->quoteName('created') . ' > :since')
            ->bind(':since', $since);

        if ((int) $this->db->setQuery($this->whereUser($query, $userId))->loadResult() >= $limit) {
            throw new ReviewException('limit', 429);
        }
    }

    /**
     * Inserts a review.
     *
     * @param   array  $values  The columns, each with its value and type
     *
     * @return  integer  The id
     *
     * @since   0.1.0
     */
    private function insert(array $values): int
    {
        $columns = array_keys($values);

        $query = $this->db->createQuery()
            ->insert($this->db->quoteName('#__mucronixreviews_reviews'))
            ->columns($this->db->quoteName($columns))
            ->values(':' . implode(', :', $columns));

        foreach ($columns as $column) {
            $query->bind(':' . $column, $values[$column][0], $values[$column][1]);
        }

        $this->db->setQuery($query)->execute();

        return (int) $this->db->insertid();
    }

    /**
     * Updates a review.
     *
     * @param   integer  $id      The id
     * @param   array    $values  The columns, each with its value and type
     *
     * @return  void
     *
     * @since   0.1.0
     */
    private function update(int $id, array $values): void
    {
        $query = $this->db->createQuery()
            ->update($this->db->quoteName('#__mucronixreviews_reviews'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        foreach (array_keys($values) as $column) {
            $query->set($this->db->quoteName($column) . ' = :' . $column)
                ->bind(':' . $column, $values[$column][0], $values[$column][1]);
        }

        $this->db->setQuery($query)->execute();
    }

    /**
     * Changes reviews by their ids and recalculates their objects.
     *
     * @param   integer[]       $ids    The ids
     * @param   QueryInterface  $query  The change, without its conditions
     *
     * @return  integer  How many reviews have changed
     *
     * @since   0.1.0
     */
    private function changeByIds(array $ids, QueryInterface $query): int
    {
        $ids = array_values(array_unique(array_filter(ArrayHelper::toInteger($ids), static fn (int $id): bool => $id > 0)));

        if (!$ids) {
            return 0;
        }

        $objects = $this->objects($this->db->createQuery()->whereIn($this->db->quoteName('id'), $ids));

        return $this->change(
            $this->contextsOf($objects),
            function () use ($ids, $query, $objects): int {
                $this->lockObjects($objects);

                $this->db->setQuery($query->whereIn($this->db->quoteName('id'), $ids))->execute();

                $changed = $this->db->getAffectedRows();

                $this->recalculateObjects($objects);

                return $changed;
            }
        );
    }

    /**
     * Returns the objects of reviews, in the order of the locks.
     *
     * @param   QueryInterface  $query  The conditions of the reviews
     *
     * @return  object[]
     *
     * @since   0.1.0
     */
    private function objects(QueryInterface $query): array
    {
        $query->select('DISTINCT ' . implode(', ', $this->db->quoteName(['context', 'item_id'])))
            ->from($this->db->quoteName('#__mucronixreviews_reviews'))
            ->order($this->db->quoteName(['context', 'item_id']));

        return $this->db->setQuery($query)->loadObjectList();
    }

    /**
     * Locks the statistics of objects.
     *
     * @param   object[]  $objects  The objects
     *
     * @return  void
     *
     * @since   0.1.0
     */
    private function lockObjects(array $objects): void
    {
        foreach ($objects as $object) {
            $this->stats->lock($object->context, (int) $object->item_id);
        }
    }

    /**
     * Recalculates the statistics of locked objects.
     *
     * @param   object[]  $objects  The objects
     *
     * @return  void
     *
     * @since   0.1.0
     */
    private function recalculateObjects(array $objects): void
    {
        foreach ($objects as $object) {
            $this->stats->recalculate($object->context, (int) $object->item_id);
        }
    }

    /**
     * Reads the present username of the author of a review.
     *
     * @param   integer  $id  The id of the review
     *
     * @return  string|null  Null if the account is deleted
     *
     * @since   0.2.0
     */
    private function username(int $id): ?string
    {
        $query = $this->db->createQuery()
            ->select($this->db->quoteName('u.username'))
            ->from($this->db->quoteName('#__mucronixreviews_reviews', 'r'))
            ->join('INNER', $this->db->quoteName('#__users', 'u'), $this->db->quoteName('u.id') . ' = ' . $this->db->quoteName('r.user_id'))
            ->where($this->db->quoteName('r.id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        return $this->db->setQuery($query)->loadResult();
    }

    /**
     * Adds the condition on the id of the review to a query.
     *
     * @param   QueryInterface  $query  The query
     * @param   integer         $id     The id of the review
     *
     * @return  QueryInterface
     *
     * @since   0.2.0
     */
    private function whereId(QueryInterface $query, int $id): QueryInterface
    {
        return $query->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);
    }

    /**
     * Adds the condition on the author to a query.
     *
     * @param   QueryInterface  $query   The query
     * @param   integer         $userId  The id of the author
     *
     * @return  QueryInterface
     *
     * @since   0.1.0
     */
    private function whereUser(QueryInterface $query, int $userId): QueryInterface
    {
        return $query->where($this->db->quoteName('user_id') . ' = :userId')
            ->bind(':userId', $userId, ParameterType::INTEGER);
    }

    /**
     * Returns the types of objects.
     *
     * @param   object[]  $objects  The objects
     *
     * @return  string[]
     *
     * @since   0.3.0
     */
    private function contextsOf(array $objects): array
    {
        return array_values(array_unique(array_column($objects, 'context')));
    }

    /**
     * Runs a change as one transaction and tells that it is done.
     *
     * @param   string[]  $contexts  The types of the objects that are concerned
     * @param   callable  $work      The change
     *
     * @return  mixed  What the change returns
     *
     * @throws  \Throwable  What the change throws, after everything is undone
     *
     * @since   0.3.0
     */
    private function change(array $contexts, callable $work): mixed
    {
        $this->db->transactionStart(true);

        try {
            $result = $work();

            $this->db->transactionCommit(true);
        } catch (\Throwable $e) {
            $this->db->transactionRollback(true);

            throw $e;
        }

        if ($contexts) {
            try {
                // This also loads the plugins that want to know
                $this->contexts->getHandlers();
                $this->dispatcher->dispatch(AfterChangeEvent::NAME, new AfterChangeEvent($contexts));
            } catch (\Throwable $e) {
                // The change is stored, whatever becomes of a cache
                Log::add('After a change: ' . $e->getMessage(), Log::ERROR, 'com_mucronixreviews');
            }
        }

        return $result;
    }
}
