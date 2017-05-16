<?php namespace Nocio\FormStore;

use System\Classes\PluginBase;

class Plugin extends PluginBase
{
    /**
     * Component details
     * @return array
     */
    public function componentDetails()
    {
        return [
            'name'        => 'nocio.formstore::lang.plugin.name',
            'description' => 'nocio.formstore::lang.plugin.description',
            'icon'        => 'icon-paperclip',
            'iconSvg'     => 'assets\images\logo.svg',
            'homepage'    => 'https://github.com/nocio/oc-formstore-plugin'
        ];
    }
    
    /**
     * Registers components
     * @return array
     */
    public function registerComponents()
    {
        return [
            'Nocio\FormStore\Components\Manager'   => 'formstoreManager',
            'Nocio\FormStore\Components\Countdown' => 'formstoreCountdown'
        ];
    }
    
    /**
     * Registers mail templates
     * @return array
     */
    public function registerMailTemplates()
    {
        return [
            'nocio.formstore::mail.login' => 'FormStore Manager login mail'
        ];
    }
}
