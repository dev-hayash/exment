<?php

namespace Exceedone\Exment\Enums;

class FormColumnType extends EnumBase
{
    use EnumOptionTrait;
    
    const COLUMN = 0;
    const SYSTEM = 1;
    const OTHER = 99;
    
    protected static $options = [
        1 => ['id' => 1, 'column_name' => 'header'],
        2 => ['id' => 2, 'column_name' => 'explain'],
        3 => ['id' => 3, 'column_name' => 'html'],
        4 => ['id' => 4, 'column_name' => 'exhtml'],
    ];
    
    public static function getEnum($value, $default = null)
    {
        $enum = parent::getEnum($value, $default);
        if (isset($enum)) {
            return $enum;
        }

        foreach (self::$options as $key => $v) {
            if (array_get($v, 'id') == $value) {
                return parent::getEnum($key);
            }
        }
        return $default;
    }
}
