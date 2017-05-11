<?php namespace Nocio\FormStore\Models;

use Model;

/**
 * Model
 */
class Relation extends Model
{
    use \October\Rain\Database\Traits\Validation;
    
    /*
     * Disable timestamps by default.
     * Remove this line if timestamps are defined in the database table.
     */
    public $timestamps = false;

    /*
     * Validation
     */
    public $rules = [
    ];
    
    public $belongsTo = [
        'form' => 'Nocio\FormStore\Models\Form', // inverse of Form::relations()
        'target' => 'Nocio\FormStore\Models\Form'
    ];

    /**
     * @var string The database table used by the model.
     */
    public $table = 'nocio_formstore_relations';
}