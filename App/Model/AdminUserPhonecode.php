<?php

namespace App\Model;

use App\Base\BaseModel;
use App\lib\pool\BaseRedis;
use App\lib\Tool;
use EasySwoole\Mysqli\QueryBuilder;

class AdminUserPhonecode extends BaseModel
{
    protected $tableName = "admin_user_phonecode";
    const STATUS_UNUSED = 0;


    public function findAll($page, $limit)
    {
        return $this
            ->order('created_at', 'desc')
            ->limit(($page - 1) * $limit, $limit)
            ->all();
    }


    public function saveIdData($id, $data)
    {
        return $this->where('id', $id)->update($data);
    }

    //获取用户验证码
    public function getLastCodeByMobile($mobile)
    {
        return $this->where('mobile', $mobile)->where('status', self::STATUS_UNUSED)->order('created_at', 'DESC')->limit(1)->get();
    }
}
