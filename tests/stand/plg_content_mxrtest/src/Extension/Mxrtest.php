<?php

namespace Mucronix\Plugin\Content\Mxrtest\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\Event\SubscriberInterface;
use Mucronix\Component\MucronixReviews\Administrator\Context\ContextHandlerInterface;
use Mucronix\Component\MucronixReviews\Administrator\Event\RegisterContextsEvent;

/**
 * A type of objects for the stand: 1 to 1000 exist, 13 is for nobody, 14 for administrators only, 15 breaks, 16 has a script for an address.
 */
final class Mxrtest extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return ['onMucronixReviewsRegisterContexts' => 'register'];
    }

    public function register(RegisterContextsEvent $event): void
    {
        foreach (['com_test.item', 'com_test.other'] as $name) {
            $event->addHandler(
                new class ($name) implements ContextHandlerInterface {
                    public function __construct(private string $name)
                    {
                    }

                    public function getContext(): string
                    {
                        return $this->name;
                    }

                    public function getLabel(): string
                    {
                        return 'Test <b>items</b>';
                    }

                    public function isAvailable(int $itemId, User $user): bool
                    {
                        if ($itemId === 15) {
                            throw new \RuntimeException('secret detail');
                        }

                        return $itemId <= 1000 && $itemId !== 13 && ($itemId !== 14 || $user->authorise('core.admin'));
                    }

                    public function getTitle(int $itemId): string
                    {
                        if ($itemId === 15) {
                            throw new \RuntimeException('no title');
                        }

                        return 'Item <i>' . $itemId;
                    }

                    public function getUrl(int $itemId): string
                    {
                        return $itemId === 16 ? 'javascript:alert(1)' : ($itemId === 63 ? '/index.php?item=63' : str_replace('administrator/', '', Uri::base()) . 'index.php?item=' . $itemId);
                    }

                    public function getSchemaType(): ?string
                    {
                        return 'Product';
                    }
                }
            );
        }
    }
}
