<?php namespace nocio\FormStore\Controllers;

use Backend\Classes\Controller;
use BackendMenu;

class Submissions extends Controller
{
    public $implement = ['Backend\Behaviors\ListController','Backend\Behaviors\FormController','Backend\Behaviors\ReorderController'];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';
    public $reorderConfig = 'config_reorder.yaml';
    public $relationConfig = 'config_relation.yaml';

    public $requiredPermissions = [
        'nocio.formstore.view_submissions',
        'nocio.formstore.manage_submissions'
    ];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('nocio.FormStore', 'main-menu-item', 'submissions-menu-item');
    }
}
