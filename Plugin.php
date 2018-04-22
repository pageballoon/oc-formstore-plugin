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
            'nocio.formstore::mail.login' => 'FormStore login mail',
            'nocio.formstore::mail.submission_notice' => 'FormStore submission notice'
        ];
    }

    public function register()
    {
        /*
         * Compatability with RainLab.Notify
         */
        $this->bindNotificationEvents();
    }

    /**
     * Registers notification rules
     * @return array
     */
    public function registerNotificationRules()
    {
        return [
            'events' => [
                \Nocio\FormStore\NotifyRules\SubmissionCreatedEvent::class,
                \Nocio\FormStore\NotifyRules\SubmissionWithdrawnEvent::class,
                \Nocio\FormStore\NotifyRules\SubmissionSubmittedEvent::class
            ],
            'actions' => [],
            'conditions' => [],
            'groups' => [
                'formstore' => [
                    'label' => 'FormStore',
                    'icon' => 'icon-database'
                ],
            ]
        ];
    }

    protected function bindNotificationEvents()
    {

        if (!class_exists(\RainLab\Notify\Classes\Notifier::class)) {
            return;
        }

        \RainLab\Notify\Classes\Notifier::bindEvents([
            'nocio.formstore.create' => \Nocio\FormStore\NotifyRules\SubmissionCreatedEvent::class,
            'nocio.formstore.withdraw' => \Nocio\FormStore\NotifyRules\SubmissionWithdrawnEvent::class,
            'nocio.formstore.submit' => \Nocio\FormStore\NotifyRules\SubmissionSubmittedEvent::class
        ]);
    }
}
