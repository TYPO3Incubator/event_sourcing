<?php
namespace TYPO3\CMS\EventSourcing\Tests\Unit\Infrastructure\EventStore;

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

use TYPO3\CMS\Core\Tests\UnitTestCase;
use TYPO3\CMS\EventSourcing\Core\Domain\Model\Base\Event;
use TYPO3\CMS\EventSourcing\DataHandling\Infrastructure\EventStore\EventSelector;
use TYPO3\CMS\EventSourcing\Tests\Framework\AssertionUtility;
use TYPO3\CMS\EventSourcing\Tests\Functional\Infrastructure\EventStore\Fixtures;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;

class EventSelectorTest extends UnitTestCase
{
    /**
     * @param string $selector
     * @param array $expectations
     * @test
     * @dataProvider eventSelectorIsCreatedDataProvider
     */
    public function eventSelectorIsCreated(string $selector, array $expectations)
    {
        $eventSelector = EventSelector::create($selector);

        $this->assertTrue(
            AssertionUtility::matchesExpectations($expectations, $eventSelector),
            'Selector "' . $selector . '" did not match expectations'
        );
    }

    /**
     * @return array
     */
    public function eventSelectorIsCreatedDataProvider(): array
    {
        return [
            'all' => [
                '*',
                [
                    'all' => true,
                    'streamName' => '',
                    'categories' => [],
                    'events' => [],
                ]
            ],
            'absolute stream name' => [
                '$stream',
                [
                    'streamName' => 'stream',
                    'categories' => [],
                    'events' => [],
                    'relative' => false,
                ]
            ],
            'relative stream name' => [
                '~stream',
                [
                    'streamName' => 'stream',
                    'categories' => [],
                    'events' => [],
                    'relative' => true,
                ]
            ],
            'one category' => [
                '.category',
                [
                    'streamName' => '',
                    'categories' => ['category'],
                    'events' => [],
                ]
            ],
            'one event' => [
                '[event]',
                [
                    'streamName' => '',
                    'categories' => [],
                    'events' => ['event'],
                ]
            ],
            'many events' => [
                '[created,changed]',
                [
                    'streamName' => '',
                    'categories' => [],
                    'events' => ['created', 'changed'],
                ]
            ],
            'many categories' => [
                '.first.second',
                [
                    'streamName' => '',
                    'categories' => ['first', 'second'],
                    'events' => [],
                ]
            ],
            'stream name with one category and one event' => [
                '$stream.category[event]',
                [
                    'streamName' => 'stream',
                    'categories' => ['category'],
                    'events' => ['event'],
                ]
            ],
            'stream name with many categories and events' => [
                '$stream.first.second[created,changed]',
                [
                    'streamName' => 'stream',
                    'categories' => ['first', 'second'],
                    'events' => ['created', 'changed'],
                ]
            ],
            'stream wildcard name with many categories and events' => [
                '$stream/record/*.first.second[created,changed]',
                [
                    'streamName' => 'stream/record/*',
                    'categories' => ['first', 'second'],
                    'events' => ['created', 'changed'],
                ]
            ],
        ];
    }

    /**
     * @param string $selector
     * @test
     * @dataProvider invalidEventSelectorIsDeterminedDataProvider
     * @expectedException \RuntimeException
     */
    public function invalidEventSelectorIsDetermined(string $selector)
    {
        EventSelector::create($selector);
    }

    /**
     * @return array
     */
    public function invalidEventSelectorIsDeterminedDataProvider()
    {
        return [
            'noting' => [
                '',
            ],
            'invalid all' => [
                '*.category[event]'
            ],
            'without stream prefix' => [
                'stream',
            ],
            'stream with empty events' => [
                '$stream[]',
            ],
            'empty category' => [
                '.',
            ],
            'empty events' => [
                '[]',
            ],
            'empty events with comma' => [
                '[,]',
            ],
        ];
    }

    /**
     * @param string $supervisor
     * @param string $candidate
     * @param bool $expectation
     * @test
     * @dataProvider isFulfilledDataProvider
     */
    public function isFulfilled(string $supervisor, string $candidate, bool $expectation)
    {
        $this->assertEquals(
            $expectation,
            EventSelector::create($supervisor)
                ->fulfills(EventSelector::create($candidate))
        );
    }

