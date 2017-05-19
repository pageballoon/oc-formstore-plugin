<?php namespace Nocio\FormStore\Components;

use Cms\Classes\ComponentBase;
use Nocio\FormStore\Models\Form;
use Nocio\FormStore\Models\Submitter;
use Nocio\FormStore\Traits\ManagesUploads;
use Nocio\FormStore\Traits\AjaxController;
use Input;
use Cookie;
use Redirect;
use Request;
use Event;
use Validator;
use Mail;
use Response;

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
     * Contains the rendered component
     * @var string
     */
    public $app = null;

    /**
     * Authenticates the submitter against the request
     */
    public function authenticate() {

        /** Extensionality */
        if ($auth = Event::fire('nocio.formstore.authenticate', [$this->alias], true)) {;
            $this->submitter = $auth[0];
            return $this->authenticated = $auth[1];
        }

        $this->submitter = Submitter::byId(Cookie::get('formstore_identifier'))->first();
        
        if ($this->submitter) {
            $this->authenticated = $this->submitter->authenticated(Cookie::get('formstore_token'));
        }
        
        return $this->authenticated;
    }
    
    /**
     * Initialise plugin and parse request
     */
    public function init() {

        if ($this->authenticate()) {

            // Parse request information

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
            'embedded' => [
                'title' => 'Embed form',
                'description' => 'If activated the manager will be embedded into the page rather than replacing it',
                'type' => 'checkbox',
                'default' => 0
            ],
            'open_for_registration' => [
                'title' => 'Open for registration',
                'description' => 'If disabled, only existing submitters can access the form',
                'type' => 'checkbox',
                'default' => 1
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
        if (! $this->submitter->authenticated(Cookie::get('formstore_token'))) {
            if (! $this->submitter->authenticated(Input::get('token'))) {
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
            $this->addCss('/modules/system/assets/ui/storm.css');
            $this->addCss('/plugins/nocio/formstore/assets/css/uploader.css');
            $this->addJs('/modules/system/assets/ui/storm-min.js');
            $this->addJs('/plugins/nocio/formstore/assets/vendor/dropzone/dropzone.js');
            $this->addJs('/plugins/nocio/formstore/assets/js/uploader.js');

            if ($this->property('embedded')) {
                $this->app = $this->renderPartial('@app/index');
            } else {
                return $this->renderPartial('@app/wrapper');
            }

        } else {
            /** Extensionality */
            if ($response = Event::fire('nocio.formstore.not_authenticated', [$this->alias], true)) {;
                return $response;
            }
        }
    }

    /**
     * Sends an authentication email to the user
     * @return October AJAX response
     */
    public function onLogin() {

        // Validate the email
        $validator = Validator::make(Input::all(), ['email' => 'required|email']);
        if ($validator->fails()) {
            return Redirect::to($this->currentPageUrl())->withErrors($validator);
        }

        // Find or create submitter
        if ($this->property('open_for_registration')) {
            $submitter = Submitter::firstOrNew(['email' => Input::get('email')]);
        } else {
            if (! $submitter = Submitter::where(['email' => Input::get('email')])->first()) {
                return Response::json('Sorry, this email is not registered.', 403);
            }
        }

        // First-time generation?
        $renew = ! isset($submitter->id);

        // Generate a new token
        $identifier = $submitter->generateIdentifier();
        $token = $submitter->generateToken();
        $submitter->save();

        Mail::sendTo(
            $submitter->email,
            $this->property('login_mail_template'),
            ['token' => $token, 'identifier' => $identifier,
                'base_url' => $this->currentPageUrl(), 'renew' => $renew ]
        );

        return [
            '#fs-login-form' => $this->renderPartial('@authenticate/success',
                ['base_url' => $this->currentPageUrl()])
        ];
    }

    /**
     * Signs out
     */
    public function onLogout() {

        /** Extensionality */
        if ($response = Event::fire('nocio.formstore.logout', [$this->alias], true)) {
            return $response;
        }

        Cookie::queue(Cookie::forget('formstore_identifier'));
        Cookie::queue(Cookie::forget('formstore_token'));
    }
    
}
