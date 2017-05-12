<?php namespace Nocio\FormStore\Controllers;

use Backend\Classes\Controller;
use BackendMenu;

class Submissions extends Controller
{
    public $implement = [
        'Backend\Behaviors\ListController',
        'Backend\Behaviors\FormController',
        ];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = [
        'nocio.formstore.view_submissions',
        'nocio.formstore.manage_submissions'
    ];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Nocio.FormStore', 'main-menu-item', 'submissions-menu-item');
    }
    
    public function renderDataPreview() {
        $submission = $this->widget->form->model;
        
        $config = $this->makeConfig($submission->form->getFieldsConfig());
        $config->model = $submission;
        
        $formWidget = new \Backend\Widgets\Form($this, $config);
        
        return $formWidget->render(['preview' => true]);
    }
    
    public function renderRelationsPreview() {
        $submission = $this->widget->form->model;
        
        if (! $relations = $submission->form->rels()->get()) {
            return false;
        }
        
        $html = '<hr />';
        foreach($relations as $relation) {
            if (! $rows = $submission->getDataField($relation->field)) {
                continue;
            }
            
            $html .= '<h4>' . $relation->title . '</h4>';
            
            foreach($rows->get() as $row) {
                $config = $this->makeConfig($relation->target->getFieldsConfig());
                $config->model = $row;
        
                $formWidget = new \Backend\Widgets\Form($this, $config);
        
                $html .= $formWidget->render(['preview' => true]);
            }
        }

        return $html;
    }
}
