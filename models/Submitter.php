<?php namespace nocio\FormStore\Models;

use Model;
use Hash;

/**
 * Model
 */
class Submitter extends Model
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
        'email' => 'required|email'
    ];
    
    public $hasMany = [
        'submissions' => 'nocio\FormStore\Models\Submission'
    ];
    
    public $fillable = [
        'email'
    ];

    /**
     * @var string The database table used by the model.
     */
    public $table = 'nocio_formstore_submitters';
    

    /** 
     * Scope to filter by identifier
     * @param type $query
     * @param type $identifier
     * @return type
     */
    public function scopeById($query, $identifier) {
        return $query->where('identifier', $identifier);
    }
    
    /**
     * Validates a token against the hashed token value
     * @param type $token
     * @return type
     */
    public function authenticate($token) {
        return Hash::check($token, $this->token);
    }
    
    /**
     * Generates and returns the identifier
     * @return string(24)
     */
    public function generateIdentifier() {
        if (is_null($this->identifier) || strlen($this->identifier) <= 1) {
            $this->identifier = str_random(24);
        }
        
        return $this->identifier;
    }

    /**
     * Returns a random access token and binds its hashed value to the model
     * @return string(64)
     */
    public function generateToken() {
        $token = str_random(64);

        $this->token = Hash::make($token);

        return $token;
    }
    
    public function submittedMaximum($form) {
        if ($form->max_per_user == -1) {
            return false;
        } 
        
        return $this->submissions()->byForm($form)->active()->count() >= $form->max_per_user;
    }
}