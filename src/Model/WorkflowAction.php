<?php

namespace Exceedone\Exment\Model;

use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Enums\SystemColumn;
use Exceedone\Exment\Enums\ConditionTypeDetail;
use Exceedone\Exment\Enums\WorkflowWorkTargetType;
use Exceedone\Exment\Enums\WorkflowTargetSystem;
use Exceedone\Exment\Enums\WorkflowCommentType;
use Exceedone\Exment\Form\Widgets\ModalForm;

class WorkflowAction extends ModelBase
{
    use Traits\DatabaseJsonTrait,
    Traits\UseRequestSessionTrait,
    \Illuminate\Database\Eloquent\SoftDeletes;

    protected $appends = ['work_targets', 'work_conditions', 'comment_type', 'flow_next_type', 'flow_next_count', 'reject_action'];
    protected $casts = ['options' => 'json'];

    protected $work_targets;
    protected $work_condition_headers;

    public function workflow()
    {
        return $this->belongsTo(Workflow::class, 'workflow_id');
    }

    public function workflow_authorities()
    {
        return $this->hasMany(WorkflowAuthority::class, 'workflow_action_id');
            //->with(['user_organization']);
    }

    public function workflow_condition_headers()
    {
        return $this->hasMany(WorkflowConditionHeader::class, 'workflow_action_id');
    }

    public function getWorkTargetsAttribute()
    {
        return WorkflowAuthority::where('workflow_action_id', $this->id)->get();
    }
    public function setWorkTargetsAttribute($work_targets)
    {
        if(is_nullorempty($work_targets)){
            return;
        }
        
        $this->work_targets = jsonToArray($work_targets);

        $this->setOption('work_target_type', array_get($this->work_targets, 'work_target_type'));
        
        return $this;
    }

    /**
     * Get work conditions. Contains status_to, enabled_flg, workflow_conditions, etc
     *
     * @return void
     */
    public function getWorkConditionsAttribute()
    {
        $headers = $this->workflow_condition_headers()
            ->with('workflow_conditions')
            ->get()->toArray();

        return collect($headers)->map(function($header){
            $header['workflow_conditions'] = collect(array_get($header, 'workflow_conditions', []))->map(function($h){
                return array_only($h, 
                    ['id', 'condition_target', 'condition_type', 'condition_key', 'condition_value']
                );
            })->toArray();
            return array_only($header, 
                ['id', 'status_to', 'enabled_flg', 'workflow_conditions']
            );
        });
    }
    public function setWorkConditionsAttribute($work_conditions)
    {
        if(is_nullorempty($work_conditions)){
            return $this;
        }
        
        $work_conditions = Condition::getWorkConditions($work_conditions);

        $this->work_condition_headers = $work_conditions;
        
        return $this;
    }

    public function getStatusFromNameAttribute(){
        if(is_numeric($this->status_from)){
            return WorkflowStatus::getEloquentDefault($this->status_from)->status_name;
        }
        elseif($this->status_from == Define::WORKFLOW_START_KEYNAME){
            return Workflow::getEloquentDefault($this->workflow_id)->start_status_name;
        }

        return null;
    }

    public function getCommentTypeAttribute(){
        return $this->getOption('comment_type');
    }
    public function setCommentTypeAttribute($comment_type){
        $this->setOption('comment_type', $comment_type);
        return $this;
    }

    public function getFlowNextTypeAttribute(){
        return $this->getOption('flow_next_type');
    }
    public function setFlowNextTypeAttribute($flow_next_type){
        $this->setOption('flow_next_type', $flow_next_type);
        return $this;
    }

    public function getFlowNextCountAttribute(){
        return $this->getOption('flow_next_count');
    }
    public function setFlowNextCountAttribute($flow_next_count){
        $this->setOption('flow_next_count', $flow_next_count);
        return $this;
    }

