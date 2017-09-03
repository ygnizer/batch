<?php

namespace App\Batches;

use \Exception as Exception;
use App\Libs\RiotApi\Summoners;
use App\Libs\RiotApi\LeaguesBySummoner;
use App\Models\ApiQueue;
use App\Models\Member;
use App\Models\LnsDB;

/**
 * // テストバッチ
 */
class BatchUserTier extends BatchBase
{

	public function main()
	{
		$apikey     = 'RGAPI-1883206d-c040-4767-ae43-003b68b3e640';
		$sm_api     = new Summoners();

		// api_queuesテーブルからaction=1,state=0のものを処理する
		$queues = ApiQueue::where  ('action',     ApiQueue::ACTION_UPDATE_SUMMONER)
						  ->where  ('state',      ApiQueue::STATE_UNTREATED)
						  ->orderBy('priority',   'desc')
						  ->orderBy('created_at', 'asc')
						  ->get();

		\Log::debug('処理予定のqueue件数：'.$queues->count());
		$this->log( '処理予定のqueue件数：'.$queues->count() );
		foreach( $queues as $queue )
		{
			// 処理中としてマークつける
			$record = false;
			LnsDB::transaction(function()use($queue, &$record)
			{
				$record = ApiQueue::where ('id',    $queue->id)
								  ->where ('state', ApiQueue::STATE_UNTREATED)
								  ->first();
				if( !empty($record) )
				{
					$record->state = ApiQueue::STATE_DOING;
					$record->save();
				}
			});
			// マークつけれなかったなら次へ。
			if( empty($record) )
			{
				\Log::debug('$id = '.$queue->id.' is not UNTREATED. go to next.');
				$this->log('$id = '.$queue->id.' is not UNTREATED. go to next.');
				continue;
			}
			\Log::debug('$record = '.print_r($record->toArray(),true));


			// payloadの中にちゃんとデータ設定されてるか？
			$payload = json_decode($record->payload, true);
			if( !$this->checkPayload($payload) )
			{
				// キューを失敗にしておく？
				$this->log('失敗。payloadにデータがちゃんと設定されてない。payload:'.$record->payload);
				$queue->result = '失敗。payloadにデータがちゃんと設定されてない。payload:'.$record->payload;
				$queue->state  = ApiQueue::STATE_FAILED;
				$queue->save();
				continue;
			}
			// 該当のm_memberレコード取ってくる
			$member  = Member::find($payload['member_id']);
			if( empty($member) )
			{
				// キューを失敗にしておく？
				$this->log('失敗。memberテーブルに該当レコード見当たらず。member_id:'.$payload['member_id']);
				$queue->result = '失敗。memberテーブルに該当レコード見当たらず。member_id:'.$payload['member_id'];
				$queue->state  = ApiQueue::STATE_FAILED;
				$queue->save();
				continue;
			}
			// 該当のm_memberレコードにsummoner_idが設定されてなかったらだめ！
			if( empty($member->summoner_id) )
			{
				// キューを失敗にしておく？
				$this->log('失敗。memberレコードにsummoner_idが設定されてない。$member:'.$member->toJson());
				$queue->result = '失敗。memberレコードにsummoner_idが設定されてない。$member:'.$member->toJson();
				$queue->state  = ApiQueue::STATE_FAILED;
				$queue->save();
				continue;
			}

			// RiotApiからsummoner_idを元にデータひっぱってくる
			$sm_api->setParams(['id'=>$member->summoner_id]);
			$sm_api->setApiKey($apikey);
			$json = $sm_api->execApi();

			// 取れなかったら失敗ということで。
			if( !$sm_api->isSuccess() )
			{
				// キューを失敗にしておく？
				$this->log('失敗。RiotApiでデータ見つからなかった系。$json:'.json_encode($json));
				$queue->result = '失敗。RiotApiでデータ見つからなかった系。$json:'.json_encode($json);
				$queue->state  = ApiQueue::STATE_FAILED;
				$queue->save();
				continue;
			}

			// ちゃんと取れたので、、、
//$this->log('$json = '.print_r($json,true));
\Log::debug('$json = '.print_r($json,true));
			LnsDB::transaction(function()use(&$member, &$queue, $json)
			{
				$from = $member->toArray();
				// サモナー情報更新して、
				$member->summoner_name = $json['name'];
				$member->account_id    = $json['accountId'];
				$member->save();
				$dest = $member->toArray();
				// キューを完了にする
				$this->log('$id = '.$queue->id.' is Finished. go to next.');
				$queue->result = json_encode(['from'=>$from,'desc'=>$dest]);
				$queue->state  = ApiQueue::STATE_FINISHED;
				$queue->save();
			});
		}

/*
		$apikey     = 'RGAPI-1883206d-c040-4767-ae43-003b68b3e640';
		$sm_api     = new Summoners();
		$league_api = new LeaguesBySummoner();
*/

/*
		$list = [

'6181095',
'6182723',
'6200694',
'6193035',
'6188637',
		];
*/

/*
//		$fp = fopen('test.list', 'r');
		$list = file('test.list', FILE_IGNORE_NEW_LINES);
//		fclose($fp);
*/
/*
		foreach( $list as $sid )
		{
			// leagueAPI結果中で名前突合せする必要あるので、まずはsummonerNameとっておく。
			$sm_api->setParams(['id'=>$sid]);
			$sm_api->setApiKey($apikey);
			$json = $sm_api->execApi();
			$name      = 'NoData';
			if( !empty($json['id']) )
			{
				$name      = $json['name'];
			}
			// 取れなかったら次のレコードへ。
			else
			{
				continue;
			}

			// ここからleagueAPIでサモナーのtier情報取ってくる。
			$league_api->setParams(['summonerId'=>$sid]);
			$league_api->setApiKey($apikey);
			$json = $league_api->execApi();

			$tier      = 'Unrank';
			$rank      = 'Unrank';
			if( empty($json) )
			{
//				Logger::info('■:'.$sid.':'.$tier.':'.$rank);
				\Log::info('■:'.$sid.':'.$tier.':'.$rank);
				continue;
			}

			// そのリーグのメンバー一覧が入ってるので、自分を探す
			foreach( $json[0]['entries'] as $entrie )
			{
				if( $name == $entrie['playerOrTeamName'] )
				{
					$tier = $json[0]['tier'];
					$rank = $entrie['rank'];
//					Logger::info('■:'.$sid.':'.$tier.':'.$rank);
					\Log::info('■:'.$sid.':'.$tier.':'.$rank);
					break;
				}
			}
		}
*/
	}


	protected function checkPayload( $payload )
	{
		$res = true;
		if( empty($payload) || empty($payload['member_id']) )
		{
			$res = false;
		}
		return $res;
	}

}
