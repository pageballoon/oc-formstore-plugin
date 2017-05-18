<?php namespace Nocio\FormStore\Models;

use Model;
use Validator;

/**
 * Model
 */
class Form extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $dates = ['opens_at', 'closes_at'];
    
    protected $jsonable = ['validation'];
    
    /*
     * Validation
     */
    public $rules = [
        'title' => 'required',
    ];

    /*
     * Disable timestamps by default.
     * Remove this line if timestamps are defined in the database table.
     */
    public $timestamps = false;

    /**
     * @var string The database table used by the model.
     */
    public $table = 'nocio_formstore_forms';
    
    public $hasMany = [
        'submissions' => 'Nocio\FormStore\Models\Submission',
        'rels' => 'Nocio\FormStore\Models\Relation'
    ];
    
    /**
     * Returns validation errors
     * @param array $data
     * @return array Error messages
     */
    public function getErrors($data) {
        if (empty($this->validation)) {
            return [];
        }
        
        $rules = [];
        foreach($this->validation as $validation) {
            $rules[$validation['field']] = $validation['rule'];
        }
        
        $validator = Validator::make($data, $rules);
        
        if ($validator->fails()) {
            return $validator->messages()->all();
        }
        
        return [];
    }
    
    public function getFieldsConfig() {
        if ($this->fields_config[0] != '$') {
            return '$/' . str_replace('\\', '/', strtolower($this->model)) . '/' . $this->fields_config;
        }
        
        return $this->fields_config;
    }

    public function submittedMaximum($submitter) {
        if ($this->max_per_user == -1) {
            return false;
        }

        return $submitter->submissions()->byForm($this)->active()->count() >= $this->max_per_user;
    }
    
}