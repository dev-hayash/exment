<?php

namespace Exceedone\Exment\Model;

use Exceedone\Exment\Enums\WorkflowType;
use Exceedone\Exment\Enums\SystemTableName;

class Workflow extends ModelBase
{
    use Traits\AutoSUuidTrait;
    use Traits\DatabaseJsonTrait;
    use Traits\UseRequestSessionTrait;
    use Traits\ClearCacheTrait;

    protected $appends = ['workflow_edit_flg'];
    protected $casts = ['options' => 'json'];

    public function workflow_tables()
    {
        return $this->hasMany(WorkflowTable::class, 'workflow_id');
    }

    /**
     * get workflow statuses
     */
    public function workflow_statuses()
    {
        return $this->hasMany(WorkflowStatus::class, 'workflow_id');
    }

    /**
     * get workflow actions
     */
    public function workflow_actions()
    {
        return $this->hasMany(WorkflowAction::class, 'workflow_id');
    }
        
    public function notifies()
    {
        return $this->hasMany(Notify::class, 'workflow_id');
    }

    protected static function boot()
    {
        parent::boot();
        
        // delete event
        static::deleting(function ($model) {
            // Delete items
            $model->deletingChildren();
        });
    }
    
    /**
     * Delete children items
     */
    public function deletingChildren()
    {
        $keys = ['workflow_statuses', 'workflow_tables', 'notifies'];
        $this->load($keys);
        foreach ($keys as $key) {
            foreach ($this->{$key} as $item) {
                if (!method_exists($item, 'deletingChildren')) {
                    continue;
                }
                $item->deletingChildren();
            }

            $this->{$key}()->delete();
        }
        
        foreach ($this->workflow_actions()->withTrashed()->get() as $item) {
            $item->deletingChildren();
        }

        $this->workflow_actions()->forceDelete();
    }

    public function getWorkflowEditFlgAttribute()
    {
        return $this->getOption('workflow_edit_flg');
    }
    public function setWorkflowEditFlgAttribute($workflow_edit_flg)
    {
        $this->setOption('workflow_edit_flg', $workflow_edit_flg);
        return $this;
    }

    public function getOption($key, $default = null)
    {
        return $this->getJson('options', $key, $default);
    }
    public function setOption($key, $val = null, $forgetIfNull = false)
    {
        return $this->setJson('options', $key, $val, $forgetIfNull);
    }

    /**
     * get workflow statuses using cache
     */
    public function getWorkflowStatusesCacheAttribute()
    {
        return $this->hasManyCache(WorkflowStatus::class, 'workflow_id');
    }

    /**
     * get workflow actions
     */
    public function getWorkflowActionsCacheAttribute()
    {
        return $this->hasManyCache(WorkflowAction::class, 'workflow_id');
    }

    /**
     * get eloquent using Cache.
     * now only support only id.
     */
    public static function getEloquent($id, $withs = [])
    {
        return static::getEloquentCache($id, $withs);
    }
    
    /**
     * Get status string
     *
     * @return Collection
     */
    public function getStatusesString()
    {
        return $this->getStatusOptions()->implode(exmtrans('common.separate_word'));
    }

    /**
     * Get status options. contains start and end.
     *
     * @return Collection
     */
    public function getStatusOptions($onlyStart = false)
    {
        $statuses = collect();
        if (!$onlyStart) {
            $statuses = $this->workflow_statuses_cache->pluck('status_name', 'id');
        }

        $statuses->prepend($this->start_status_name, Define::WORKFLOW_START_KEYNAME);

        return $statuses;
    }

    /**
     * Get workflow filtering active using custom table
     *
     * @param [type] $custom_table
     * @return void
     */
    public static function getWorkflowByTable($custom_table)
    {
        // if not has workflow, return false
        $hasWorkflow = System::cache(Define::SYSTEM_KEY_SESSION_HAS_WORLFLOW, function () {
            return WorkflowTable::count() > 0;
        });
        if (!$hasWorkflow) {
            return null;
        }

        $custom_table = CustomTable::getEloquent($custom_table);
        $today = \Carbon\Carbon::today();

        $workflowTable = WorkflowTable::allRecordsCache(function ($record) use ($custom_table, $today) {
            if ($custom_table->id != $record->custom_table_id) {
                return false;
            }

            if (!boolval($record->active_flg)) {
                return false;
            }

            if (isset($record->active_start_date) && $today->lt(new \Carbon\Carbon($record->active_start_date))) {
                return false;
            }

            if (isset($record->active_end_date) && $today->gt(new \Carbon\Carbon($record->active_end_date))) {
                return false;
            }
            
            return true;
        }, false)->first();

        if (!isset($workflowTable)) {
            return null;
        }

        return Workflow::getEloquent($workflowTable->workflow_id);
    }

