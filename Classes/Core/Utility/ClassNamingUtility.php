<?php
namespace TYPO3\CMS\EventSourcing\Core\Utility;

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


class ClassNamingUtility extends \TYPO3\CMS\Core\Utility\ClassNamingUtility
{
    /**
     * @param object|string $object
     * @return string
     */
    public static function getLastPart($object): string
    {
        if (is_object($object)) {
            $className = get_class($object);
        } else {
            $className = (string)$object;
        }

        $classNameParts = explode('\\', $className);
        return $classNameParts[count($classNameParts)-1];
    }

    /**
     * @param string $className
     * @return bool
     */
    public static function isModelClassName(string $className)
    {
        return (strpos($className, '\\Domain\\Model\\') !== false);
    }

    /**
     * @param string $className
     * @return null|string
     */
    public static function buildValidationModelClassName($className)
    {
        $validationModelClassName = preg_replace(
            '/\\\\Domain\\\\Model\\\\/',
            '\\Domain\\ValidationModel\\',
            $className
        );

        if (
            $className !== $validationModelClassName
            && class_exists($validationModelClassName)
        ) {
            return $validationModelClassName;
        }

        return null;
    }
}
