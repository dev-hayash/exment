<?php

namespace Exceedone\Exment\Model;

class WorkflowConditionHeader extends ModelBase
{
    use Traits\UseRequestSessionTrait;
    use Traits\ClearCacheTrait;
    use Traits\DatabaseJsonTrait;

    protected $appends = ['condition_join'];
    protected $casts = ['options' => 'json'];

    public function workflow_action()
    {
        return $this->belongsTo(WorkflowAction::class, 'workflow_action_id');
    }
    
    public function workflow_conditions()
    {
        return $this->morphMany(Condition::class, 'morph', 'morph_type', 'morph_id');
    }

    /**
     * check if custom_value and user(organization, role) match for conditions.
     */
    public function isMatchCondition($custom_value)
    {
        $is_or = $this->condition_join == 'or'? true: false;
        foreach ($this->workflow_conditions as $condition) {
            if ($is_or) {
                if ($condition->isMatchCondition($custom_value)) {
                    return true;
                }
            } else {
                if (!$condition->isMatchCondition($custom_value)) {
                    return false;
                }
            }
        }
        return !$is_or;
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($model) {
            $model->deletingChildren();
        });
    }
    
    public function deletingChildren()
    {
        $keys = ['workflow_conditions'];
        $this->load($keys);
        foreach ($keys as $key) {
            $this->{$key}()->delete();
        }
    }

    public function getOption($key, $default = null)
    {
        return $this->getJson('options', $key, $default);
    }
    public function setOption($key, $val = null, $forgetIfNull = false)
    {
        return $this->setJson('options', $key, $val, $forgetIfNull);
    }
    
    public function getConditionJoinAttribute()
    {
        return $this->getOption('condition_join');
    }

    public function setConditionJoinAttribute($val)
    {
        $this->setOption('condition_join', $val);

        return $this;
    }
}