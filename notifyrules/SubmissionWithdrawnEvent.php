<?php

namespace Nocio\FormStore\NotifyRules;

use Nocio\FormStore\Classes\SubmissionEventBase;

class SubmissionWithdrawnEvent extends SubmissionEventBase
{

    /**
     * Returns information about this event, including name and description.
     */
    public function eventDetails()
    {
        return [
            'name'        => 'Submission withdrawn',
            'description' => 'A submission is withdrawn',
            'group'       => 'formstore'
        ];
    }

}