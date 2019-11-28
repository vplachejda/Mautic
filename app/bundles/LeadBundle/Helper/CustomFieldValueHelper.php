<?php

/*
 * @copyright   2019 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\LeadBundle\Helper;

use Mautic\CoreBundle\Helper\ArrayHelper;
use Mautic\CoreBundle\Helper\Serializer;

/**
 * Helper class custom field operations.
 */
class CustomFieldValueHelper
{
    const TYPE_BOOLEAN     = 'boolean';
    const TYPE_SELECT      = 'select';
    const TYPE_MULTISELECT = 'multiselect';

    /**
     * @param array $field
     *
     * @return mixed
     */
    public static function normalizeValue(array $field)
    {
        $value      = ArrayHelper::getValue('value', $field, '');
        $type       = ArrayHelper::getValue('type', $field);
        $properties = ArrayHelper::getValue('properties', $field);

        if ($value !== '' && $type && $properties) {
            $properties = Serializer::decode($properties);
            switch ($type) {
                case self::TYPE_BOOLEAN:
                    $values = array_values($properties);
                    $value  = $values[$value];
                    break;
                case self::TYPE_SELECT:
                    $value = self::setValueFromProperties($properties, $value);
                break;
                case self::TYPE_MULTISELECT:
                    $values = explode('|', $value);
                    foreach ($values as &$val) {
                        $val = self::setValueFromProperties($properties, $val);
                    }
                    $value = implode('|', $values);
                    break;
            }
        }

        return $value;
    }

    /**
     * @param array  $properties
     * @param string $value
     *
     * @return string
     */
    private static function setValueFromProperties(array $properties, $value)
    {
        if (isset($properties['list'])) {
            foreach ($properties['list'] as $property) {
                if ($property['value'] == $value) {
                    $value = $property['label'];
                }
            }
        }

        return $value;
    }
}