    /**
     * @return array
     */
    public function isFulfilledDataProvider()
    {
        return [
            'all' => [
                '*', '*', true
            ],
            'all & anything' => [
                '*', '$stream.category[event]', true
            ],
            'equal streams' => [
                '$stream', '$stream', true
            ],
            'different streams' => [
                '$stream', '$other', false
            ],
            'matching wildcard streams' => [
                '$stream/*', '$stream/record/abcd', true
            ],
            'matching both wildcard streams' => [
                '$stream/*', '$stream/record/*', true
            ],
            'different wildcard streams' => [
                '$stream/record/abcd', '$stream/*', false
            ],
            'matching categories' => [
                '.first.second', '.first.other-one', true
            ],
            'different categories' => [
                '.dunno.second', '.first.other-one', false
            ],
            'matching events' => [
                '[' . \stdClass::class . ']', '[' . \stdClass::class . ']', true
            ],
            'different events' => [
                '[dunno,second]', '[first,other-one]', false
            ],
            'matching inherited events' => [
                '[' . Event\BaseEvent::class . ']',
                '[' . Fixtures\AbstractEventFixture::class . ']',
                true
            ],
            'different inherited events' => [
                '[' . Fixtures\AbstractEventFixture::class . ']',
                '[' . Fixtures\EventFixture::class . ']',
                false
            ],
            'matching chain' => [
                '$stream/*.first.second[' . Event\BaseEvent::class . ']',
                '$stream/record/abcd.first.other-one[' . Fixtures\AbstractEventFixture::class . ']',
                true
            ],
            'different chain' => [
                '$stream/record/abcd.first.other-one[' . Fixtures\AbstractEventFixture::class . ']',
                '$stream/*.first.second[' . Event\BaseEvent::class . ']',
                false
            ],
            'empty left-side stream' => [
                '[null]',
                '$stream',
                false,
            ],
            'empty left-side category' => [
                '[null]',
                '.category',
                false,
            ],
            'empty left-side event' => [
                '.null',
                '[event]',
                false,
            ],
        ];
    }

    /**
     * @test
     */
    public function isConvertedToAbsolute()
    {
        $relativeEventSelector = EventSelector::create('~aspect.category[event]');
        $absoluteEventSelector = $relativeEventSelector->toAbsolute('absolute');

        $expectations = [
            'streamName' => 'absolute/aspect',
            'categories' => ['category'],
            'events' => ['event'],
            'relative' => false,
            'all' => false,
        ];

        $this->assertTrue(
            AssertionUtility::matchesExpectations($expectations, $absoluteEventSelector)
        );
    }

    /**
     * @param array $properties
     * @test
     * @dataProvider invalidLiteralsAreDeterminedDataProvider
     * @expectedException \RuntimeException
     */
    public function invalidLiteralsAreDetermined(array $properties)
    {
        $eventSelector = EventSelector::instance();
        foreach ($properties as $propertyPath => $propertyValue) {
            ObjectAccess::setProperty($eventSelector, $propertyPath, $propertyValue);
        }
    }

    /**
     * @return array
     */
    public function invalidLiteralsAreDeterminedDataProvider()
    {
        return [
            'streamName' => [
                [
                    'streamName' => '$stream',
                ],
            ],
            'categories' => [
                [
                    'categories' => ['$category'],
                ],
            ],
            'events' => [
                [
                    'events' => ['$event'],
                ],
            ],
            'combined' => [
                [
                    'streamName' => '~stream.name',
                    'categories' => ['category[mixed]'],
                    'events' => ['event,event'],
                ],
            ],
        ];
    }

    /**
     * @param EventSelector $selector
     * @param string $expected
     * @test
     * @dataProvider isRepresentedAsStringDataProvider
     */
    public function isRepresentedAsString(EventSelector $selector, string $expected)
    {
        $this->assertEquals($expected, $selector . '');
    }

    /**
     * @return array
     */
    public function isRepresentedAsStringDataProvider()
    {
        return [
            'all' => [
                EventSelector::instance()->setAll(true),
                '*',
            ],
            'absolute stream' => [
                EventSelector::instance()->setStreamName('stream'),
                '$stream',
            ],
            'relative stream' => [
                EventSelector::instance()->setStreamName('stream')->setRelative(true),
                '~stream',
            ],
            'categories' => [
                EventSelector::instance()->setCategories(['firstCategory', 'secondCategory']),
                '.firstCategory.secondCategory',
            ],
            'events' => [
                EventSelector::instance()->setEvents(['firstEvent', 'secondEvent']),
                '[firstEvent,secondEvent]',
            ],
            'combined' => [
                EventSelector::instance()
                    ->setStreamName('stream')
                    ->setCategories(['firstCategory', 'secondCategory'])
                    ->setEvents(['firstEvent', 'secondEvent']),
                '$stream.firstCategory.secondCategory[firstEvent,secondEvent]',
            ],
        ];
    }
}