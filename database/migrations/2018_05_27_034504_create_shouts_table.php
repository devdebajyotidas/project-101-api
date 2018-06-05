<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateShoutsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shouts', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->string('area')->nullable();
            $table->string('latitude');
            $table->string('longitude');
            $table->integer('service_id');
            $table->integer('taken_by');
            $table->tinyInteger('is_complete')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('shouts');
    }
}
