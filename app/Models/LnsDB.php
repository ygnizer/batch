<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

// 他DB増えるかもだし一応・・・
class LnsDB extends DB
{
    // 
	public static function transaction($closure, $retryCount=5)
	{
		parent::connection('mysql')->transaction($closure, $retryCount);
	}
}
