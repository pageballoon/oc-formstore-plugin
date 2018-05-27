<?php

namespace Nocio\FormStore\NotifyRules;

use Nocio\FormStore\Classes\SubmissionEventBase;

class SubmissionCreatedEvent extends SubmissionEventBase
{

    /**
     * Returns information about this event, including name and description.
     */
    public function eventDetails()
    {
        return [
            'name'        => 'Submission created',
            'description' => 'A submission is created',
            'group'       => 'formstore'
        ];
    }

}