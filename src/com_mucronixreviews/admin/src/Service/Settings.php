<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  com_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Mucronix\Component\MucronixReviews\Administrator\Service;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The options of the component, with their defaults: until the options are saved for the first time, the database
 * holds none of them. A value that cannot be becomes the default.
 *
 * @since  0.1.0
 */
final class Settings
{
    /**
     * The longest text: the column holds 65535 bytes, a character takes up to four.
     *
     * @var    integer
     * @since  0.1.0
     */
    public const TEXT_LIMIT = 16000;

    /**
     * Constructor
     *
     * @param   array  $params  The saved options
     *
     * @since   0.1.0
     */
    public function __construct(private readonly array $params)
    {
    }

    /**
     * Whether a review has more than the stars.
     *
     * @return  boolean
     *
     * @since   0.1.0
     */
    public function hasContent(): bool
    {
        return $this->choice('mode', ['full', 'stars']) === 'full';
    }

    /**
     * The text: off, optional or required.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public function textMode(): string
    {
        return $this->hasContent() ? $this->choice('text_mode', ['optional', 'off', 'required']) : 'off';
    }

    /**
     * The shortest text, when the text is required.
     *
     * @return  integer
     *
     * @since   0.1.0
     */
    public function textMin(): int
    {
        return $this->number('text_min', 20, 0);
    }

    /**
     * The longest text.
     *
     * @return  integer
     *
     * @since   0.1.0
     */
    public function textMax(): int
    {
        return min($this->number('text_max', 3000, 1), self::TEXT_LIMIT);
    }

    /**
     * The groups that may rate. Nobody, if the saved value holds no group.
     *
     * @return  integer[]
     *
     * @since   0.1.0
     */
    public function allowedGroups(): array
    {
        $groups = array_map('intval', (array) ($this->params['allowed_groups'] ?? [2]));

        return array_values(array_filter($groups, static fn (int $group): bool => $group > 0));
    }

    /**
     * Whether a user may change their review.
     *
     * @return  boolean
     *
     * @since   0.1.0
     */
    public function allowEdit(): bool
    {
        return (bool) (int) ($this->params['allow_edit'] ?? 1);
    }

    /**
     * Whether the reviews go together with the account of their author.
     *
     * @return  boolean
     *
     * @since   0.1.0
     */
    public function deleteWithUser(): bool
    {
        return $this->choice('user_delete', ['keep', 'delete']) === 'delete';
    }

    /**
     * What waits for a moderator: all, text or none.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public function moderation(): string
    {
        return $this->choice('moderation', ['text', 'all', 'none']);
    }

    /**
     * What happens to a review with a link: allow, moderate or forbid.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public function links(): string
    {
        return $this->choice('links', ['moderate', 'allow', 'forbid']);
    }

    /**
     * The most addresses of "Additional Addresses"
     *
     * @var    integer
     * @since  0.6.0
     */
    public const NOTIFY_EMAILS_MAX = 10;

    /**
     * Whether the moderators get an email about a review that waits for them.
     *
     * @return  boolean
     *
     * @since   0.6.0
     */
    public function notify(): bool
    {
        return (bool) (int) ($this->params['notify'] ?? 1);
    }

    /**
     * The groups whose moderators get the email. Never saved: Super Users of a new site.
     *
     * @return  integer[]
     *
     * @since   0.6.0
     */
    public function notifyGroups(): array
    {
        $groups = array_map('intval', (array) ($this->params['notify_groups'] ?? [8]));

        return array_values(array_unique(array_filter($groups, static fn (int $group): bool => $group > 0)));
    }

    /**
     * The addresses that get the email besides the groups: each once, the first ten. The rule of the options has
     * checked them, the email checks them again.
     *
     * @return  string[]
     *
     * @since   0.6.0
     */
    public function notifyEmails(): array
    {
        $emails = [];

        foreach (self::emailList((string) ($this->params['notify_emails'] ?? '')) as $email) {
            $emails[strtolower($email)] ??= $email;
        }

        return \array_slice(array_values($emails), 0, self::NOTIFY_EMAILS_MAX);
    }

    /**
     * How many emails about reviews go out in an hour at most, 0 for any number.
     *
     * @return  integer
     *
     * @since   0.6.0
     */
    public function notifyHourly(): int
    {
        return $this->number('notify_hourly', 20, 0);
    }

