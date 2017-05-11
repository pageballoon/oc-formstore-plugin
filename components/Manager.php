<?php namespace nocio\FormStore\Components;

use Cms\Classes\ComponentBase;
use nocio\FormStore\Models\Form;
use nocio\FormStore\Models\Submitter;
use nocio\FormStore\Traits\ManagesUploads;
use nocio\FormStore\Traits\AjaxController;
use Input;
use Cookie;
use Redirect;
use Request;

class Manager extends ComponentBase  {
    
    use AjaxController;
    use ManagesUploads;
    
    /**
     * The submitter model (null if not authenticated)
     * @var mixed
     */
    public $submitter = null;
    
    /**
     * The authentication state
     * @var boolean
     */
    public $authenticated = null;
    
    /**
     * The submission model
     * @var type 
     */
    public $submission = null;
    
    /**
     * The relation model
     * @var type 
     */
    public $relation = null;
    
    /**
     * Data model
     * @var Model
     */
    public $model = null;

    /**
     * Authenticates the submitter against the request
     */
    public function authenticate() {
        $this->submitter = Submitter::byId(Cookie::get('formstore_identifier'))->first();
        
        if ($this->submitter) {
            $this->authenticated = $this->submitter->authenticate(Cookie::get('formstore_token'));
        }
        
        return $this->authenticated;
    }
    
    /**
     * Initialise plugin and parse request
     */
    public function init() {
        
        // Middleware
        if ( ! $this->authenticate() ) {
            
            // Require authoritzation for AJAX request, except login
            if (Request::ajax() && Request::header('X-OCTOBER-REQUEST-HANDLER') != 'onLogin') {
                // Unauthenticated
                throw new \Exception('Not authorized');
            }
            
        }

        // Request information
        
        if ($submission_id = Input::get('submission')) {
            $this->submission = $this->submitter->submissions()->find($submission_id);
            $this->model = $this->submission->data()->first();
        }
        
        if ($relation_id = Input::get('relation')) {
            $this->relation = $this->submission->form->rels()->find($relation_id);
            
            if ($data_id = Input::get('data_id')) {
                $this->model = $this->submission->getDataField($this->relation->field)->find($data_id);
            }
        }

    }
    
    /**
     * Register component details
     * @return array
     */
    public function componentDetails()
    {
        return [
            'name'        => 'Form Component',
            'description' => 'Allows to start/manage form submissions'
        ];
    }

    /**
     * Register properties
     * @return array
     */
    public function defineProperties()
    {
        return [
            'forms' => [
                'title'             => 'Forms',
                'description'       => 'Forms the user is allowed to access',
                'type'              => 'set',
                'items'             => Form::lists('title', 'id'),
                'default'           => []
            ],
            'login_mail_template' => [
                'title' => 'onLogin Mail template',
                'description' => 'The mail template that will be send to the user on login',
                'type' => 'string',
                'default' => 'nocio.formstore::mail.login'
            ],
            'save_warning' => [
                'title' => 'Display save hint',
                'description' => 'Displays a warning to save content regularly',
                'type' => 'checkbox',
                'default' => 0
            ]
        ];
    }
    
    /**
     * Returns a list of activated forms
     * @return type
     */
    public function getForms() {
        return Form::findMany($this->property('forms'));
    }

    /**
     * Authenticates the user based on cookie and request data
     * @return boolean
     */
    private function login() {
        
        // Validate identifier, $_GET beats Cookie
        $identifier = Input::get('id', Cookie::get('formstore_identifier'));
        if (! $this->submitter = Submitter::byId($identifier)->first()) {
           return false;
        }
        
        // Check against submitter model, Cookie beats $_GET
        if (! $this->submitter->authenticate(Cookie::get('formstore_token'))) {
            if (! $this->submitter->authenticate(Input::get('token'))) {
                return false;
            }
        }
        
        $new_token = $this->submitter->generateToken();
        $this->submitter->save();
        $minutes = 60 * 24 * 30; // = 1 month
        Cookie::queue('formstore_identifier', $identifier, $minutes);
        Cookie::queue('formstore_token', $new_token, $minutes);
        
        return $this->authenticated = true;
    }
    
    /**
     * Renders the frontend
     * @return mixed
     */
    public function onRun() {
        
        // Authenticate via token
        if (Input::get('id') || Input::get('token')) {
            $this->login();
            return Redirect::to($this->currentPageUrl());
        }
        
        // Run application
        if ($this->authenticated) {
            
            // Handle file upload requests
            if ($handler = $this->processUploads()) {
                return $handler;
            }
            
            // Render frontend
            return $this->renderPartial('@app/main');
        }
        
        // else: default login partial will be rendered
    }
    
}
