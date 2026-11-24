<?php

namespace Mucronix\Plugin\Content\Mxrdemo\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Mucronix\Component\MucronixReviews\Administrator\Context\ContextHandlerInterface;

/**
 * The test objects.
 */
final class DemoContext implements ContextHandlerInterface
{
    public function getContext(): string
    {
        return 'com_mxrdemo.item';
    }

    public function getLabel(): string
    {
        return 'Тестовые объекты';
    }

    public function isAvailable(int $itemId, User $user): bool
    {
        return $itemId >= 1 && $itemId <= 30;
    }

    public function getTitle(int $itemId): string
    {
        return 'Тестовый объект ' . $itemId;
    }

    public function getUrl(int $itemId): string
    {
        return Uri::root() . '?mxrdemo=' . $itemId;
    }

    public function getSchemaType(): ?string
    {
        return null;
    }
}
