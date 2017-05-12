<?php namespace Nocio\FormStore\Traits;

use Input;
use Validator;
use Mail;
use Nocio\FormStore\Models\Submission;
use Nocio\FormStore\Models\Form;
use Nocio\FormStore\Models\Submitter;
use Response;
use Cookie;
use Redirect;
use Backend\Classes\WidgetManager;

trait AjaxController {
    
    use \System\Traits\ConfigMaker;
    
    public $widget;
    
    /**
     * Sends an authentication email to the user
     * @return October AJAX response
     */
    public function onLogin() {

        $validator = Validator::make(Input::all(), ['email' => 'required|email']);
        if ($validator->fails()) {
            // @todo: make dynamic url
            return Redirect::to('/contribute/article')->withErrors($validator);
        }
        
        $submitter = Submitter::firstOrNew(['email' => Input::get('email')]);
        
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
    public function onSignout() {
        Cookie::queue(Cookie::forget('formstore_identifier'));
        Cookie::queue(Cookie::forget('formstore_token'));
    }
    
    /** 
     * Creates a new form submission
     * @return boolean
     */
    public function onCreate() {
        
        if ( ! in_array($form_id = Input::get('form'), $this->property('forms'))
                || ! $form = Form::find($form_id)) {
            return false;
        }
        
        if ($this->submitter->submittedMaximum($form)) {
            return false;
        }
        
        // Insert data
        $model = '\\' . $form->model;
        $this->deactivateModelValidation($model);
        $data = $model::create();
        
        $submission = new Submission();
        $submission->form_id = $form_id;
        $submission->submitter_id = $this->submitter->id;
        $submission->data_id = $data->id;
        $submission->data_type = $form->model;
        $submission->save();
        
        return $this->refreshForm($form);
    }
    
    /**
     * Registers backend widgets for frontend use
     */
    public function loadBackendFormWidgets() {
        $widgets = [
            'Backend\FormWidgets\DatePicker' => [
                'label' => 'Date picker',
                'code'  => 'datepicker'
            ],
            'Backend\FormWidgets\RichEditor' => [
                'label' => 'Rich editor',
                'code'  => 'richeditor'
            ],
            'Backend\FormWidgets\MarkdownEditor' => [
                'label' => 'MarkdownEditor',
                'code'  => 'markdowneditor'
            ],
            // Custom file upload for frontend use
            'Nocio\FormStore\Widgets\FrontendFileUpload' => [
                'label' => 'FileUpload',
                'code'  => 'fileupload'
            ],
        ];
        
        foreach ($widgets as $className => $widgetInfo) {
            WidgetManager::instance()->registerFormWidget($className, $widgetInfo);
        }
    }
    
    public function editor($form, $model) {
        $config = $this->makeConfig($form->getFieldsConfig());
        $config->arrayName = 'data';
        $config->alias = $this->alias;
        $config->model = $model;
        
        $formWidget = new \Backend\Widgets\Form($this, $config);
        
        $this->loadBackendFormWidgets();
        
        $html = $formWidget->render(['preview' => ! $this->submission->isWritable()]);
        
        return [
            '#app' => $this->renderPartial('@app/edit', 
                ['form' => $html, 'data_id' => $model->id])
        ];
    }
    
    /**
     * Edits a submission
     * @return type
     */
    public function onEdit() {   
        return $this->editor($this->submission->form, $this->submission->data);
    }
    
    /**
     * Edits a relation
     * @return boolean
     */
    public function onEditRelated() {
        if (! $model = $this->submission->getDataField($this->relation->field)->find(Input('data_id'))) {
           return false; 
        }
        
        return $this->editor($this->relation->target, $this->model);
    }
    
    public function onSave() {
        if (! $this->submission->isWritable()) {
            return;
        }
        
        if (Input::get('relation')) {
            $model = $this->submission->getDataField($this->relation->field)->find(Input('data_id'));
        } else {
            $model = $this->submission->data;
        }
        
        if (! $data = Input::get('data')) {
            return false;
        }
        
        $this->deactivateModelValidation($model);
        if (! $model->update($data)) {
            return false; // @todo: error handling
        }
        
        if (Input::get('close')) {
            return $this->onCloseForm();
        }
    }  
    
    /**
     * Cancels the submission
     */
    public function onCancelSubmission() {
        $this->submission->withdraw();
        return $this->refreshForm();
    }
    
    /**
     * Submits the submission
     */
    public function onSubmitSubmission() {
        if ($this->submission->submit()) {
            return $this->refreshForm();  
        }
    }
    
    /**
     * Closes the form
     * @return type
     */
    public function onCloseForm() {
        return [
            '#app' => $this->renderPartial('@app/forms')
        ];
    }
    

    public function onRemoveRelated() {
        if (! $this->submission->isWritable()) {
            return;
        }
        
        if (! $model = $this->submission->getDataField($this->relation->field)->find(Input('data_id'))) {
           return false; 
        }
        
        $model->delete();
        
        return $this->refreshForm();
    }
    
    public function onCreateRelated() {
        if (! $this->submission->isWritable()) {
            return;
        }
        
        $form = $this->relation->target;
        $field = $this->submission->getDataField($this->relation->field);
        
        if($form->max_per_user != -1 && $field->count() >= $form->max_per_user) {
            return Response::json('Maximum number of ' . $form->title . ' reached.', 400);
        }
        
        $model = '\\' . $form->model;
        $this->deactivateModelValidation($model);
        $child_model = $model::create();
        
        $field->add($child_model);
        
        return $this->refreshForm();
    }
    
    /**
     * Returns a refreshed relation
     * @return type
     */
    public function refreshRelation() {
        return [
            "#fs-form-{$this->submission->id}-relation-{$this->relation->id}" =>
            $this->renderPartial('@app/relation')
        ];
    }
    
    /**
     * Returns a refreshed from
     * @param Model $form Optional model
     * @return mixed
     */
    private function refreshForm($form = null) {
        if (! is_object($form)) {
            if (! $form = $this->submission->form()->first()) {
                return false;
            }
        }
        
        return [
            '#fs-form-' . $form->id => 
                $this->renderPartial('@app/form', ['form' => $form])
        ];
    }
    
    /**
     * Deactivates the validation of a given model
     * @param mixed $model
     */
    private function deactivateModelValidation($model) {
        $closure = function($model) {
            if (isset($model->rules)) {
                $model->rules = [];
            }
        };
        
        if (is_string($model)) {
            $model::extend($closure);
        }
        
        if (is_object($model)) {
            $closure($model);
        }
    }
    
}