    /**
     * The addresses of a list, as they are written: separated by commas, semicolons, spaces or lines.
     *
     * @param   string  $list  The list
     *
     * @return  string[]
     *
     * @since   0.6.0
     */
    public static function emailList(string $list): array
    {
        return array_values(array_filter(preg_split('~[\s,;]+~u', trim($list)) ?: [], static fn (string $email): bool => $email !== ''));
    }

    /**
     * How many new reviews a user may leave in 24 hours, 0 for any number.
     *
     * @return  integer
     *
     * @since   0.1.0
     */
    public function dailyLimit(): int
    {
        return $this->number('daily_limit', 10, 0);
    }

    /**
     * How the stars are rounded: half or whole.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public function rounding(): string
    {
        return $this->choice('rounding', ['half', 'whole']);
    }

    /**
     * Whether the date of a review is shown.
     *
     * @return  boolean
     *
     * @since   0.1.0
     */
    public function showDate(): bool
    {
        return (bool) (int) ($this->params['show_date'] ?? 1);
    }

    /**
     * The format of the date: a format or the language string that holds it.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public function dateFormat(): string
    {
        $format = $this->params['date_format'] ?? '';

        return \is_string($format) && trim($format) !== '' ? trim($format) : 'DATE_FORMAT_LC3';
    }

    /**
     * How many reviews a page shows.
     *
     * @return  integer
     *
     * @since   0.1.0
     */
    public function listLimit(): int
    {
        return $this->number('list_limit', 10, 1);
    }

    /**
     * The default order: newest, highest or lowest.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public function listOrder(): string
    {
        return $this->choice('list_order', ['newest', 'highest', 'lowest']);
    }

    /**
     * How the buttons and the fields look: auto (the classes of UIkit on a template of YOOtheme Pro) or own.
     *
     * @return  string
     *
     * @since   0.5.0
     */
    public function controlStyle(): string
    {
        return $this->choice('control_style', ['auto', 'own']);
    }

    /**
     * The categories of articles whose page shows the block by itself.
     *
     * @return  integer[]
     *
     * @since   0.3.1
     */
    public function articleCategories(): array
    {
        $categories = array_map('intval', (array) ($this->params['article_categories'] ?? []));

        return array_values(array_filter($categories, static fn (int $category): bool => $category > 0));
    }

    /**
     * Where that block stands: before or after the text of the article.
     *
     * @return  string
     *
     * @since   0.3.1
     */
    public function articlePosition(): string
    {
        return $this->choice('article_position', ['after', 'before']);
    }

    /**
     * What that block shows: summary, list (the summary and the reviews) or full (with the form, too).
     *
     * @return  string
     *
     * @since   0.3.1
     */
    public function articleShow(): string
    {
        return $this->choice('article_show', ['full', 'summary', 'list']);
    }

    /**
     * The types of objects that get the Schema.org markup.
     *
     * @return  string[]
     *
     * @since   0.1.0
     */
    public function schemaContexts(): array
    {
        return array_values(array_filter((array) ($this->params['schema_contexts'] ?? []), 'is_string'));
    }

    /**
     * Whether the reviews stay when the extension is uninstalled.
     *
     * @return  boolean
     *
     * @since   0.1.0
     */
    public function keepData(): bool
    {
        return (bool) (int) ($this->params['keep_data'] ?? 1);
    }

    /**
     * Reads a choice.
     *
     * @param   string    $name     The option
     * @param   string[]  $choices  What it may be, the default first
     *
     * @return  string
     *
     * @since   0.1.0
     */
    private function choice(string $name, array $choices): string
    {
        $value = $this->params[$name] ?? null;

        return \in_array($value, $choices, true) ? $value : $choices[0];
    }

    /**
     * Reads a whole number.
     *
     * @param   string   $name     The option
     * @param   integer  $default  The default
     * @param   integer  $minimum  The smallest value that may be
     *
     * @return  integer
     *
     * @since   0.1.0
     */
    private function number(string $name, int $default, int $minimum): int
    {
        $value = filter_var($this->params[$name] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => $minimum]]);

        return $value === false ? $default : $value;
    }
}
