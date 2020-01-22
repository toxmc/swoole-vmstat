<?php

/**
 * Web VMStat 监控
 * @author xmc
 * 2015-5-5
 */
swoole_async_set(['enable_coroutine' => false]);

$vmstat_path = '/usr/bin/vmstat'; //vmstat命令绝对路径
$interval = 1; //vmstat 命令参数。指定每个报告之间的时间量
$count = 1000000000; //vmstat 命令参数。决定生成的报告数目和相互间隔的秒数
$host = "0.0.0.0";    //代表监听全部地址
$port = 8888;    //监听端口号

/**
 * MasterPid命令时格式化输出
 * ManagerPid命令时格式化输出
 * WorkerId命令时格式化输出
 * WorkerPid命令时格式化输出
 * @var int
 */
$config = array(
    'max_master_pid_length'  => 12,
    'max_manager_pid_length' => 12,
    'max_worker_id_length'   => 12,
    'max_worker_pid_length'  => 12,
);


$table = new swoole_table(1024);
$table->column('cmd', swoole_table::TYPE_STRING, 32);
$table->column('interval', swoole_table::TYPE_INT, 2);
$table->column('count', swoole_table::TYPE_STRING, 10);
$table->create();
$table->set('vmstat', array(
    'cmd'      => $vmstat_path,
    'interval' => $interval,
    'count'    => $count
));

/**
 * 创建一个子进程
 * 子进程创建成功后调用函数callback_vmstat
 */
$process = new swoole_process('callback_vmstat', true);
$pid = $process->start();
/**
 * swoole_process回调函数
 * @param swoole_process $worker
 */
function callback_vmstat(swoole_process $worker)
{
    global $table;
    $vmstat = $table->get('vmstat');
    $cmd = $vmstat['cmd'];
    $interval = $vmstat['interval'];
    $count = $vmstat['count'];
    $worker->exec($cmd, array(
        $interval,
        $count
    ));
}

$try = 3;
while ($try--) {
    if ($pid) {
        $ret = swoole_process::wait(false);
        if ($ret) {
            CreateProcess($process);
        }
    }
    usleep(100000);
}

/**
 * wsl1无法使用vmstat命令，模拟命令使用
 * @param $process
 */
function CreateProcess(&$process)
{
    $process = new swoole_process(function (swoole_process $worker) {
        swoole_set_process_name(sprintf('php-ps:%s', 'vmstat'));
        $data = [
            " 1  0      0 7875564 196540 2772784    0    0     0    19    0    0  1  1 98  0  0",
            " 0  0      0 7875784 196540 2772804    0    0     0    76 2305 3946  1  0 99  0  0",
            " 0  0      0 7875576 196540 2772804    0    0     0     0 2783 4207  1  1 98  0  0",
            " 0  0      0 7875756 196540 2772804    0    0     0     0 2833 4189  1  1 98  0  0",
            " 0  0      0 7876212 196540 2772804    0    0     0     0 2309 3969  1  0 99  0  0",
            " 0  0      0 7875312 196540 2772804    0    0     0     0 2420 4036  1  0 99  0  0",
            " 0  0      0 7875296 196540 2772804    0    0     0     0 2632 4095  1  1 98  0  0",
            " 0  0      0 7875344 196540 2772804    0    0     0     0 2112 3876  1  0 99  0  0",
            " 0  0      0 7874904 196540 2772804    0    0     0     0 2949 4279  1  1 98  0  0",
        ];
        $len = count($data);
        for ($j = 0; $j < 16000; $j++) {
            sleep(1);
            echo $data[rand(0, $len - 1)];
        }
    }, true);
    $pid = $process->start();
}

/**
 * 创建一个websocket服务器
 * 端口8888
 */
$server = new swoole_websocket_server($host, $port);
$server->table = $table;    //将table保存在serv对象上

/**
 * websocket server配置
 */
$server->set(array(
    'worker_num'               => 1,
    //worker进程数量
    'daemonize'                => false,
    //守护进程设置成true
    'max_request'              => 1000,
    //最大请求次数，当请求大于它时，将会自动重启该worker
    'log_file'                 => 'log/swoole.log',
    //指定swoole错误日志文件
    'dispatch_mode'            => 2,
    // 1 和 3底层会屏蔽onConnect/onClose事件
    'heartbeat_idle_time'      => 20,
    //超过连接最大时间 自动关闭
    'heartbeat_check_interval' => 10,
    //每隔多长时间遍历一次客户端fd
    'document_root'            => dirname(__DIR__) . '/web',
    // v4.4.0以下版本, 此处必须为绝对路径
    'enable_static_handler'    => true,
));

