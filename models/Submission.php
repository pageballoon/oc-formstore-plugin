<?php namespace nocio\FormStore\Models;

use Model;
use Event;
use Backend;

/**
 * Model
 */
class Submission extends Model
{
    use \October\Rain\Database\Traits\Validation;

    protected $fillable = ['form_id'];

    /*
     * Validation
     */
    public $rules = [];
    
    public $dates = ['updated_at', 'created_at', 'treated'];

    public $timestamps = true;

    /**
     * @var string The database table used by the model.
     */
    public $table = 'nocio_formstore_submissions';

    public $belongsTo = [
      'form' => 'nocio\FormStore\Models\Form',
      'submitter' => 'nocio\FormStore\Models\Submitter'
    ];

    public $morphTo = [
        'data' => []
    ];
    
    /**
     * Status codes
     * @var int
     */
    protected $STATUS_NONE = 0;
    protected $STATUS_CANCELLED = 1;
    protected $STATUS_SUBMITTED = 2;


    /**
     * Determines whether submission is writable
     */
    public function isWritable() {
        if ($this->status > 0) {
            return false;
        }
        
        $closes_at = $this->form->closes_at;
        
        if (! is_null($closes_at) && $closes_at->isPast()) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Return data value
     * @param string $field
     * @return mixed Relation query builder or field value
     */
    public function getDataField($field) {
        if (! $this->data()->first()) {
            return false;
        }
        
        if (! $this->data->hasRelation($field)) {
            return false;
        }
        
        return $this->data->$field();
    }
    
    public function url($action = 'preview') {
        return Backend::url("nocio/formstore/submissions/$action/{$this->id}");
    }
    
    /**
     * Cancels the submission
     */
    public function withdraw() {
        if ($this->status != $this->STATUS_NONE) {
            return false;
        }
        
        Event::fire('nocio.formstore.withdraw', [$this]);
        
        // Remove data
        foreach ($this->form->rels()->get() as $relation) {
            if (! $field = $this->getDataField($relation->field)) {
                continue;
            }
            
            foreach($field->get() as $row) {
                $row->delete();
            }
            
            $field->detach();
        }
        $this->data()->delete();
        
        // Set to cancelled
        $this->status = $this->STATUS_CANCELLED;
        $this->treated = date('Y-m-d H:i:s');
        $this->save();
        
        return true;
    }
    
    /**
     * Submits the submission
     */
    public function submit() {
        if ($this->status != $this->STATUS_NONE) {
            return false;
        }
        
        if (! empty($this->getErrors())) {
            return false;
        }
        
        Event::fire('nocio.formstore.submit', [$this]);
        
        $this->status = $this->STATUS_SUBMITTED;
        $this->treated = date('Y-m-d H:i:s');
        $this->save();
        
        return true;
    }
    
    /**
     * Generates a title from the data
     * @param type $data
     * @param type $suffix
     * @return type
     */
    public function title($suffix = '') {
        $title = '#' . $this->id;
        
        if (! $data = $this->data()->first()) {
            $title = ' [Removed]';
        }
        
        foreach(['name', 'title'] as $field) {
            if (! empty($data->$field) && strlen($data->field) > 3) {
                $title = ' ' . $data->$field;
            }
        }
        
        return $title . $suffix;
    }
    
    /**
     * Returns submission's error
     * @param deep If true, relations will be validated as well
     * @return type
     */
    public function getErrors($deep = true) {
        if ($this->status != 0) {
            return [];
        }
        
        if (! $this->data()->first()) {
            return [];
        }
        
        $errors = $this->form->getErrors($this->data->toArray());
        
        if ($deep) {
            foreach($this->form->rels()->get() as $relation) {
                $rows = $this->getDataField($relation->field)->get();
                if (count($rows) < $relation->required_min) {
                    $errors[] = 'You have to add more ' . $relation->target->title . ' (' . $relation->required_min . ' required)';
                }
                
                $e = [];
                foreach($rows as $row) {
                    $e = array_merge($relation->target->getErrors($row->toArray()), $e);
                }
                
                if (! empty($e)) {
                    $errors[] = $relation->target->title . ' is not ready ('. count($e) . ' issues, see below)';
                }
            }
        }

        return $errors;
    }

    /**
     * Scope definition to filter down submission by form
     * @param type $query
     * @param type $form
     * @return QueryBuilder
     */
    public function scopeByForm($query, $form) {
        if (is_numeric($form)) {
            $form_id = (int) $form;
        }
        
        if (is_object($form)) {
            $form_id = $form->id;
        }
        
        if (is_array($form) && isset($form['id'])) {
            $form_id = $form['id'];
        }
        
        return $query->where('form_id', $form_id);
    }
    
    /**
     * Filters down by state
     * @param type $query
     * @param type $state
     * @param type $operator
     * @return type
     */
    public function scopeInState($query, $state, $operator = '=') {
        return $query->where('status', $operator, $state);
    }
    
    /**
     * Filters down active fields
     * @param type $query
     * @return type
     */
    public function scopeActive($query) {
        return $query->where('status', '!=', $this->STATUS_CANCELLED);
    }

}
