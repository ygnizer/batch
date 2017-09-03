<?php

namespace App\Models;

class Member extends BaseModel
{
    const CREATED_AT = 'create_date';
    const UPDATED_AT = 'update_date';

    protected $table      = 'm_member';  // テーブル名
    protected $primaryKey = 'member_id'; // プライマリーキー
//  protected $timestamps = false;       // created_atとupdated_atの自動更新
    protected $guarded    = [];

}
