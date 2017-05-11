<?php namespace Nocio\FormStore\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateNocioFormstoreRelations extends Migration
{
    public function up()
    {
        Schema::create('nocio_formstore_relations', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('form_id')->nullable()->unsigned()->default(0);
            $table->integer('target_id')->nullable()->unsigned()->default(0);
            $table->string('title')->nullable();
            $table->string('field', 255)->nullable();
            $table->integer('required_min')->default(0);
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('nocio_formstore_relations');
    }
}