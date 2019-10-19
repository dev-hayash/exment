<?php

namespace Exceedone\Exment\ChangeFieldItems;

use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomColumn;
use Exceedone\Exment\Model\CustomViewFilter;
use Exceedone\Exment\Model\CustomValue;
use Exceedone\Exment\Model\Condition;
use Exceedone\Exment\Enums\ConditionTypeDetail;
use Exceedone\Exment\Enums\FilterOption;

class ColumnItem extends ChangeFieldItem
{
    use ColumnSystemItemTrait;
    
    /**
     * check if custom_value and user(organization, role) match for conditions.
     *
     * @param CustomValue $custom_value
     * @return boolean
     */
    public function isMatchCondition(Condition $condition, CustomValue $custom_value){
        $custom_column = CustomColumn::getEloquent($condition->target_column_id);
        $column_value = array_get($custom_value, 'value.' . $custom_column->column_name);
        if (is_null($column_value)) {
            return false;
        }
        if (!is_array($column_value)) {
            $column_value = [$column_value];
        }
        return collect($column_value)->filter()->contains(function ($value) use($condition) {
            if (is_array($condition->condition_value)) {
                return collect($condition->condition_value)->filter()->contains($value);
            } else {
                return $value == $condition->condition_value;
            }
        });
    }
    
    /**
     * get condition value text.
     *
     * @param CustomValue $custom_value
     * @return boolean
     */
    public function getConditionText(Condition $condition, CustomValue $custom_value){
        $custom_column = CustomColumn::getEloquent($condition->target_column_id);
        
        $column_name = $custom_column->column_name;
        $column_item = $custom_column->column_item;

        return $column_item->setCustomValue(["value.$column_name" => $condition->condition_value])->text();
    }
}
