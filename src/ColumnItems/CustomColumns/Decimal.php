<?php

namespace Exceedone\Exment\ColumnItems\CustomColumns;

use Exceedone\Exment\ColumnItems\CustomItem;
use Encore\Admin\Form\Field;
use Exceedone\Exment\Validator;

class Decimal extends CustomItem
{
    public function prepare()
    {
        if (!is_null($this->value())) {
            $this->value = parseFloat($this->value);
            if (array_has($this->custom_column, 'options.decimal_digit')) {
                $digit = intval(array_get($this->custom_column, 'options.decimal_digit'));
                $this->value = floor($this->value * pow(10, $digit)) / pow(10, $digit);
            }
        }

        return $this;
    }
    
    public function text()
    {
        if (is_null($this->value())) {
            return null;
        }

        if (boolval(array_get($this->custom_column, 'options.number_format'))
        && is_numeric($this->value())
        && !boolval(array_get($this->options, 'disable_number_format'))) {
            if (array_has($this->custom_column, 'options.decimal_digit')) {
                $digit = intval(array_get($this->custom_column, 'options.decimal_digit'));
                $number = number_format($this->value(), $digit);
                return preg_replace("/\.?0+$/",'', $number);
            } else {
                return number_format($this->value());
            }
        }
        return $this->value();
    }

    protected function getAdminFieldClass()
    {
        return Field\Text::class;
    }
    
    protected function setAdminOptions(&$field, $form_column_options)
    {
        $options = $this->custom_column->options;
        
        if (!is_null(array_get($options, 'number_min'))) {
            $field->attribute(['min' => array_get($options, 'number_min')]);
        }
        if (!is_null(array_get($options, 'number_max'))) {
            $field->attribute(['max' => array_get($options, 'number_max')]);
        }

        if (!is_null(array_get($options, 'decimal_digit'))) {
            $field->attribute(['decimal_digit' => array_get($options, 'decimal_digit')]);
        }
    }
    
    protected function setValidates(&$validates)
    {
        $options = $this->custom_column->options;
        
        // value size
        if (array_get($options, 'number_min')) {
            $validates[] = 'min:'.array_get($options, 'number_min');
        }
        if (array_get($options, 'number_max')) {
            $validates[] = 'max:'.array_get($options, 'number_max');
        }

        $validates[] = new Validator\DecimalCommaRule;
    }

    /**
     * get sort column name as SQL
     */
    public function getSortColumn()
    {
        $column_name = $this->index();
        if (array_has($this->custom_column, 'options.decimal_digit')) {
            $digit = intval(array_get($this->custom_column, 'options.decimal_digit'));
            return "CAST($column_name AS DECIMAL(50, $digit))";
        } else {
            return "CAST($column_name AS SIGNED)";
        }
    }
}