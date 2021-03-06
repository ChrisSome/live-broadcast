<?php
/**
 * Created by PhpStorm.
 * User: evalor
 * Date: 2018-11-28
 * Time: 20:23
 */

namespace App\Task;

use App\lib\pool\Login;
use App\lib\Tool;
use App\Model\AdminMessage;
use App\Model\AdminUser;
use App\Model\ChatHistory;
use App\Storage\ChatMessage;
use App\Storage\OnlineUser;
use App\WebSocket\WebSocketStatus;
use EasySwoole\EasySwoole\ServerManager;
use App\WebSocket\WebSocketAction;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\Task\AbstractInterface\TaskInterface;
use App\Utility\Log\Log;

/**
 * 发送广播消息
 * Class BroadcastTask
 * @package App\Task
 */
class BroadcastTask implements TaskInterface
{
    protected $taskData;

    public function __construct($taskData)
    {
        $this->taskData = $taskData;
    }


    /**
     * 执行投递
     * @param $taskData
     * @param $taskId
     * @param $fromWorkerId
     * @param $flags
     * @return bool
     */
     function run(int $taskId, int $workerIndex)
    {
        $this->exec();
        return true;
        $taskData = $this->taskData;
        /** @var \swoole_websocket_server $server */

        $server = ServerManager::getInstance()->getSwooleServer();

        $messages = $taskData['payload'];
        $aMessage = json_decode($messages, true);
        if (json_last_error()) {
            Logger::getInstance()->log("发送信息解析json失败");
            return ;
        }
        $iMatchId = $aMessage['matchId'];
        $online = OnlineUser::getInstance();
        $aCustomers = Login::getInstance()->lrange(sprintf($online::LIST_ONLINE, $iMatchId), 0, -1);
        //先将信息插入库，然后再分发
        $messageType = $aMessage['type'];
        $iFromUserId = $aMessage['fromUserId'];
        $mFromUser = AdminUser::getInstance()->findOne($iFromUserId);
        $messageData = [
            'sender_user_id' => $iFromUserId,
            'sender_mobile' => $mFromUser['mobile'],
            'sender_nickname' => $mFromUser['nickname'],
            'type' => $messageType,
            'match_id' => $iMatchId,
            'with_message_id' => intval($aMessage['messageId'])
        ];

        $toUser = [];
        $originMessage = [];
        if (!empty($aMessage['messageId'])) {
            //获取to用户相关信息，方便客户端提示用户
            $originMessage = ChatHistory::getInstance()->where('id', $aMessage['messageId'])->getOne();
            $toUser =  AdminUser::getInstance()->findOne($originMessage['sender_user_id']);

        }
        switch ($messageType) {
            case 'text':
                $messageData['content'] = htmlspecialchars(addslashes($aMessage['content']));
                break;
        }

        $iLastInsertId = ChatHistory::getInstance()->insert($messageData);
        if (!$iLastInsertId) {
            Logger::getInstance()->log("发布聊天信息失败");
            return ;
        }
        $tool = Tool::getInstance();

        $aMessageBody = [
            'messageType' => $messageType,
            'messageContent' => [
                'fromMid' => $aMessage['mid'],
                'fromUserId' => $iFromUserId,
                'message_id' => $iLastInsertId,
                'type' => $messageType,
                'content' =>  $messageData['content'],
                'match_id' => $iMatchId,
                'toUser' => $toUser,
                'originMessage' => $originMessage
            ]
        ];


    }

    function exec() {
        $taskData = $this->taskData;
        $server = ServerManager::getInstance()->getSwooleServer();
        //获取该房间内所有用户
        $messages = $taskData['payload'];
        $aMessage = json_decode($messages, true);
        if (json_last_error()) {
            Logger::getInstance()->log("发送信息解析json失败");
            return ;
        }
        //将聊天信息入库
        $messageType = $aMessage['type'];
        switch ($messageType) {
            case 0:
                $messageData['content'] = htmlspecialchars(addslashes($aMessage['content']));
                break;
        }
        $messageData = [
            'sender_user_id' => $aMessage['fromUserId'],
            'content'        => htmlspecialchars(addslashes($aMessage['content'])),
            'type'           => $aMessage['type'],
            'match_id'       => $aMessage['matchId'],
            'with_message_id' => isset($aMessage['with_message_id']) ? (int)$aMessage['with_message_id'] : 0,
            'at_user_id'     => isset($aMessage['atUserId']) ? $aMessage['atUserId'] : 0,
        ];
        $insertId = ChatHistory::getInstance()->insert($messageData);
        if (!$insertId) {
            Log::getInstance()->error('发布聊天失败');
            return ;
        }
        $tool = Tool::getInstance();
        $userOnline = OnlineUser::getInstance()->get($aMessage['fromUserFd']);

        if (!$userOnline['user_id']) {
            $is_first = true;
            $userM = AdminUser::getInstance()->where('id', $aMessage['fromUserId'])->get();

            OnlineUser::getInstance()->update($userOnline['fd'], ['user_id' => $userM->id, 'nickname'=>$userM->nickname]);
        } else {
            $is_first = false;
        }

//        if (!$userOnline) {
//            $server->push($userOnline['fd'], $tool->writeJson(WebSocketStatus::STATUS_LOGIN_ERROR, WebSocketStatus::$msg[WebSocketStatus::STATUS_LOGIN_ERROR]));
//        }

        $users = Login::getInstance()->getUsersInRoom($aMessage['matchId']);
        $atUserInfo = AdminUser::getInstance()->find($aMessage['atUserId']);
        if ($aMessage['atUserId'] && !$atUserInfo) {
            $server->push($userOnline['fd'], $tool->writeJson(WebSocketStatus::STATUS_USER_NOT_FOUND, WebSocketStatus::$msg[WebSocketStatus::STATUS_USER_NOT_FOUND]));
            return;
        }
        $returnData = [
            'event' => 'broadcast-roomBroadcast',
            'data' => [
                'sender_user_info' => [
                    'id' => $is_first ? $userM['id'] : $userOnline['user_id'],
                    'nickname' => $is_first ? $userM['nickname'] : $userOnline['nickname'],
                ],
                'message_info' => [
                    'id' => $insertId,
                    'content' => $aMessage['content']
                ],


            ],

        ];
        if ($atUserInfo) {
            $returnData['data']['at_user_info'] = $atUserInfo;
        } else {
            $returnData['data']['at_user_info'] = [];
        }


        if ($users) {
            foreach ($users as $user) {
                $connection = $server->connection_info($user);

                if (is_array($connection) && $connection['websocket_status'] == 3) {  // 用户正常在线时可以进行消息推送
                    $server->push($user, $tool->writeJson(WebSocketStatus::STATUS_SUCC, WebSocketStatus::$msg[WebSocketStatus::STATUS_SUCC], $returnData));
                }
            }
        }
        return true;



    }

    function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        throw $throwable;
    }

}