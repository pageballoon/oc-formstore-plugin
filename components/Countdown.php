<?php namespace Nocio\FormStore\Components;

use Cms\Classes\ComponentBase;
use Nocio\FormStore\Models\Form;
use Carbon\Carbon;

class Countdown extends ComponentBase  {

    public $opens;
    public $closes;
    public $liftoff;
    public $open;
    public $closed;

    public function componentDetails()
    {
        return [
            'name'        => 'Form Countdown Component',
            'description' => 'Displays form opening information'
        ];
    }

    public function defineProperties()
    {
        return [
            'form' => [
                'title'             => 'Form',
                'description'       => 'Form to display',
                'type'              => 'dropdown',
                'placeholder'       => 'Select a form',
                'default'           => 1,
            ]
        ];
    }

    public function getFormOptions()
    {
        return Form::lists('title', 'id');
    }

    public function distance($date) {
        $now = Carbon::now();
        $result = new \stdClass();
        
        if (! $date) {
            $result->days = $result->hours = $result->minutes = 0;
            return $result;
        }

        $result->date = $date;
        $result->days = $now->diffInDays($date);
        $now = $now->addDays($now->diffInDays($date, false));
        $result->hours = $now->diffInHours($date);
        $now = $now->addHours($now->diffInHours($date, false));
        $result->minutes = $now->diffInMinutes($date);

        return $result;
    }

    public function onRun() {
        $form = Form::find($this->property('form'));

        $this->open = $form->opens_at ? $form->opens_at->isPast() : false;
        $this->closed = $form->closes_at ? $form->closes_at->isPast() : false;

        $this->opens = $this->distance($form->opens_at);
        $this->closes = $this->distance($form->closes_at);

        $this->liftoff = $this->open ? $this->closes : $this->opens;
    }

}