//心跳检测
$process_ping = new swoole_process(function ($process_ping) use ($server) {
    swoole_timer_tick(10000, function () use ($server) {
        foreach ($server->connections as $fd) {
            if ($server->isEstablished($fd)) {
                if ($server->connection_info($fd)) {
                    // 发送ping包
                    $server->push($fd, 'ping');
                } else {
                    $server->close($fd);
                }
            }
        }
    });
});

$server->addprocess($process_ping);

/**
 * websocket server start
 * 成功后回调
 */
$server->on('start', function ($serv) use ($config, $host, $port) {
    echo "\033[1A\n\033[K-----------------------\033[47;30m SWOOLE \033[0m-----------------------------\n\033[0m";
    echo 'swoole:' . swoole_version() . "\tphp:" . PHP_VERSION . "\thost:{$host}\tport:{$port}\n";
    echo "------------------------\033[47;30m WORKERS \033[0m---------------------------\n";
    echo "\033[47;30mMasterPid\033[0m", str_pad('', $config['max_master_pid_length'] + 2 - strlen('MasterPid')),
    "\033[47;30mManagerPid\033[0m", str_pad('', $config['max_manager_pid_length'] + 2 - strlen('ManagerPid')),
    "\033[47;30mWorkerId\033[0m", str_pad('', $config['max_worker_id_length'] + 2 - strlen('WorkerId')),
    "\033[47;30mWorkerPid\033[0m", str_pad('', $config['max_worker_pid_length'] + 2 - strlen('WorkerPid')), "\n";
});

/**
 * 当WebSocket客户端与服务器建立连接并完成握手后会回调此函数。
 */
$server->on('open', function (swoole_websocket_server $server, $request) {
    echo "server: handshake success with fd{$request->fd}\n";
    $server->push($request->fd, "procs -----------memory---------- ---swap-- -----io---- -system-- ----cpu----\n");
    $server->push($request->fd, "r  b   swpd   free   buff  cache   si   so    bi    bo   in   cs us sy id wa\n");
});

/**
 * 当服务器收到来自客户端的数据帧时会回调此函数。
 */
$server->on('message', function (swoole_websocket_server $server, $frame) {
// 	echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";
// 	$server->push($frame->fd, "this is server");
    if ($frame->data == 'pong') {
         echo "接收pong包\n";
    }
});

/**
 * websocket server继承于http server所以也可以处理http请求，这里用来处理静态文件
 */
$server->on('request', function (swoole_http_request $request, swoole_http_response $response) {
    $uri = $request->server['request_uri'] ?? null;
    $blackList = ['php', 'htaccess', 'config'];
    $extension = substr(strrchr($uri, '.'), 1);
    if ($extension && in_array($extension, $blackList)) {
        $response->status(403);
        $response->end("403 Forbidden");
        return;
    }

    // 重定向到首页
    if (in_array($uri, [null, '/', '/index'])) {
        $uri = "/index.html";
    }

    $publicPath = dirname(__DIR__)."/web";
    $filename = $publicPath . $uri;
    if (! is_file($filename) || filesize($filename) === 0) {
        $response->status(404);
        $response->end("404 Not Found");
        return;
    }
    $response->status(200);
    $mime = mime_content_type($filename);
    if ($extension === 'js') {
        $mime = 'text/javascript';
    } elseif ($extension === 'css') {
        $mime = 'text/css';
    }
    $response->header('Content-Type', $mime);
    $response->sendfile($filename);
});

/**
 * 当客户端关闭的时候调用
 */
$server->on('close', function ($ser, $fd) {
    echo "client {$fd} closed\n";
});

/**
 * 当worker 启动的时候调用
 */
$server->on('workerStart', function ($serv, $worker_id) use ($process, $config) {
    echo str_pad($serv->master_pid, $config['max_master_pid_length'] + 2),
    str_pad($serv->manager_pid, $config['max_manager_pid_length'] + 2),
    str_pad($serv->worker_id, $config['max_worker_id_length'] + 2),
    str_pad($serv->worker_pid, $config['max_worker_pid_length']), "\n";

    //将process->pipe加入到swoole的reactor事件监听中
    swoole_event_add($process->pipe, function ($pipe) use ($process, $serv) {
        $str = $process->read();
        $conn_list = $serv->connection_list();
        if (!empty($conn_list)) {
            foreach ($conn_list as $fd) {
                if ($serv->isEstablished($fd)) {
                    $info = $serv->connection_info($fd);
                    if (!empty($info)) {
                        $serv->push($fd, $str);
                    } else {
                        $serv->close($fd);
                    }
                }
            }
        }
    });
});

$server->start();