    public function getRejectActionAttribute(){
        return $this->getOption('reject_action');
    }
    public function setRejectActionAttribute($reject_action){
        $this->setOption('reject_action', $reject_action);
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

    public function deletingChildren()
    {
        WorkflowAuthority::where('workflow_action_id', $this->id)->delete();
    }

    /**
     * set action authority
     */
    protected function setActionAuthority()
    {
        // target keys
        $keys = [ConditionTypeDetail::USER()->lowerKey(), 
            ConditionTypeDetail::ORGANIZATION()->lowerKey(), 
            ConditionTypeDetail::COLUMN()->lowerKey(),
            ConditionTypeDetail::SYSTEM()->lowerKey(),
        ];
        foreach($keys as $key){
            $ids = array_get($this->work_targets, $key, []);
            $values = collect($ids)->map(function($id) use($key){
                return [
                    'related_id' => $id,
                    'related_type' => $key,
                    'workflow_action_id' => $this->id
                ];
            })->toArray();

            \Schema::insertDelete(SystemTableName::WORKFLOW_AUTHORITY, $values, [
                'dbValueFilter' => function (&$model) use($key) {
                    $model->where('workflow_action_id', $this->id)
                        ->where('related_type', $key);
                },
                'dbDeleteFilter' => function (&$model, $dbValue) use($key) {
                    $model->where('workflow_action_id', $this->id)
                        ->where('related_id', array_get((array)$dbValue, 'related_id'))
                        ->where('related_type', $key);
                },
                'matchFilter' => function ($dbValue, $value) use($key) {
                    return array_get((array)$dbValue, 'workflow_action_id') == $value['workflow_action_id']
                        && array_get((array)$dbValue, 'related_id') == $value['related_id']
                        && array_get((array)$dbValue, 'related_type') == $value['related_type']
                        ;
                },
            ]);
        }
    }

    /**
     * set action conditions
     */
    protected function setActionCondition()
    {
        $this->workflow_condition_headers()->delete();
        foreach($this->work_condition_headers as $work_condition_header){
            $work_condition_header['workflow_action_id'] = $this->id;

            $conditions = array_pull($work_condition_header, 'workflow_conditions', []);
            $header = WorkflowConditionHeader::create($work_condition_header);
            $header->workflow_conditions()->createMany($conditions);
        }
    }

    /**
     * Execute workflow action
     *
     * @param CustomValue $custom_value
     * @param array $data
     * @return void
     */
    public function executeAction($custom_value, $data = []){
        \DB::transaction(function() use($custom_value, $data){
            $morph_type = $custom_value->custom_table->table_name;
            $morph_id = $custom_value->id;

            // update old WorkflowValue
            WorkflowValue::where([
                'morph_type' => $morph_type, 
                'morph_id' => $morph_id, 
                'latest_flg' => true
            ])->update(['latest_flg' => false]);

            $status_to = $this->getStatusToId($custom_value);
            $data = array_merge([
                'workflow_id' => array_get($this, 'workflow_id'),
                'morph_type' => $morph_type,
                'morph_id' => $morph_id,
                'workflow_action_id' => $this->id,
                'workflow_status_id' => $status_to == Define::WORKFLOW_START_KEYNAME ? null : $status_to,
                'latest_flg' => 1
            ], $data);
    
            WorkflowValue::create($data);
        });
    }

    /**
     * Check has workflow authority
     *
     * @param [type] $targetUser
     * @return boolean
     */
    public function hasAuthority($custom_value, $targetUser = null){
        if(!isset($targetUser)){
            $targetUser = \Exment::user()->base_user;
        }

        $workflow_authorities = $this->workflow_authorities;

        foreach($workflow_authorities as $workflow_authority){
            $type = ConditionTypeDetail::getEnum($workflow_authority->related_type);
            switch($type){
                case ConditionTypeDetail::USER:
                    if($workflow_authority->related_id == $targetUser->id){
                        return true;
                    }
                    break;
                case ConditionTypeDetail::ORGANIZATION:
                    $ids = $targetUser->belong_organizations->pluck('id')->toArray();
                    if(in_array($workflow_authority->related_id, $ids)){
                        return true;
                    }
                    break;
                case ConditionTypeDetail::SYSTEM:
                    if($workflow_authority->related_id == WorkflowTargetSystem::CREATED_USER && $custom_value->created_user_id == $targetUser->id){
                        return true;
                    }
                    break;
            }
        }

        return false;
    }

    /**
     * Get users or organzations on this action authority
     *
     * @param [type] $targetUser
     * @return boolean
     */
    public function getAuthorityTargets($custom_value, $orgAsUser = false, $getAsDefine = false){
        $workflow_authorities = $this->workflow_authorities;

        // get users and organizations
        $userIds = [];
        $organizationIds = [];
        $labels = [];

        foreach($workflow_authorities as $workflow_authority){
            $type = ConditionTypeDetail::getEnum($workflow_authority->related_type);
            switch($type){
                case ConditionTypeDetail::USER:
                    $userIds[] = $workflow_authority->related_id;
                    break;
                case ConditionTypeDetail::ORGANIZATION:
                    $organizationIds[] = $workflow_authority->related_id;
                    break;
                case ConditionTypeDetail::SYSTEM:
                    if($getAsDefine){
                        $labels[] = exmtrans('common.' . WorkflowTargetSystem::getEnum($workflow_authority->related_id)->lowerKey());
                        break;
                    }

                    if($workflow_authority->related_id == WorkflowTargetSystem::CREATED_USER){
                        $userIds[] = $custom_value->created_user_id;
                    }
                    break;
            }
        }

        $result = collect();

        if(count($userIds) > 0){
            $result = getModelName(SystemTableName::USER)::find(array_unique($userIds))
                ->merge($result);
        }
        
        if(System::organization_available() && count($organizationIds) > 0){
            $orgs = getModelName(SystemTableName::ORGANIZATION)::find(array_unique($organizationIds));

            if($orgAsUser){
                $result = $orgs->load('users')->users->merge($result);
            }else{
                $result = $orgs->merge($result);
            }
        }

        if($getAsDefine){
            return $result->map(function($r){
                return $r->label;
            })->merge(collect($labels));
        }
        
        return $result;
    }

    /**
     * Get status_to id. Filtering value
     *
     * @return void
     */
    public function getStatusToId($custom_value){
        $next = $this->isActionNext($custom_value);
        
        if($next){
            // get matched condition
            $condition = $this->getMatchedCondtionHeader($custom_value);
            if(is_null($condition)){
                return null;
            }

            return $condition['status_to'];
        }else{
            return $this->status_from;
        }
    }

    /**
     * Filtering condtions. use action condtion
     *
     * @return void
     */
    public function getMatchedCondtionHeader($custom_value){
        if(count($this->workflow_condition_headers) == 0){
            return null;
        }

        foreach($this->workflow_condition_headers as $workflow_condition_header){
            if($workflow_condition_header->isMatchCondition($custom_value)){
                return $workflow_condition_header;
            }
        }

        return null;
    }

    /**
     * Whether this action is next.
     *
     * @param [type] $custom_value
     * @return boolean
     */
    public function isActionNext($custom_value){
        if(($flow_next_count = $this->getOption("flow_next_count", 1)) == 1){
            return true;
        }
        
        // get already execution action user's count
        $action_executed_count = WorkflowValue::where([
            'morph_type' => $custom_value->custom_table->table_name,
            'morph_id' => $custom_value->id,
            'action_executed_flg' => true,
        ])->count();

        return ($flow_next_count - 1 <= $action_executed_count);
    }

    /**
     * Get action modal form
     *
     * @param [type] $custom_value
     * @return void
     */
    public function actionModal($custom_value){
        $custom_table = $custom_value->custom_table;
        $path = admin_urls('data', $custom_table->table_name, $custom_value->id, 'actionClick');
        
        // create form fields
        $form = new ModalForm();
        $form->action($path);

        // get suatus info
        $statusFromName = esc_html(WorkflowStatus::getWorkflowStatusName($this->status_from, $this->workflow));
        $statusTo = $this->getStatusToId($custom_value);
        $statusToName = esc_html(WorkflowStatus::getWorkflowStatusName($statusTo, $this->workflow));

        $form->description('以下のアクションを実行します。');
        
        $form->display('action_name', exmtrans('workflow.action_name'))
            ->default($this->action_name);
        
        $form->display('status_from', exmtrans('common.workflow_status'))
            ->displayText($statusFromName);
    
        $form->display('status_to', exmtrans('workflow.status_to'))
            ->displayText(($statusFromName != $statusToName) ? "<span class='red bold'>$statusToName</span>" : $statusToName);
        
        $next = $this->isActionNext($custom_value);
        $completed = WorkflowStatus::getWorkflowStatusCompleted($statusTo);
        if($next && !$completed){
            // get next actions
            $toActionAuthorities = collect();

            $nextActions = WorkflowStatus::getActionsByFrom($statusTo, $this->workflow, true);
            $nextActions->each(function($workflow_action) use(&$toActionAuthorities, $custom_value){
                    $toActionAuthorities = $workflow_action->getAuthorityTargets($custom_value)
                        ->merge($toActionAuthorities);
                });

            // show target users text
            $select = $nextActions->contains(function($nextAction){
                return $nextAction->getOption('work_target_type') == WorkflowWorkTargetType::ACTION_SELECT;
            });

            // if select, show options
            if($select){
                $options = CustomValueAuthoritable::getUserOrgSelectOptions($custom_table, null, true);
                $form->multipleSelect('next_work_user', exmtrans('workflow.next_work_user'))
                    ->options($options)->required();
            }else{
                // only display
                $form->display('next_work_user', exmtrans('workflow.next_work_user'))
                    ->displayText($toActionAuthorities->map(function($toActionAuthority){
                        return $toActionAuthority->getUrl([
                            'tag' => true,
                            'only_avatar' => true,
                        ]);
                    })->implode(exmtrans('common.separate_word')));
            }
        }

        if($this->comment_type != WorkflowCommentType::NOTUSE){
            $field = $form->textarea('comment_type', exmtrans('common.comment'));
            // check required
            if($this->comment_type == WorkflowCommentType::REQUIRED){
                $field->required();
            }    
        }

        $form->hidden('action_id')->default($this->id);
       
        $form->setWidth(10, 2);

        return getAjaxResponse([
            'body'  => $form->render(),
            'script' => $form->getScript(),
            'title' => $this->action_name
        ]);
    }
    
    protected static function boot() {
        parent::boot();

        static::saved(function ($model) {
            $model->setActionAuthority();
            $model->setActionCondition();
        });
    }
}
