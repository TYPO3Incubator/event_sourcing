<?php
namespace TYPO3\CMS\EventSourcing\Core\Service;

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

use TYPO3\CMS\Core\SingletonInterface;

class MetaModelService implements SingletonInterface
{
    /**
     * @return MetaModelService
     */
    public static function instance()
    {
        return new static();
    }

    public function shallListenEvents(string $tableName)
    {
        return (bool)($GLOBALS['TCA'][$tableName]['ctrl']['eventSourcing']['listenEvents'] ?? false);
    }

    public function shallRecordEvents(string $tableName)
    {
        return (bool)($GLOBALS['TCA'][$tableName]['ctrl']['eventSourcing']['recordEvents'] ?? false);
    }

    public function shallProjectEvents(string $tableName)
    {
        return (bool)($GLOBALS['TCA'][$tableName]['ctrl']['eventSourcing']['projectEvents'] ?? false);
    }

    public function isWorkspaceAware(string $tableName)
    {
        return (bool)($GLOBALS['TCA'][$tableName]['ctrl']['versioningWS'] ?? false);
    }

    public function getDeletedFieldName(string $tableName)
    {
        return ($GLOBALS['TCA'][$tableName]['ctrl']['delete'] ?? null);
    }

    public function getTimestampFieldName(string $tableName)
    {
        return ($GLOBALS['TCA'][$tableName]['ctrl']['tstamp'] ?? null);
    }

    public function getCreationDateFieldName(string $tableName)
    {
        return ($GLOBALS['TCA'][$tableName]['ctrl']['crdate'] ?? null);
    }

    public function getDisabledFieldName(string $tableName)
    {
        return ($GLOBALS['TCA'][$tableName]['ctrl']['enablecolumns']['disabled'] ?? null);
    }

    public function getLanguageFieldName(string $tableName)
    {
        return ($GLOBALS['TCA'][$tableName]['ctrl']['languageField'] ?? null);
    }

    public function getLanguagePointerFieldName(string $tableName)
    {
        return ($GLOBALS['TCA'][$tableName]['ctrl']['transOrigPointerField'] ?? null);
    }

    public function getLanguagePointerTableName(string $tableName)
    {
        return ($GLOBALS['TCA'][$tableName]['ctrl']['transOrigPointerTable'] ?? $tableName);
    }

    public function getLanguageTableName(string $tableName)
    {
        return ($GLOBALS['TCA'][$tableName]['ctrl']['transForeignTable'] ?? $tableName);
    }

    public function getOriginalPointerField(string $tableName)
    {
        return ($GLOBALS['TCA'][$tableName]['ctrl']['origUid'] ?? null);
    }

    public function getSortingField(string $tableName)
    {
        return ($GLOBALS['TCA'][$tableName]['ctrl']['sortby'] ?? null);

    }

    public function getOrderByExpression(string $tableName)
    {
        return ($GLOBALS['TCA'][$tableName]['ctrl']['default_sortby'] ?? null);

    }

    public function getColumnConfiguration(string $tableName, string $propertyName)
    {
        return ($GLOBALS['TCA'][$tableName]['columns'][$propertyName] ?? null);
    }

    public function shallPrefixTitleOnTranslation(string $tableName, string $propertyName)
    {
        $setting = ($GLOBALS['TCA'][$tableName]['columns'][$propertyName]['l10n_mode'] ?? null);
        return ($setting === 'prefixLangTitle');
    }

    public function isInvalidValueProperty(string $tableName, string $propertyName): bool
    {
        return (
            $this->isInvalidChangeProperty($tableName, $propertyName)
            || $this->isRelationProperty($tableName, $propertyName)
        );
    }

    public function isInvalidChangeProperty(string $tableName, string $propertyName): bool
    {
        return (
            !$this->isConfiguredProperty($tableName, $propertyName)
            || $this->isSystemProperty($tableName, $propertyName)
            || $this->isActionProperty($tableName, $propertyName)
            || $this->isVisibilityProperty($tableName, $propertyName)
            || $this->isRestrictionProperty($tableName, $propertyName)
        );
    }

    public function isConfiguredProperty(string $tableName, string $propertyName): bool
    {
        return (
            $this->getColumnConfiguration($tableName, $propertyName) !== null
        );
    }

