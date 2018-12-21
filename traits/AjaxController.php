<?php namespace Nocio\FormStore\Traits;

use Input;
use Flash;
use Validator;
use Mail;
use Event;
use Nocio\FormStore\Models\Submission;
use Nocio\FormStore\Models\Form;
use Response;
use Cookie;
use Redirect;
use Backend\Classes\WidgetManager;
use October\Rain\Exception\ApplicationException;

trait AjaxController {

    use \System\Traits\ConfigMaker;

    public $widget;

    public function formGetWidget()
    {
        return $this->widget;
    }

    /**
     * Auth middleware
     */
    public function middleware() {

        if ( ! $this->authenticate() ) {
            return 'Not authorized';
        }

    }


    /**
     * Creates a new form submission
     * @return boolean
     */
    public function onCreate() {

        if ($response = $this->middleware()) {
            return $response;
        }

        if ( ! in_array($form_id = Input::get('form'), $this->property('forms'))
                || ! $form = Form::find($form_id)) {
            return false;
        }

        if ($form->submittedMaximum($this->submitter)) {
            return false;
        }

        // Insert data
        $model = '\\' . $form->model;
        if (! class_exists($model)) {
            throw new ApplicationException(
                "Error: The form's model could not be found.\n".
                "\t Please check the form settings."
            );
        }

        try {
            $this->deactivateModelValidation($model);
            $data = $model::create();
        } catch (\Exception $e) {
            throw new ApplicationException(
                "Error: The form's model could not be created.\n".
                "\t Please check the model definition.\n\n".
                "\t Ensure that:\n\n".
                "\t - the model can created empty/is nullable\n".
                "\t - the fields support mass-assignment\n"
            );
        }

        $submission = new Submission();
        $submission->form_id = $form_id;
        $submission->submitter_id = $this->submitter->id;
        $submission->data_id = $data->id;
        $submission->data_type = $form->model;
        $submission->treated = date('Y-m-d H:i:s');
        $submission->save();

        Event::fire('nocio.formstore.create', [$submission]);

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
            'Backend\FormWidgets\Relation' => [
                'label' => 'Relation',
                'code'  => 'relation'
            ],
            'Backend\FormWidgets\MarkdownEditor' => [
                'label' => 'MarkdownEditor',
                'code'  => 'markdown'
            ],
            // Custom file upload for frontend use
            'Nocio\FormStore\Widgets\FrontendFileUpload' => [
                'label' => 'FileUpload',
                'code'  => 'fileupload'
            ]
        ];

        Event::fire('nocio.formstore.widgets', [&$widgets, $this->alias]);

        foreach ($widgets as $className => $widgetInfo) {
            WidgetManager::instance()->registerFormWidget($className, $widgetInfo);
        }
    }

    public function editor($form, $model) {
        $config = $this->makeConfig($form->getFieldsConfig());
        $config->arrayName = 'data';
        $config->alias = $this->alias;
        $config->model = $model;

        $this->widget = new \Backend\Widgets\Form($this, $config);

        $this->loadBackendFormWidgets();

        $html = $this->widget->render(['preview' => ! $this->submission->isWritable()]);

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
        if ($response = $this->middleware()) {
            return $response;
        }

        return $this->editor($this->submission->form, $this->submission->data);
    }

    /**
     * Edits a relation
     * @return boolean
     */
    public function onEditRelated() {
        if ($response = $this->middleware()) {
            return $response;
        }

        if (! $model = $this->submission->getDataField($this->relation->field)->find(Input('data_id'))) {
           return false;
        }

        return $this->editor($this->relation->target, $this->model);
    }

    public function onSave() {
        if ($response = $this->middleware()) {
            return $response;
        }

        if (! $this->submission->isWritable()) {
            throw new ApplicationException("Error: The form cannot be edited.");
        }

        if (Input::get('relation')) {
            $model = $this->submission->getDataField($this->relation->field)->find(Input('data_id'));
        } else {
            $model = $this->submission->data;
        }

        if (! $data = Input::get('data')) {
            throw new ApplicationException("Error: The form could not be saved.");
        }

        // Resolve belongsTo relations
        foreach($model->belongsTo as $name => $definition) {
            if (! isset($data[$name])) {
                continue;
            }

            $key = isset($definition['key']) ? $definition['key'] : $name . '_id';
            $data[$key] = (int) $data[$name];
            unset($data[$name]);
        }

        $this->deactivateModelValidation($model);
        if (! $model->update($data)) {
            throw new ApplicationException("Error: The form could not be saved.");
        }

        if (Input::get('close')) {
            return $this->onCloseForm();
        }
    }

    /**
     * Cancels the submission
     */
    public function onCancelSubmission() {
        if ($response = $this->middleware()) {
            return $response;
        }

        $this->submission->withdraw($this->alias);
        return $this->refreshForm();
    }

    /**
     * Submits the submission
     */
    public function onSubmitSubmission() {
        if ($response = $this->middleware()) {
            return $response;
        }

        if ($this->submission->submit($this->alias)) {
            return $this->refreshForm();
        }
    }

    /**
     * Closes the form
     * @return type
     */
    public function onCloseForm() {
        if ($response = $this->middleware()) {
            return $response;
        }

        return [
            '#app' => $this->renderPartial('@app/index')
        ];
    }


    public function onRemoveRelated() {
        if ($response = $this->middleware()) {
            return $response;
        }

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
        if ($response = $this->middleware()) {
            return $response;
        }

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
        if ($response = $this->middleware()) {
            return $response;
        }

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
