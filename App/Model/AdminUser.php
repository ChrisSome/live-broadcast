<?php

namespace App\Model;

use App\Base\BaseModel;
use App\lib\pool\Login;
use App\lib\Tool;
use EasySwoole\Mysqli\QueryBuilder;

class AdminUser extends BaseModel
{
    protected $tableName = "admin_user";

    const USER_TOKEN_KEY = 'user:token:%s';   //token

    const STATUS_PRE_INIT = 1;      //用户信息审核状态
    public function findAll($page, $limit)
    {
        return $this->order('created_at', 'DESC')
            ->limit(($page - 1) * $limit, $limit)
            ->all();
    }


    /**
     * @param $id
     * @param $data
     * @return bool
     * @throws \EasySwoole\Mysqli\Exception\Exception
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function saveIdData($id, $data)
    {
        return self::update($data, ['id' => $id]);
    }


    /**
     * 通过微信token以及openid获取用户信息
     * @param $access_token
     * @param $openId
     * @return bool|string
     */
    public function getWxUser($access_token , $openId)
    {
        $url = sprintf("https://api.weixin.qq.com/sns/userinfo?access_token=%s&openid=%s&lang=zh_CN", $access_token, $openId);

        return Tool::getInstance()->postApi($url);
    }

    public function getOneByToken($token)
    {
        //头部传递access_token
        $tokenKey = sprintf(self::USER_TOKEN_KEY, $token);
        $mobile = Login::getInstance()->get($tokenKey);
        if ($mobile) {
            Login::getInstance()->setEx($tokenKey, 60*60*24*7, $mobile);
        }
        return $this->where('mobile', $mobile)->limit(1)->get();

    }

    /**
     * 获取用户详情
     * @param $id
     * @return mixed
     */
    public function findOne($id)
    {
        if (Login::getInstance()->exists('hash:user:'.$id)) {
            $user = Login::getInstance()->hgetall('hash:user:'.$id);
        } else {
            $user = $this->where('id', $id)->get()->toArray();
            unset($user['password_hash']);
        }

        return $user;
    }

    /**
     * 某人的评论数
     * @return mixed
     */
    public function commentCount()
    {


        return $this->hasMany(AdminPostComment::class, function(QueryBuilder $queryBuilder) {
            $queryBuilder->where('status', AdminPostComment::STATUS_NORMAL);
        }, 'id', 'user_id');

    }

    /**
     * 收藏数
     */
    public function collectCount()
    {
        return $this->hasMany(AdminPostOperate::class, function(QueryBuilder $queryBuilder)  {
            $queryBuilder->where('action_type', AdminPostOperate::ACTION_TYPE_COLLECT);
            $queryBuilder->where('comment_id', 0);
        }, 'id', 'user_id');

    }

    /**
     * 我的发帖
     * @return mixed
     */
    public function postCount() {
        return $this->hasMany(AdminUserPost::class, function(QueryBuilder $queryBuilder) {
            $queryBuilder->where('status', AdminUserPost::STATUS_EXAMINE_SUCC);
        }, 'id', 'user_id');
    }

    /**
     *
     */
    public function userSetting()
    {
        return $this->hasOne(AdminUserSetting::class, null, 'id', 'user_id');
    }

}
