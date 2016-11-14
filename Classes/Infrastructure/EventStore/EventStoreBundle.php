<?php
namespace TYPO3\CMS\EventSourcing\Infrastructure\EventStore;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\EventSourcing\Core\Domain\Model\Base\Event\BaseEvent;
use TYPO3\CMS\EventSourcing\Core\Domain\Model\Base\Event\StorableEvent;

class EventStoreBundle extends \ArrayObject implements AttachableStore
{
    /**
     * @return EventStoreBundle
     */
    public static function instance()
    {
        return new static();
    }

    /**
     * @param string $streamName
     * @param BaseEvent $event
     * @param string[] $categories
     * @param null $expectedVersion
     */
    public function attach(string $streamName, BaseEvent $event, array $categories = [], $expectedVersion = null)
    {
        if (!$event instanceof StorableEvent) {
            throw new \RuntimeException('Event "' . get_class($event) . '" cannot be stored', 1470871139);
        }

        /** @var EventStore $eventStore */
        foreach ($this as $eventStore) {
            $eventStore->attach($streamName, $event, $categories, $expectedVersion);
        }
    }
}
