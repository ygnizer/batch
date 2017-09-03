<?php
//namespace RiotApi;
namespace App\Batches;

// バッチのベースクラス
// いろいろ派生バッチをうごかす感じになりそうだけど、どれかが動いているときには他は動かしたくない、みたいな。
use \Exception as Exception;
use Illuminate\Support\Facades\Storage;

/*
// =======================↓↓継承先実装イメージ。=======================

use App\Libs\RiotApi\Summoners;          // ←使うRiotApiクラスアクセスしやすいように。
use App\Libs\RiotApi\LeaguesBySummoner;

class ○○ extends ApiBatchBase
{
	public function main()
	{
		// いろいろ処理記載
	}
}


// =======================↓↓実行イメージ。=======================
use App\Batches\○○;  // ←使うBatchクラスアクセスしやすいように。

$batch = new ○○();
try
{
	$batch->init();

	$batch->main();
}
catch( Exception $e )
{
	// 処理中でException投げた場合はend()通らないない=lockファイル削除されないので、次回実行の時はlockファイルを手動で削除してからで！
	Logger::error($e->getMessage());
	// できればここに、エラー終了したことをchatworkなりに発信する仕組みを。
	$batch->postToChatwork("○○バッチでエラー終了:".$e->getMessage());
	exit(1);
}
$batch->end();
*/

abstract class BatchBase
{
	protected $name = '';  // set unieuq name in your batches if you want. this is used to lock filename.
	private   $start_microtime;
	private   $end_microtime;
	protected $owner;

	/**
	 * // 継承先クラスでインスタンス作成する際に引数なしで生成した場合、lockファイルは ApiBatchBase.lock という名前で作られる感じ。
	 *    指定してたら その名前.lock でlockファイルが作られる感じ。
	 *
	 * @param  string                   $name
	 * @return void
	 */
	public function __construct( $name='' )
	{
		if( empty($name) )
		{
			$name = __CLASS__;
			$name = explode("\\",$name);
			$name = $name[count($name)-1];
		}
		$this->name = $name;
	}

	/**
	 * // バッチの初期処理。
	 *    開始時間記録して多重起動チェックしてlockファイル作成
	 *
	 * @param  Command      $owner             // $owner->log($msg)を実装しているやつ。IF作るのが作法だが・・。
	 * @return void
	 */
	public function init( $owner )
	{
		$this->owner = $owner;
		$this->start_microtime = microtime(true);

		// 多重起動チェック
		if( $this->is_running() )
		{
			throw new Exception('多重起動になるので終了');
		}
		// lockファイルの作成
		$this->createLockFile();
	}

	/**
	 * // バッチのメイン処理。継承先で作りこむこと。
	 *
	 * @param  void
	 * @return void
	 */
	abstract public function main();

	/**
	 * // バッチの終了処理。
	 *    lockファイル削除してバッチ全体の処理時間をLoggerで出力
	 *
	 * @param  void
	 * @return void
	 */
	public function end()
	{
		// lockファイルの削除
		$this->deleteLockFile();

		$this->end_microtime = microtime(true);
		\Log::info( '処理時間：' . $this->getExecutTimeString() );
	}

	////////////////////////// ↓↓↓ここから内部関数↓↓↓ //////////////////////////
	protected function log($message)
	{
		$this->owner->log($message);
	}
	protected function getLockFileName()
	{
		if( empty($this->name) )
		{
			throw new Exception('$this->name が設定されていない');
		}
		return $this->name . '.lock';
	}
	protected function is_running()
	{
		if( Storage::disk('local')->exists($this->getLockFileName()) )
		{
			\Log::debug('is_running() = true');
			return true;
		}
		\Log::debug('is_running() = false');
		return false;
	}
	protected function createLockFile()
	{
//		touch( $this->getLockFileName() );
		Storage::disk('local')->put($this->getLockFileName(), 'Contents');
		\Log::info( "lockファイル作成：" . $this->getLockFileName() );
	}
	protected function deleteLockFile()
	{
//		if( file_exists( $this->getLockFileName() ) )
		if( Storage::disk('local')->exists($this->getLockFileName()) )
		{
//			unlink( $this->getLockFileName() );
			Storage::disk('local')->delete($this->getLockFileName());
			\Log::info( "lockファイル削除：" . $this->getLockFileName() );
		}
		else
		{
			\Log::error( "lockファイル削除しようとしたのにファイルがみつからない：" . $this->getLockFileName() );
		}
	}
	protected function getExecutTimeString()
	{
		$sabun  = $this->end_microtime - $this->start_microtime;
		list($seconds, $micro_seconds) = explode('.', $sabun);
		$hour   = floor($seconds / 3600);
		$minuit = floor( ($seconds % 3600) / 60 );
		$second = floor( ($seconds % 3600) % 60 );
		return ($hour?$hour.'時間':'') . ($minuit?$minuit.'分':'') . $second . '.' . $micro_seconds . '秒';
	}
/*
	public function postToChatwork( $message, $title='' )
	{
		$room_id = 61194794; // Webエンジニア部
//		$room_id = 66957146; // 配信部

		if( empty($title) )
		{
			$message = '[code]' . $message . '[/code]';
		}
		else
		{
			$message = '[info][title]' . $title . '[/title][code]' . $message . '[/code][/info]';
		}

		$api = new \CwApi\PostRoomMessage();
		$api->setParams(['room_id'=>$room_id]);
		$api->setPostData(['body'=>$message]);
		$api->execApi();
	}
*/
}
