<?php

/**
 * Web VMStat 监控
 * @author xmc
 * 2015-5-5
 */

$server = new swoole_websocket_server("0.0.0.0", 8888);
$server->set (array(
		'worker_num' => 1,		//worker进程数量
		'daemonize' => false,	//守护进程设置成true
		'max_request' => 1000,	//最大请求次数，当请求大于它时，将会自动重启该worker
		'dispatch_mode' => 1
));

$server->on('open', function (swoole_websocket_server $server, $request) {
	echo "server: handshake success with fd{$request->fd}\n";
	$server->push($request->fd, "procs -----------memory---------- ---swap-- -----io---- -system-- ----cpu----\n");
	$server->push($request->fd, "r  b   swpd   free   buff  cache   si   so    bi    bo   in   cs us sy id wa\n");
});

$server->on('message', function (swoole_websocket_server $server, $frame) {
// 	echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";
// 	$server->push($frame->fd, "this is server");
	
});

$server->on('close', function ($ser, $fd) {
	echo "client {$fd} closed\n";
});

$server->on('workerStart',function ($serv, $worker_id) {
	echo "WorkerStart: MasterPid={$serv->master_pid}|Manager_pid={$serv->manager_pid}|WorkerId={$serv->worker_id}|WorkerPid={$serv->worker_pid}\n";
	$serv->addtimer(2000); //20ms
});

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