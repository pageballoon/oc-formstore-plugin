<?php namespace Nocio\FormStore\NotifyRules;

use RainLab\Notify\Classes\ModelAttributesConditionBase;
use ApplicationException;

class SubmissionAttributeCondition extends ModelAttributesConditionBase
{
    protected $modelClass = \Nocio\FormStore\Models\Submission::class;

    public function getGroupingTitle()
    {
        return 'Submission attribute';
    }

    public function getTitle()
    {
        return 'Submission attribute';
    }

    /**
     * Checks whether the condition is TRUE for specified parameters
     * @param array $params Specifies a list of parameters as an associative array.
     * @return bool
     */
    public function isTrue(&$params)
    {
        $submission = (object) $params;
        $submission->form_id = $submission->form->id;
        return parent::evalIsTrue($submission);
    }
}