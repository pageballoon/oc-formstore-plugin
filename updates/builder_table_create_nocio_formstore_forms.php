<?php namespace Nocio\FormStore\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateNocioFormstoreForms extends Migration
{
    public function up()
    {
        Schema::create('nocio_formstore_forms', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->string('model');
            $table->string('fields_config')->default('fields.yaml');
            $table->text('validation');
            $table->string('title');
            $table->text('introduction')->nullable();
            $table->boolean('tac_enabled')->default(0);
            $table->text('tac')->nullable();
            $table->dateTime('opens_at')->nullable();
            $table->dateTime('closes_at')->nullable();
	    $table->integer('max_per_user')->default(-1);
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('nocio_formstore_forms');
    }
}