    // @todo Analyse group/file with MM references
    public function isRelationProperty(string $tableName, string $propertyName): bool
    {
        if (empty($GLOBALS['TCA'][$tableName]['columns'][$propertyName]['config']['type'])) {
            return false;
        }

        $configuration = $GLOBALS['TCA'][$tableName]['columns'][$propertyName]['config'];

        return (
            $configuration['type'] === 'group'
                && ($configuration['internal_type'] ?? null) === 'db'
                && !empty($configuration['allowed'])
            || $configuration['type'] === 'select'
                && (
                    !empty($configuration['foreign_table'])
                        && !empty($GLOBALS['TCA'][$configuration['foreign_table']])
                    || ($configuration['special'] ?? null) === 'languages'
                )
            || $this->isInlineRelationProperty($tableName, $propertyName)
        );
    }

    public function isInlineRelationProperty(string $tableName, string $propertyName): bool
    {
        if (empty($GLOBALS['TCA'][$tableName]['columns'][$propertyName]['config']['type'])) {
            return false;
        }

        $configuration = $GLOBALS['TCA'][$tableName]['columns'][$propertyName]['config'];

        return (
            $configuration['type'] === 'inline'
            && !empty($configuration['foreign_table'])
            && !empty($GLOBALS['TCA'][$configuration['foreign_table']])
        );
    }

    public function isCascadingDeleteRelationProperty(string $tableName, string $propertyName): bool
    {
        if (empty($GLOBALS['TCA'][$tableName]['columns'][$propertyName]['config']['type'])) {
            return false;
        }

        $configuration = $GLOBALS['TCA'][$tableName]['columns'][$propertyName]['config'];

        return (
            $this->isInlineRelationProperty($tableName, $propertyName)
            && (
                // default behavior is enabled, if property is not defined
                !isset($configuration['behaviour']['enableCascadingDelete'])
                || !empty($configuration['behaviour']['enableCascadingDelete'])
            )
            && empty($configuration['MM'])
        );
    }

    public function isSystemProperty(string $tableName, string $propertyName): bool
    {
        $denyPropertyNames = ['uid', 'pid'];
        $ctrlNames = ['tstamp', 'crdate', 'cruser_id', 'editlock', 'origUid'];
        foreach ($ctrlNames as $ctrlName) {
            if (!empty($GLOBALS['TCA'][$tableName]['ctrl'][$ctrlName])) {
                $denyPropertyNames[] = $GLOBALS['TCA'][$tableName]['ctrl'][$ctrlName];
            }
        }

        return (
            in_array($propertyName, $denyPropertyNames)
            || strpos($propertyName, 't3ver_') === 0
        );
    }

    public function isActionProperty(string $tableName, string $propertyName): bool
    {
        $denyPropertyNames = [];
        $ctrlNames = ['sortby', 'delete'];
        foreach ($ctrlNames as $ctrlName) {
            if (!empty($GLOBALS['TCA'][$tableName]['ctrl'][$ctrlName])) {
                $denyPropertyNames[] = $GLOBALS['TCA'][$tableName]['ctrl'][$ctrlName];
            }
        }

        return (
            in_array($propertyName, $denyPropertyNames)
        );
    }

    public function isVisibilityProperty(string $tableName, string $propertyName): bool
    {
        $denyPropertyNames = [];
        $ctrlEnableNames = ['disabled'];
        foreach ($ctrlEnableNames as $ctrlEnableName) {
            if (!empty($GLOBALS['TCA'][$tableName]['ctrl']['enablecolumns'][$ctrlEnableName])) {
                $denyPropertyNames[] = $GLOBALS['TCA'][$tableName]['ctrl']['enablecolumns'][$ctrlEnableName];
            }
        }

        return (
            in_array($propertyName, $denyPropertyNames)
        );
    }

    public function isRestrictionProperty(string $tableName, string $propertyName): bool
    {
        $denyPropertyNames = [];
        $ctrlEnableNames = ['starttime', 'endtime', 'fe_group'];
        foreach ($ctrlEnableNames as $ctrlEnableName) {
            if (!empty($GLOBALS['TCA'][$tableName]['ctrl']['enablecolumns'][$ctrlEnableName])) {
                $denyPropertyNames[] = $GLOBALS['TCA'][$tableName]['ctrl']['enablecolumns'][$ctrlEnableName];
            }
        }

        return (
            in_array($propertyName, $denyPropertyNames)
        );
    }
}
