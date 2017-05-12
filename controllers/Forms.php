<?php namespace Nocio\FormStore\Controllers;

use Backend\Classes\Controller;
use BackendMenu;

class Forms extends Controller
{
    public $implement = [
        'Backend\Behaviors\ListController',
        'Backend\Behaviors\FormController',
        'Backend\Behaviors\ReorderController',
        'Backend\Behaviors\RelationController',
        ];
    
    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';
    public $reorderConfig = 'config_reorder.yaml';
    public $relationConfig = 'config_relation.yaml';

    public $requiredPermissions = [
        'nocio.formstore.manage_forms' 
    ];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Nocio.FormStore', 'main-menu-item', 'forms-menu-item');
    }
}