    /**
     * Get custom table. Only workflow type is table
     * If workflow is common, return null
     *
     * @param [type] $custom_table
     * @return void
     */
    public function getDesignatedTable()
    {
        $key = sprintf(Define::SYSTEM_KEY_SESSION_WORKFLOW_DESIGNATED_TABLE, $this->id);
        return System::requestSession($key, function () {
            if ($this->workflow_type == WorkflowType::COMMON) {
                return null;
            }

            $workflowTables = $this->workflow_tables;
            if (is_nullorempty($workflowTables)) {
                return null;
            }

            return $workflowTables->first()->custom_table;
        });
    }

    /**
     * Check can change activate this wokflow
     *
     * @return boolean
     */
    public function canActivate()
    {
        if (boolval($this->setting_completed_flg)) {
            return false;
        }

        // check statuses
        if (count($this->workflow_statuses) == 0) {
            return false;
        }

        // check actions
        if (count($this->workflow_actions) == 0) {
            return false;
        }

        return true;
    }

    public static function hasSettingCompleted()
    {
        return static::allRecords(function ($workflow) {
            return boolval($workflow->setting_completed_flg);
        })->count() > 0;
    }
    
    /**
     * Whether this model disable delete
     *
     * @return boolean
     */
    public function getDisabledDeleteAttribute()
    {
        return boolval($this->setting_completed_flg);
    }

    /**
     * append workflow status
     *
     * @return Workflow
     */
    public function appendStartStatus() : Workflow
    {
        $this->workflow_statuses->prepend(WorkflowStatus::getWorkflowStartStatus($this));

        return $this;
    }

    /**
     * Get all workflow authorities
     *
     * @param CustomTable $custom_table
     * @return void
     */
    public static function getAllAuthorities(CustomTable $custom_table)
    {
        $values = \DB::table(SystemTableName::WORKFLOW_AUTHORITY)
            ->join(SystemTableName::WORKFLOW_ACTION, SystemTableName::WORKFLOW_AUTHORITY . '.workflow_action_id', SystemTableName::WORKFLOW_ACTION . '.id')
            ->join(SystemTableName::WORKFLOW, SystemTableName::WORKFLOW_ACTION . '.workflow_id', SystemTableName::WORKFLOW . '.id')
            ->join(SystemTableName::WORKFLOW_TABLE, SystemTableName::WORKFLOW_TABLE . '.workflow_id', SystemTableName::WORKFLOW . '.id')
            ->select(SystemTableName::WORKFLOW_AUTHORITY . '.*')
            ->distinct()
            ->get();
        
        return $values;
    }

    /**
     * Get all value workflow authorities
     *
     * @param CustomTable $custom_table
     * @return void
     */
    public static function getAllValueAuthorities(CustomTable $custom_table)
    {
        $values = \DB::table(SystemTableName::WORKFLOW_VALUE_AUTHORITY)
            ->join(SystemTableName::WORKFLOW_VALUE, SystemTableName::WORKFLOW_VALUE_AUTHORITY . '.workflow_value_id', SystemTableName::WORKFLOW_VALUE . '.id')
            ->join(SystemTableName::WORKFLOW, SystemTableName::WORKFLOW_VALUE . '.workflow_id', SystemTableName::WORKFLOW . '.id')
            ->join(SystemTableName::WORKFLOW_TABLE, SystemTableName::WORKFLOW_TABLE . '.workflow_id', SystemTableName::WORKFLOW . '.id')
            ->select(SystemTableName::WORKFLOW_VALUE_AUTHORITY . '.*')
            ->distinct()
            ->get();
        
        return $values;
    }
}
