<?php

namespace App\Libs\RiotApi;

use \Exception as Exception;
use App\Models\ApiCallStatus;

abstract class ApiBase
{
/*
	protected static $config   = [
		'APIKEY' => 'RGAPI-5698ead6-8fe6-435c-8e25-84349da4f51c',
	];
*/
	protected        $apikey   = '';
	protected static $api_base = 'https://jp1.api.riotgames.com/lol/';          // リージョンとかも変えれるようにするとよりいいかも。
	protected static $path     = '';                                            // ★継承先で定義すること。
	protected        $url      = '';
	protected static $dto      = [];                                            // ★継承先で定義すること。
	protected        $params   = [];                                            // ★コンストラクタで渡す もしくは setParams() で設定すること
	protected        $code     = 0;
	abstract protected function makeUrl();                                      // ★継承先で定義すること。

	public function __construct( $prm=[], $apikey='' )
	{
		$this->params = $prm;
// apikeyの設定については、configファイル使うのか環境変数つかうのかなど、
// 利用環境(フレームワーク？)に合わせた方がいいと思うので環境ごとに適宜処理を変更してほしい
//		static::$apikey = getenv('APIKEY');
		$this->apikey = $apikey;
	}
	/**
	 * // Usage: $testapi  = new SummonersByName();
	 *           $testapi->setParams(['name'=>'ygnizer']);
	 *
	 * @param  array                    $prm                          // 
	 * @return void
	 */
	public function setParams( $prm=[] )
	{
		$this->params = $prm;
		$this->url    = $this->makeUrl();
		$this->code   = 0;
	}
	/**
	 * // RiotApiKeyを設定
	 *
	 * @param  array                    $apikey                          // RiotApiKey文字列
	 * @return void
	 */
	public function setApiKey( $apikey )
	{
		$this->apikey = $apikey;
	}
	public function isSuccess()
	{
		return $this->code == 200 ? true : false;
	}
	/**
	 * // Usage: $testapi  = new SummonersByName(['name'=>'ygnizer']);
	 *           $json_arr = $testapi->execApi();
	 *
	 * @param  boolean                  $useCache                     // true:キャッシュ使う、false:キャッシュ使わない
	 * @return array
	 */
	public function execApi( $useCache=true )
	{
		if( empty($this->apikey) || empty(static::$path) || empty($this->params) )
		{
			\Log::error('apikey, path, params, のいずれかが設定されていないのでapiコール出来ない');
			return $this->getDefaultResult();
		}
		$data = [];
		\Log::debug('$useCache = ' . ($useCache ? 'true' : 'false'));
		if( $useCache )
		{
			\Log::info('キャッシュから取得します');
			$data = [];
			// キャッシュから取ってくる処理を記載する
			// Cache::get($this->getUrl());
		}

		if( empty($data) )
		{
			if( $useCache )
			{
				\Log::info('キャッシュから取得出来なかったのでapi叩きます');
			}
			// RateLimitについてこちら側で抑えておく
			$this->checkRateLimit();

//			try
//			{
				$data = $this->call_api();
//			}
//			catch( Exception $e )
//			{
//				\Log::error($e->getMessage());
//				$data = false;
//			}
			// キャッシュに$dataを登録する処理を記載する
			// Cache::set($this->getUrl(), $data);
		}
/*
		if( empty($data) )
		{
			return $data;
		}
*/
		$json = json_decode($data['body'], true);
		$this->code = $data['code'];
		if( $data['code'] != '200' )
		{
			$json = $this->getDefaultResult();
		}
//		\Log::debug('$json = ' . json_encode($json));
		return $json;
	}


	////////////////////////// ↓↓↓ここから内部関数↓↓↓ //////////////////////////
	protected function checkRateLimit()
	{
		\Log::debug('内部レートリミットチェックします');

		// statusとってくる
		$status = ApiCallStatus::getStatus($this->getApiKey());
		$check  = $status->checkLimit();
		if( !$check['enable'] )
		{
			// 待ち時間を取得してその分待つ。
			$wait_time = max($check['waittime_for_1sec'], $check['waittime_for_2min']);
			\Log::debug('内部レートリミットオーバーなので待ちます $wait_time = ' . $wait_time);
			sleep($wait_time);
			// もう一回チェック
			$this->checkRateLimit();
		}
	}
	protected function getApiKey()
	{
		return $this->apikey;
	}
	protected function getUrl()
	{
		if( empty($this->url) )
		{
			$this->url = $this->makeUrl();
		}
		return $this->url;
	}
	protected function getDefaultResult()
	{
		return array_merge(static::$dto, $this->params);
	}
	protected function call_api()
	{
		$ch          = $this->setupCurl();
		$response    = curl_exec($ch);
		$code        = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE); // ヘッダサイズ取得
		$header      = substr($response, 0, $header_size);      // headerだけ切り出し
		$body        = substr($response, $header_size);         // bodyだけ切り出し
		curl_close($ch);

		\Log::info('$code = ' . $code);
		switch( $code )
		{
			case '200':
			// ここらへんはまぁありうるかなという感じのやつ。データみつからないとか。
			case '400': // (Bad Request)
			case '404': // (Not Found)
				break;
			// リトライ案件
			case '429': // (Rate Limit Exceeded)
				preg_match('/Retry-After: ([0-9]*)/', $header, $matches);
				$retry_after = $matches[1];
				\Log::info('$retry_after = ' . $retry_after . ', sleep $retry_after+1 seconds.');
				sleep($retry_after + 1);
				$data = $this->call_api();
				break;
			// その他の400番台や500番台が返ってくるってのは恐らく致命的になにかまずいので処理中断切り上げで。
/*
			case '403': // (Forbidden)
			case '415': // (Unsupported Media Type)
			case '500': // (Internal Server Error)
			case '503': // (Service Unavailable)
*/
			default:
				\Log::error('想定外コードなのでエラー終了予定');
				throw new Exception($code);
				break;
		}
		if( $code != '429' )
		{
			$data = [
				'url'          => $this->getUrl(),
				'params'       => $this->params,

				'response'     => $response,
				'code'         => $code,
				'header'       => $header,
				'body'         => $body,
			];
		}

		return $data;
	}
	private function setupCurl()
	{
		$url = $this->getUrl();
		\Log::info('$url = ' . $url);
		// curl準備、実行
		$ch  = curl_init();
		curl_setopt($ch, CURLOPT_URL,            $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER,         true);
		curl_setopt($ch, CURLOPT_HTTPHEADER,     ["X-Riot-Token:" . $this->getApiKey()]);

		return $ch;
	}

}
