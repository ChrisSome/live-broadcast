<?php

namespace App\WebSocket;

use App\lib\pool\Login as Base;
use App\lib\Tool;
use App\Model\AdminUser;
use App\Storage\OnlineUser;
use App\WebSocket\Actions\Broadcast\BroadcastAdmin;
use App\WebSocket\Actions\User\UserOutRoom;
use easySwoole\Cache\Cache;
use EasySwoole\Utility\Random;
use \swoole_server;
use \swoole_websocket_server;
use \swoole_http_request;
use EasySwoole\EasySwoole\ServerManager;
use \Exception;
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
        $user_online = OnlineUser::getInstance()->get($fd);
        //这里也可以做一个唯一标志 考虑以后有用
        $mid = uniqid($fd . '-');
        $user_id = $request->get['user_id'];
        if ($user_id) {
            $user = AdminUser::getInstance()->where('id', $user_id)->get();
        }
        //如果已经有设备登陆,则强制退出, 根据后台配置是否允许多终端登陆

        $info = [
            'fd' => $fd,
            'nickname' => isset($user) ? $user->nickname : '',
            'token' => '',
            'user_id' => isset($user) ? $user->id : 0,
            'level' => isset($user) ? $user->level : 0,
        ];
        if (!$user_online) {
            OnlineUser::getInstance()->set($fd, $info);
        } else {
            OnlineUser::getInstance()->update($fd, $info);
        }

        $resp_info = [
            'event' => 'connection-succ',
            'info' => ['fd' => $fd, 'mid' => $mid],

        ];

        $server->push($fd, Tool::getInstance()->writeJson(WebSocketStatus::STATUS_SUCC, WebSocketStatus::$msg[WebSocketStatus::STATUS_SUCC], $resp_info));

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
           if ($mid = Cache::get($fd)) {
               // 移除用户并广播告知
               OnlineUser::getInstance()->delete($fd);

            }

            $message = new UserOutRoom;
            $message->setUserFd($fd);
//            TaskManager::getInstance()->async(new BroadcastTask(['payload' => $message->__toString(), 'fromFd' => $fd]));
            ServerManager::getInstance()->getSwooleServer()->close($fd);

        }
    }
}
