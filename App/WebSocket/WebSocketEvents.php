<?php

namespace App\WebSocket;

use App\lib\pool\Login;
use App\lib\pool\Login as Base;
use App\lib\Tool;
use App\Model\AdminUser;
use App\Storage\ChatMessage;
use App\Storage\OnlineUser;
use App\Task\BroadcastTask;
use App\Utility\Gravatar;
use App\WebSocket\Actions\Broadcast\BroadcastAdmin;
use App\WebSocket\Actions\User\UserInRoom;
use App\WebSocket\Actions\User\UserOutRoom;
use easySwoole\Cache\Cache;
use EasySwoole\Utility\Random;
use \swoole_server;
use \swoole_websocket_server;
use \swoole_http_request;
use EasySwoole\EasySwoole\ServerManager;
use App\Model\AdminUser as UserModel;
use \Exception;
use EasySwoole\EasySwoole\Task\TaskManager;
use App\Utility\Log\Log;
/**
 * WebSocket Events
 * Class WebSocketEvents
 * @package App\WebSocket
 */
class WebSocketEvents
{
    static function onWorkerStart()
    {

    }

    /**
     * @param swoole_websocket_server $server
     * @param swoole_http_request $request
     * @return bool
     */
    static function onOpen(\swoole_websocket_server $server, \swoole_http_request $request)
    {

        $fd = $request->fd;
        $data = ['fd' => $fd];
        $user = OnlineUser::getInstance()->get($fd);
        if ($user) {
            $data['user'] = $user;
        } else {
            $data['user'] = [];
        }



        //如果已经有设备登陆,则强制退出, 根据后台配置是否允许多终端登陆
        if (false) {
//            self::userClose($server, $info['id']);
        }
        //推送消息
//        Login::getInstance()->sadd("members:".$info['id'], $fd);
        // 发送欢迎消息给用户
        if ($old = Cache::get($fd)) {
//                $oldHashKey = Login::getInstance()->getUserKey($info['id'], $old);
//                Login::getInstance()->del($oldHashKey);
//                Login::getInstance()->lrem(Login::ONLINE_USER_QUEUES, 0, $old);
        }

        //分配对应mid写入redis队列
        $mid = Login::getInstance()->getMid();

        //记录房间内用户
        $resp = [
            'fd' => $fd,
            'mid' => $mid
        ];
        Cache::set($fd, $mid);
        $server->push($fd, Tool::getInstance()->writeJson(WebSocketStatus::STATUS_SUCC, WebSocketStatus::$msg[WebSocketStatus::STATUS_SUCC], $resp));

    }

    static function userClose($server, $iUserId)
    {
        $fds = Base::getInstance()->smembers("members:".$iUserId);
        foreach ($fds as $fd) {
            // 移除用户并广播告知
            OnlineUser::getInstance()->delete($fd);
            $broadcastAdminMessage = new BroadcastAdmin;
            $broadcastAdminMessage->setContent("您在别的地方登陆，这边被强制下线");
            $server->push($fd, $broadcastAdminMessage->__toString());
            ServerManager::getInstance()->getSwooleServer()->close($fd);
        }
        Base::getInstance()->del("members:".$iUserId);

    }
    /**
     * 链接被关闭时
     * @param swoole_server $server
     * @param int $fd
     * @param int $reactorId
     * @throws Exception
     */
    static function onClose(\swoole_server $server, int $fd, int $reactorId)
    {

        OnlineUser::getInstance()->delete($fd);

        $info = $server->connection_info($fd);
        if (isset($info['websocket_status']) && $info['websocket_status'] !== 0) {
            // 移除用户并广播告知
           if ($mid = Cache::get($fd)) {
                Login::getInstance()->del($mid);
                Login::getInstance()->lrem(Login::ONLINE_USER_QUEUES, 0, $mid);
               // 移除用户并广播告知
               $userOnline = OnlineUser::getInstance()->get($mid);
               $hashKey =  Login::getInstance()->getUserKey($userOnline['user_id'], $mid);;
               Login::getInstance()->del($hashKey);
               OnlineUser::getInstance()->close($mid);

            }

            $message = new UserOutRoom;
            $message->setUserFd($fd);
//            TaskManager::getInstance()->async(new BroadcastTask(['payload' => $message->__toString(), 'fromFd' => $fd]));
            ServerManager::getInstance()->getSwooleServer()->close($fd);

        }
    }
}
