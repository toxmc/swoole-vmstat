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
$_maxMasterPidLength = 12;
$_maxManagerPidLength = 12;
$_maxWorkerIdLength = 12;
$_maxWorkerPidLength = 12;

/**
 * 创建一个websocket服务器
 * 端口8888
 */
$table = new swoole_table(1024);
$table->column('cmd', swoole_table::TYPE_STRING, 32);
$table->column('interval', swoole_table::TYPE_INT, 2);
$table->column('count', swoole_table::TYPE_STRING, 10);
$table->create();
$table->set('vmstat', array('cmd' => $vmstat_path,'interval' => $interval, 'count' => $count));

$server = new swoole_websocket_server($host, $port);
$server->table = $table;	//将table保存在serv对象上

/**
 * 创建一个子进程
 * 子进程创建成功后调用函数callback_vmstat
 */
$process = new swoole_process('callback_vmstat', true);
$pid = $process->start();

/**
 * websocket server配置
 */
$server->set (array(
		'worker_num' => 1,		//worker进程数量
		'daemonize' => false,	//守护进程设置成true
		'max_request' => 1000,	//最大请求次数，当请求大于它时，将会自动重启该worker
		'dispatch_mode' => 1
));

/**
 * websocket server start
 * 成功后回调
 */
$server->on('start', function ($serv) use($_maxMasterPidLength, $_maxManagerPidLength, $_maxWorkerIdLength, $_maxWorkerPidLength) {
	echo "\033[1A\n\033[K-----------------------\033[47;30m SWOOLE \033[0m-----------------------------\n\033[0m";
	echo 'swoole version:' . swoole_version() . "          PHP version:".PHP_VERSION."\n";
	echo "------------------------\033[47;30m WORKERS \033[0m---------------------------\n";
	echo "\033[47;30mMasterPid\033[0m", str_pad('', $_maxMasterPidLength + 2 - strlen('MasterPid')),
		 "\033[47;30mManagerPid\033[0m", str_pad('', $_maxManagerPidLength + 2 - strlen('ManagerPid')),
		 "\033[47;30mWorkerId\033[0m", str_pad('', $_maxWorkerIdLength + 2 - strlen('WorkerId')),
		 "\033[47;30mWorkerPid\033[0m", str_pad('', $_maxWorkerPidLength + 2 - strlen('WorkerPid')),"\n";

	global $process;
	//将process->pipe加入到swoole的reactor事件监听中
	swoole_event_add($process->pipe, function($pipe) use($process, $serv) {
		$str = $process->read();
		$conn_list = $serv->connection_list();
		if (!empty($conn_list)) {
			foreach($conn_list as $fd) {
				$serv->push($fd, $str);
			}
		}
	});
});

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
$server->on('workerStart',function ($serv, $worker_id) use($_maxMasterPidLength, $_maxManagerPidLength, $_maxWorkerIdLength, $_maxWorkerPidLength) {
	echo str_pad($serv->master_pid, $_maxMasterPidLength+2),
		 str_pad($serv->manager_pid, $_maxManagerPidLength+2),
		 str_pad($serv->worker_id, $_maxWorkerIdLength+2), 
		 str_pad($serv->worker_pid, $_maxWorkerIdLength), "\n";;
// 	$serv->addtimer(2000); //2000ms
});

/**
 * addtime 回调
 * 弃用这种方式，用事件循环的方式实现
 */
$server->on('Timer', function ($serv, $interval) {
	$conn_list = $serv->connection_list();
	if (!empty($conn_list)) {
		$str = exec('vmstat',$string);
		foreach($conn_list as $fd) {
			$serv->push($fd, $str);
		}
	}
});

$server->start();