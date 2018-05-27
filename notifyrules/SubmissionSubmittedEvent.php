<?php

namespace Nocio\FormStore\NotifyRules;

use Nocio\FormStore\Classes\SubmissionEventBase;

class SubmissionSubmittedEvent extends SubmissionEventBase
{

    /**
     * Returns information about this event, including name and description.
     */
    public function eventDetails()
    {
        return [
            'name'        => 'Submission submitted',
            'description' => 'A submission is submitted',
            'group'       => 'formstore'
        ];
    }

}