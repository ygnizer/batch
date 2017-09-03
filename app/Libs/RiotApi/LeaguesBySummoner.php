<?php

namespace App\Libs\RiotApi;

/**
 * // 現在サモナー情報をサモナーIDで引っ張ってくる
 *    $api = new ActiveGamesBySummoner(['summonerId'=>0]);
 */
class LeaguesBySummoner extends ApiBase
{
	protected static $path = 'league/v3/leagues/by-summoner/%d';
	protected static $dto  = [
		'tier'              => "",
		'queue'             => "",
		'name'              => "",
		'entries'           => [],
	];
	protected function makeUrl()
	{
		return sprintf(static::$api_base . static::$path, $this->params['summonerId']);
	}
	protected function getDefaultResult()
	{
		return static::$dto;
	}
}
