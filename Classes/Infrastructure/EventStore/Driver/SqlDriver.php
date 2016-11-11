<?php
namespace TYPO3\CMS\EventSourcing\DataHandling\Infrastructure\EventStore\Driver;

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

use TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\EventSourcing\Core\Database\ConnectionPool;
use TYPO3\CMS\EventSourcing\Core\Domain\Model\Base\Event\BaseEvent;
use TYPO3\CMS\EventSourcing\DataHandling\Infrastructure\EventStore\EventSelector;
use TYPO3\CMS\EventSourcing\DataHandling\Infrastructure\EventStore\EventStream;

class SqlDriver implements PersistableDriver
{
    const FORMAT_DATETIME = 'Y-m-d H:i:s.u';

    /**
     * @return SqlDriver
     */
    public static function instance()
    {
        return new static();
    }

    /**
     * @param string $streamName
     * @param BaseEvent $event
     * @param array $categories
     * @return bool|int|string
     * @throws \Doctrine\DBAL\ConnectionException
     * @throws \Exception
     */
    public function attach(string $streamName, BaseEvent $event, array $categories = [])
    {
        $aggregateId = null;
        if ($event->getAggregateId() !== null) {
            $aggregateId = $event->getAggregateId()->toString();
        }

        $rawEvent = [
            'event_stream' => $streamName,
            'event_categories' => (!empty($categories) ? implode(',', $categories) : null),
            'event_id' => $event->getEventId(),
            'event_name' => get_class($event),
            'event_date' => $event->getEventDate()->format(static::FORMAT_DATETIME),
            'aggregate_id' => $aggregateId,
            'data' => $event->exportData(),
            'metadata' => $event->getMetadata(),
        ];

        foreach ($rawEvent as $propertyName => $propertyValue) {
            if ($propertyValue === null) {
                unset($rawEvent[$propertyName]);
            } elseif (is_array($propertyValue)) {
                $rawEvent[$propertyName] = json_encode($propertyValue);
            }
        }

        $connection = ConnectionPool::instance()->getOriginConnection();
        $connection->beginTransaction();

        try {
            $queryBuilder = $connection->createQueryBuilder();
            $queryBuilder->getRestrictions()->removeAll();
            $statement = $queryBuilder
                ->select('event_version')
                ->from('sys_event_store')
                ->where(
                    $queryBuilder->expr()->eq(
                        'event_stream',
                        $queryBuilder->createNamedParameter($streamName)
                    )
                )
                ->orderBy('event_version', 'DESC')
                ->setMaxResults(1)
                ->execute();
            // first version starts with zero
            $eventVersion = $statement->fetchColumn(0);
            $eventVersion = ($eventVersion !== false ? $eventVersion + 1 : 0);
            $rawEvent['event_version'] = $eventVersion;
            $connection->insert('sys_event_store', $rawEvent);
            $connection->commit();
        } catch (\Exception $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return $eventVersion;
    }

    /**
     * @param string $streamName
     * @param array $categories
     * @return EventStream
     */
    public function stream(string $streamName, array $categories = [])
    {
        if (empty($streamName) && empty($categories)) {
            throw new \RuntimeException('No selection criteria given', 1471441756);
        }

        $predicates = [];
        $queryBuilder = ConnectionPool::instance()->getOriginQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll();

        if (!empty($streamName)) {
            $predicates[] = $this->createMatchesWildcardExpression($queryBuilder, 'event_stream', $streamName);
        }
        if (!empty($categories)) {
            $predicates[] = $this->createFindInListExpression($queryBuilder, 'event_categories', $categories);
        }
        // @todo Add event name selection

        $statement = $queryBuilder
            ->select('*')
            ->from('sys_event_store')
            ->where(...$predicates)
            ->execute();

        return EventStream::create(SqlDriverIterator::create($statement), $streamName);
    }

    /**
     * @return bool
     */
    public function isAvailable(): bool
    {
        return ConnectionPool::instance()->getOriginConnection()->ping();
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param string $fieldName
     * @param string $needle
     * @return string
     */
    protected function createMatchesWildcardExpression(QueryBuilder $queryBuilder, string $fieldName, string $needle)
    {
        $comparableNeedle = EventSelector::getComparablePart($needle);
        if ($needle === $comparableNeedle) {
            $namedParameter = $queryBuilder->createNamedParameter($needle);
            $expression = $queryBuilder->expr()->eq($fieldName, $namedParameter);
        } else {
            $escapedComparableNeedle = $queryBuilder->escapeLikeWildcards($comparableNeedle);
            $expression = $queryBuilder->expr()->like($fieldName, $queryBuilder->quote($escapedComparableNeedle . '%'));
        }
        return $expression;
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param string $fieldName
     * @param array $needles
     * @return CompositeExpression
     */
    protected function createFindInListExpression(QueryBuilder $queryBuilder, string $fieldName, array $needles)
    {
        $expression = $queryBuilder->expr()->orX();
        foreach ($needles as $needle) {
            $namedParameter = $queryBuilder->createNamedParameter($needle);
            $escapedNeedle = $queryBuilder->escapeLikeWildcards($needle);
            $expression->addMultiple([
                $queryBuilder->expr()->eq($fieldName, $namedParameter),
                $queryBuilder->expr()->like($fieldName, $queryBuilder->quote($escapedNeedle . ',%')),
                $queryBuilder->expr()->like($fieldName, $queryBuilder->quote('%,' . $escapedNeedle)),
                $queryBuilder->expr()->like($fieldName, $queryBuilder->quote('%,' . $escapedNeedle . ',%')),
            ]);
        }
        return $expression;
    }
}
