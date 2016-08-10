<?php

/**
 * Web VMStat 监控
 * @author xmc
 * 2015-5-5
 */

$vmstat_path = '/usr/bin/vmstat'; //vmstat命令绝对路径
$interval = 1; //vmstat 命令参数。指定每个报告之间的时间量
$count = 1000000000; //vmstat 命令参数。决定生成的报告数目和相互间隔的秒数
$host = "0.0.0.0";	//代表监听全部地址 
$port = 8888;	//监听端口号

/**
 * MasterPid命令时格式化输出
 * ManagerPid命令时格式化输出
 * WorkerId命令时格式化输出
 * WorkerPid命令时格式化输出
 * @var int
 */
$config = array(
    'max_master_pid_length' => 12,
    'max_manager_pid_length' => 12,
    'max_worker_id_length' => 12,
    'max_worker_pid_length' => 12,
);


$table = new swoole_table(1024);
$table->column('cmd', swoole_table::TYPE_STRING, 32);
$table->column('interval', swoole_table::TYPE_INT, 2);
$table->column('count', swoole_table::TYPE_STRING, 10);
$table->create();
$table->set('vmstat', array('cmd' => $vmstat_path,'interval' => $interval, 'count' => $count));

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
function callback_vmstat(swoole_process $worker) {
    global $table;
    $vmstat = $table->get('vmstat');
    $cmd  = $vmstat['cmd'];
    $interval = $vmstat['interval'];
    $count = $vmstat['count'];
    $worker->exec($cmd, array($interval, $count));
}

/**
 * 创建一个websocket服务器
 * 端口8888
 */
$server = new swoole_websocket_server($host, $port);
$server->table = $table;	//将table保存在serv对象上

/**
 * websocket server配置
 */
$server->set (array(
	'worker_num' => 1,		//worker进程数量
	'daemonize' => false,	//守护进程设置成true
	'max_request' => 1000,	//最大请求次数，当请求大于它时，将会自动重启该worker
	'log_file' => 'log/swoole.log', //指定swoole错误日志文件
	'dispatch_mode' => 2,  // 1 和 3底层会屏蔽onConnect/onClose事件
    'heartbeat_idle_time' => 20,       //超过连接最大时间 自动关闭
    'heartbeat_check_interval' => 10,   //每隔多长时间遍历一次客户端fd
));

//心跳检测
$process_ping = new swoole_process(function($process_ping) use ($server) {
    swoole_timer_tick(10000,function() use ($server) {
        foreach ($server->connections as $fd) {
            if ($server->connection_info($fd)) {
                // 发送ping包
                $server->push($fd, 'ping');
            } else {
                $server->close($fd);
            }
        }
    });
});

$server->addprocess($process_ping);

/**
 * websocket server start
 * 成功后回调
 */
$server->on('start', function ($serv) use($config) {
	echo "\033[1A\n\033[K-----------------------\033[47;30m SWOOLE \033[0m-----------------------------\n\033[0m";
	echo 'swoole version:' . swoole_version() . "          PHP version:".PHP_VERSION."\n";
	echo "------------------------\033[47;30m WORKERS \033[0m---------------------------\n";
	echo "\033[47;30mMasterPid\033[0m", str_pad('', $config['max_master_pid_length'] + 2 - strlen('MasterPid')),
		 "\033[47;30mManagerPid\033[0m", str_pad('', $config['max_manager_pid_length'] + 2 - strlen('ManagerPid')),
		 "\033[47;30mWorkerId\033[0m", str_pad('', $config['max_worker_id_length'] + 2 - strlen('WorkerId')),
		 "\033[47;30mWorkerPid\033[0m", str_pad('', $config['max_worker_pid_length'] + 2 - strlen('WorkerPid')),"\n";
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
//         echo "接收pong包\n";
    }
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
$server->on('workerStart',function ($serv, $worker_id) use($process, $config) {
	echo str_pad($serv->master_pid, $config['max_master_pid_length']+2),
		 str_pad($serv->manager_pid, $config['max_manager_pid_length']+2),
		 str_pad($serv->worker_id, $config['max_worker_id_length']+2), 
		 str_pad($serv->worker_pid, $config['max_worker_pid_length']), "\n";
	
	//将process->pipe加入到swoole的reactor事件监听中
	swoole_event_add($process->pipe, function($pipe) use($process, $serv) {
	    $str = $process->read();
	    $conn_list = $serv->connection_list();
	    if (! empty($conn_list)) {
	        foreach($conn_list as $fd) {
	            $info = $serv->connection_info($fd);
	            if (!empty($info)) {
	                $serv->push($fd, $str);
	            } else {
	                $serv->close($fd);
	            }
	        }
	    }
	});
});

$server->start();
