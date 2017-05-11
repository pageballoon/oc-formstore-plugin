<?php namespace Nocio\FormStore\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateNocioFormstoreSubmitters extends Migration
{
    public function up()
    {
        Schema::create('nocio_formstore_submitters', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('email', 255)->nullable();
            $table->string('identifier', 255)->nullable();
            $table->string('token', 255)->nullable();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('nocio_formstore_submitters');
    }
}
