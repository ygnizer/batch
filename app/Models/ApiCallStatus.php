<?php

namespace App\Models;

use App\Libs\UtilTime;

class ApiCallStatus extends BaseModel
{
    protected $table      = 'api_call_statuses';  // テーブル名
    protected $guarded    = [];

    const LIMIT_BY_1SEC     = 5;
    const LIMIT_BY_2MIN     = 60;



	public static function getStatus($apikey)
	{
		$status = self::where('apikey', $apikey)->first();
		if( empty($status) )
		{
			$now = UtilTime::now();
			$status = self::create([
				'apikey'             => $apikey,
				'last_reset_by_1sec' => $now,
				'last_reset_by_2min' => $now,
			]);
		}
		return $status;
	}


	public function checkLimit()
	{
		// 現在時刻取得しておく。(UnixTimestamp値で。)
		$now               = UtilTime::nowTime();
		$enable            = true;
		$waittime_for_1sec = 0;
		$waittime_for_2min = 0;

		// ==== 1秒について。 ==== 
		if( UtilTime::addSecond($this->last_reset_by_1sec, 1) <= $now )
		{
			// 1秒経っているのでカウントをリセット
			$this->last_reset_by_1sec = UtilTime::timeToStr($now);
			$this->count_by_1sec      = 1;
		}
		else if( $this->count_by_1sec < ApiCallStatus::LIMIT_BY_1SEC )
		{
			// リミット未満なのでセーフ、回数は増やす
			$this->count_by_1sec++;
		}
		else
		{
			// アウアウ。
			$enable = false;
			$waittime_for_1sec = UtilTime::addSecond($this->last_reset_by_1sec, 1) + 1 - $now; // バッファで1秒追加。
		}

		// ==== 2分について。 ==== 
		if( UtilTime::addMinutes($this->last_reset_by_2min, 2) <= $now )
		{
			// 2分経っているのでカウントをリセット
			$this->last_reset_by_2min = UtilTime::timeToStr($now);
			$this->count_by_2min      = 1;
		}
		else if( $this->count_by_2min < self::LIMIT_BY_2MIN )
		{
			// リミット未満なのでセーフ
			$this->count_by_2min++;
		}
		else
		{
			// アウアウ。
			$enable = false;
			$waittime_for_2min = UtilTime::addMinutes($this->last_reset_by_2min, 2) + 1 - $now; // バッファで1秒追加。
		}

		// 1秒制限、2分制限の両方が問題なければ、カウントアップ(＋時間リセットしてればそれも)をアップデートしておく
		if( $enable )
		{
			$this->save();
		}

		$res = [
			'id'                 => $this->id,
			'apikey'             => $this->apikey,
			'last_reset_by_1sec' => $this->last_reset_by_1sec,
			'last_reset_by_2min' => $this->last_reset_by_2min,
			'count_by_1sec'      => $this->count_by_1sec,
			'count_by_2min'      => $this->count_by_2min,
			'created_at'         => $this->created_at,
			'updated_at'         => $this->updated_at,
			'enable'             => $enable,
			'waittime_for_1sec'  => $waittime_for_1sec,
			'waittime_for_2min'  => $waittime_for_2min,
		];
		return $res;
	}
}
