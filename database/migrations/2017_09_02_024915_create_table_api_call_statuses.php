<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableApiCallStatuses extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('api_call_statuses', function (Blueprint $table) {
            $table->engine       = 'InnoDB';
            $table->bigincrements('id');
            $table->string       ('apikey', 100)                                                           ->comment('RiotApiKey');
            $table->timestamp    ('last_reset_by_1sec')                                  ->nullable()      ->comment('直近リセット日時(1秒毎のレートリミットの管理)');
            $table->timestamp    ('last_reset_by_2min')                                  ->nullable()      ->comment('直近リセット日時(2分毎のレートリミットの管理)');
            $table->integer      ('count_by_1sec')         ->unsigned()->default(0)                        ->comment('既に何回callしたか(1秒毎のレートリミットの管理)');
            $table->integer      ('count_by_2min')         ->unsigned()->default(0)                        ->comment('既に何回callしたか(2分毎のレートリミットの管理)');
            $table->timestamps   ();
            $table->unique       ('apikey');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('api_call_statuses');
    }
}
