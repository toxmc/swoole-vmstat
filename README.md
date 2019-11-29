# swoole-vmstat
本项目只作为swoole入门示例
> 如果有使用`laravel` 的朋友，推荐另外一个项目[fast-laravel](https://github.com/toxmc/fast-laravel).欢迎使用，喜欢的话给个star鼓励下。谢谢各位

## 演示地址
* http://ip:port
> ip=127.0.0.1，port=8888 演示地址 http://127.0.0.1:8888

## 依赖

* PHP 5.3+
* Swoole 1.7.16
* Linux, OS X and basic Windows support (Thanks to cygwin)

## 安装 Swoole扩展

1. Install via pecl
    
    ```
    pecl install swoole
    ```

2. Install from source

    ```
    sudo apt-get install php5-dev
    git clone https://github.com/swoole/swoole-src.git
    cd swoole-src
    phpize
    ./configure
    make && make install
    ```
    
## 运行

1. `cd swoole-vmstat/server`
2. `php server.php`
3. 修改`web`目录下`stats.js`代码 `var ws = new ReconnectingWebSocket("ws://192.168.1.10:8888");` 改成服务器的IP
4. 用浏览器打开`web`目录下的`index.html`或打开地址 `http://ip:port`

## 运行结果

1. 打开页面如下所示
![one](https://raw.githubusercontent.com/smalleyes/swoole-vmstat/master/doc/vmstat-web.png)
![two](https://raw.githubusercontent.com/smalleyes/swoole-vmstat/master/doc/vmstat-web1.png)

## nginx配置文件
在`doc/swoole-vmstat.conf`
## workerman实现
[传送门](https://github.com/walkor/workerman-vmstat)
