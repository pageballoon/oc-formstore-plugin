<?php

namespace Nocio\FormStore\Classes;


class SubmissionEventBase extends \RainLab\Notify\Classes\EventBase
{

    /**
     * @var array Local conditions supported by this event.
     */
    public $conditions = [
        \Nocio\FormStore\NotifyRules\SubmissionAttributeCondition::class
    ];

    /**
     * Defines the usable parameters provided by this class.
     */
    public function defineParams()
    {
        return [
            'id' => [
                'title' => 'ID',
                'label' => 'ID of the submission',
            ],
            'status' => [
                'title' => 'Status',
                'label' => 'The status of the submission'
            ],
            'treated' => [
                'title' => 'Last changed',
                'label' => 'Date of last status change of the submission'
            ],
            'submitter' => [
                'title' => 'Submitter',
                'label' => 'The submitter of the submission (aliased as sender)'
            ],
            'form' => [
                'title' => 'Form',
                'label' => 'The submission form'
            ],
            'submission' => [
                'title' => 'Submission',
                'label' => 'The submission object'
            ]
        ];
    }

    public static function makeParamsFromEvent(array $args, $eventName = null)
    {
        $submission = array_get($args, 0);

        $params = $submission->getNotificationVars();
        $params['submission'] = $submission;

        return $params;
    }

}