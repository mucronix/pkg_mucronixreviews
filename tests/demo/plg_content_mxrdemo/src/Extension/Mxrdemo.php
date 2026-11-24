<?php

namespace Mucronix\Plugin\Content\Mxrdemo\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

/**
 * Test reviews for a check of the administrator by hand. Not part of the package.
 */
final class Mxrdemo extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    private const CONTEXT = 'com_mxrdemo.item';

    public static function getSubscribedEvents(): array
    {
        return ['onMucronixReviewsRegisterContexts' => 'register', 'onAjaxMxrdemo' => 'buttons'];
    }

    public function register(Event $event): void
    {
        $event->addHandler(new DemoContext());
    }

    public function buttons(Event $event): void
    {
        $app = $this->getApplication();

        if (!$app->isClient('administrator') || !$app->getIdentity()->authorise('core.admin') || !Session::checkToken('get')) {
            throw new NotAllowed('Нет прав', 403);
        }

        $service = $app->bootComponent('com_mucronixreviews')->getReviewService();

        $this->clear($service);

        if ($app->getInput()->get('do') === 'create') {
            $this->create($service);
            $app->enqueueMessage('Создано тестовых отзывов: 30.');
        } else {
            $app->enqueueMessage('Тестовые отзывы удалены.');
        }

        $app->redirect(Route::_('index.php?option=com_mucronixreviews&view=reviews&filter[state]=*', false));
    }

    private function clear($service): void
    {
        for ($item = 1; $item <= 30; $item++) {
            $service->deleteByItem(self::CONTEXT, $item);
        }
    }

    private function create($service): void
    {
        $db       = $this->getDatabase();
        $user     = $this->getApplication()->getIdentity();
        $states   = [1, 1, 1, 0, 0, -1, -3, -2];
        $contents = [
            'Отличный товар. Пользуюсь второй месяц, всё нравится. Доставили быстро.',
            'Короткий отзыв.',
            null,
            '<script>alert(1)</script> HTML в отзыве должен показываться как текст: <b>жирный</b> & "кавычки".',
            str_repeat('Это длинный отзыв, в списке видно только его начало. ', 30),
            'Со ссылкой: смотрите shop.ua и www.example.com',
        ];

        for ($item = 1; $item <= 30; $item++) {
            $text           = $contents[$item % \count($contents)];
            $state          = $states[$item % \count($states)];
            $rating         = 1 + $item % 5;
            $created        = Factory::getDate('-' . $item . ' days')->toSql();
            $context        = self::CONTEXT;

            // Every fifth review is of a deleted account
            $userId   = $item % 5 === 0 ? null : (int) $user->id;
            $username = $userId ? $user->username : 'deleted-user-' . $item;

            $query = $db->createQuery()
                ->insert($db->quoteName('#__mucronixreviews_reviews'))
                ->columns($db->quoteName(['context', 'item_id', 'user_id', 'username', 'rating', 'text', 'state', 'created']))
                ->values(':context, :item, :user, :username, :rating, :text, :state, :created')
                ->bind(':context', $context)
                ->bind(':item', $item, ParameterType::INTEGER)
                ->bind(':user', $userId, $userId ? ParameterType::INTEGER : ParameterType::NULL)
                ->bind(':username', $username)
                ->bind(':rating', $rating, ParameterType::INTEGER)
                ->bind(':text', $text, $text === null ? ParameterType::NULL : ParameterType::STRING)
                ->bind(':state', $state, ParameterType::INTEGER)
                ->bind(':created', $created);

            $db->setQuery($query)->execute();
            $service->recalculate(self::CONTEXT, $item);
        }
    }
}
