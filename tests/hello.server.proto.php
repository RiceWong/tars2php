<?php
/**
 * Created by PhpStorm.
 * User: liangchen
 * Date: 2018/2/24
 * Time: 下午3:43.
 */

return array(
    'appName' => 'Hello',
    'serverName' => 'Server',
    'objName' => 'Obj',
    'withServant' => true, //决定是服务端,还是客户端的自动生成
    'tarsFiles' => array(
        './Hello.tars',
    ),
    'dstPath' => './server/',
    'namespacePrefix' => 'Server\servant',
);