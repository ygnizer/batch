<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

//use App\Models\Member;
//use App\Models\ApiCallStatus;
use App\Models\ApiQueue;
//use App\Libs\UtilTime;
//use App\Libs\RiotApi\Summoners;

use App\Batches\BatchUserTier;
use App\Libs\UtilTime;

class ExecApiQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'batch:api_queue {action}'; // ←優先度指定とかもあっていいかも～。

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'api_queuesの処理';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //
        $action = $this->argument('action');

        // とりあえずテスト
//		$name = $this->ask('What is your name?');

//		$this->info    ('$name         = '.$name);
		$this->line    ('$action       = '.$action);

		// DBテスト=======================================================================
//		$members = \App\Models\Member::all();
//		$members = Member::all();
//		$members = \Model\Member::all();
/*
		$member_headers = [
			'member_id',
			'team_id',
			'summoner_name',
			'create_date',
			'update_date',
		];
*/
/*
		$member_count = $members->count();
		$this->info    ('$member_count = '.$member_count);
		$this->table($member_headers, $members);
		$member = Member::create([
			'team_id'       => 999,
			'summoner_name' => $name,
		]);
		$members = Member::where('member_id', $member->member_id)->get();
		$this->table($member_headers, $members);
*/
/*
		$queue = ApiQueue::create([
			'action'        => ApiQueue::ACTION_UPDATE_SUMMONER,
			'payload'       => json_encode(['member_id'=>1504]),
		]);
		$queues = ApiQueue::where('id', $queue->id)->get();
		$queue_headers = [
			'id',
			'action',
			'state',
			'priority',
			'payload',
			'created_at',
			'updated_at',
		];
		$this->table($queue_headers, $queues);
*/
/*
		$status = $this->getStatus("RGAPI-5698ead6-8fe6-435c-8e25-84349da4f51c");
		\Log::debug('$key = '.$status->apikey);

		$sid = 6181095;
		$sm_api     = new Summoners();
//		$sm_api     = new \App\Libs\Summoners();
		$sm_api->setParams(['id'=>$sid]);
		$json = $sm_api->execApi();
		$name      = 'NoData';
		if( !empty($json['id']) )
		{
			$name      = $json['name'];
		}
		\Log::debug('$name = '.$name);
*/

		// $actionで振り分け
		switch( $action )
		{
			case ApiQueue::ACTION_UPDATE_SUMMONER:
				$batch = new BatchUserTier();
				break;

			default:
				$this->error('指定actionの振り先が未定義');
				return false;
				break;
		}

		try
		{
			$batch->init($this);
//throw new \Exception('Test throw Exception');
			$batch->main();
		}
		catch( \Exception $e )
		{
			// 処理中でException投げた場合はend()通らないない=lockファイル削除されないので、次回実行の時はlockファイルを手動で削除してからで！
			\Log::error($e->getMessage());
//			$batch->postToChatwork('エラー終了: $e->getMessage() = ' . $e->getMessage());
			throw $e;
		}
		$batch->end();
    }




    /**
     * // 
     *
     * @param  string           $apikey         // RiotApiKey
     * @return ApiCallStatus
     */
/*
	function getStatus( $apikey )
	{
		$status = ApiCallStatus::where('apikey', $apikey)->first();
		if( empty($status) )
		{
			$now = UtilTime::now();
			$status = ApiCallStatus::create([
				'apikey'             => $apikey,
				'last_reset_by_1sec' => $now,
				'last_reset_by_2min' => $now,
			]);
		}
		return $status;
	}
*/
    /**
     * // 
     *
     * @param  string           $apikey         // RiotApiKey
     * @return ApiCallStatus
     */
/*
	function getLogMethod()
	{
		return function($message){ $this->line($message); };
	}
*/
	function log($message)
	{
		$msg = '[' . UtilTime::now() . '] ' . $message;
		$this->line($msg);
	}

}
