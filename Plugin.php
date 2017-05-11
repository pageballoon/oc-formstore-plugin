<?php namespace Nocio\FormStore;

use System\Classes\PluginBase;

class Plugin extends PluginBase
{

    public function componentDetails()
    {
        return [
            'name'        => 'Submission',
            'description' => 'Manages submissions'
        ];
    }
    
    public function registerComponents()
    {
        return [
            'Nocio\FormStore\Components\Manager' => 'formstoreManager',
            'Nocio\FormStore\Components\Countdown' => 'formstoreCountdown'
        ];
    }
    
    public function registerMailTemplates()
    {
        return [
            'nocio.formstore::mail.login' => 'FormStore Manager login mail'
        ];
    }
    
    public function registerFormWidgets()
    {
        return [
            
            // @todo: remove this workaround of registering widgets
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
            
            // Register frontend fileupload
            'Nocio\FormStore\Widgets\FrontendFileUpload' => [
                'label' => 'FrontendFileUpload',
                'code'  => 'frontendfileupload'
            ],
        ];
    }
}
