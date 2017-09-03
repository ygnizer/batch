<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableApiQueues extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('api_queues', function (Blueprint $table) {
            $table->engine       = 'InnoDB';
            $table->bigincrements('id');
            $table->integer      ('action')                ->unsigned()                                    ->comment('1:Summoner情報更新、2:...');
            $table->tinyinteger  ('state')                 ->unsigned()->default(0)                        ->comment('0:未処理、1:処理中、2:処理済み');
            $table->integer      ('priority')                          ->default(0)                        ->comment('優先度');
            $table->string       ('payload')                                             ->nullable()      ->comment('actionに応じた処理データ');
            $table->text         ('result')                                              ->nullable()      ->comment('結果データ(気休め・・)');
            $table->timestamps   ();
            $table->index        ('action');
            $table->index        ('state');
            $table->index        ('priority');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('api_queues');
    }
}
