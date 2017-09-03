<?php

namespace App\Libs;

class UtilTime
{
    const FORMAT_STRING = "Y-m-d H:i:s"; // 日付けフォーマット


    public static function now()
    {
        return date( self::FORMAT_STRING );
    }

    public static function nowTime()
    {
        return strtotime(self::now());
    }

    public static function timeToStr( $time )
    {
        return date( self::FORMAT_STRING, $time );
    }

	/**
	 * // 渡された日付け文字列に指定秒を足したUnixTimestampを返す
	 * @param  string         $timestring
	 * @param  int            $second
	 * @return int
	 */
	public static function addSecond($timestring, $second)
	{
		return strtotime($timestring) + $second;
	}
	public static function addMinutes($timestring, $minutes)
	{
		return self::addSecond($timestring, $minutes*60);
	}

}
