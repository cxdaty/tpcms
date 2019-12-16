<?php

use think\Lang;
use think\Db;
use think\Config;
use think\Session;
/**
 * 安装模块公共函数库
 */

/**
 * 环境监测函数
 */
function check_env()
{
    $items = array(
        'os'      => array('操作系统', '不限制', PHP_OS, 'success'),
        'php'     => array('PHP版本', '5.4', PHP_VERSION, 'success'),
        'upload'  => array('附件上传', '不限制', '未知', 'success'),
        'gd'      => array('GD库', '2.0', '未知', 'success'),
        'disk'    => array('磁盘空间', '5M', '未知', 'success'),
    );

    //PHP环境检测
    if($items['php'][2] < $items['php'][1]){
        $items['php'][3] = 'error';
        session('error', true);
    }

    //附件上传检测
    if(@ini_get('file_uploads'))
        $items['upload'][2] = ini_get('upload_max_filesize');

    //GD库检测
    $tmp = function_exists('gd_info') ? gd_info() : array();
    if(empty($tmp['GD Version'])){
        $items['gd'][2] = '未安装';
        $items['gd'][3] = 'error';
        session('error', true);
    } else {
        $items['gd'][2] = $tmp['GD Version'];
    }
    unset($tmp);

    //磁盘空间检测
    if(function_exists('disk_free_space')) {
        $items['disk'][2] = floor(disk_free_space(INSTALL_APP_PATH) / (1024*1024)).'M';
    }

    return $items;
}



/**
 * 目录，文件读写检测
 * @return array 检测数据
 */
function check_dirfile()
{
    $items = array(
        array('dir',  '可写', 'success', 'public/uploads'),
        array('file', '可写', 'success', 'application/config.php'),
        array('file', '可写', 'success', 'application/database.php'),
        array('dir',  '可写', 'success', 'runtime'),
        array('dir',  '可写', 'success', 'data'),

    );

    foreach ($items as &$val) {
        if('dir' == $val[0]){
            if(!is_writable(INSTALL_APP_PATH . $val[3])) {
                if(is_dir($val[3])) {
                    $val[1] = '可读';
                    $val[2] = 'error';
                    session('error', true);
                } else {
                    $val[1] = '不存在';
                    $val[2] = 'error';
                    session('error', true);
                }
            }
        } else {
            if(file_exists(INSTALL_APP_PATH . $val[3])) {
                if(!is_writable(INSTALL_APP_PATH . $val[3])) {
                    $val[1] = '不可写';
                    $val[2] = 'error';
                    session('error', true);
                }
            } else {
                if(!is_writable(dirname(INSTALL_APP_PATH . $val[3]))) {
                    $val[1] = '不存在';
                    $val[2] = 'error';
                    session('error', true);
                }
            }
        }
    }

    return $items;
}


/**
 * 监测函数是否存在
 * @author tangtanglove <dai_hang_love@126.com>
 */
function check_fun()
{
    $items = array(
        array('iconv','支持', 'success'),
        array('file_get_contents', '支持', 'success'),
        array('mb_strlen',         '支持', 'success'),
    );

    foreach ($items as &$val) {
        if(!function_exists($val[0])){
            $val[1] = '不支持';
            $val[2] = 'error';
            $val[3] = '开启';
            session('error', true);
        }
    }

    return $items;
}


/**
 * 创建数据表
 * @param  resource $db 数据库连接资源
 */
function create_tables($db,$prefix)
{
    //读取SQL文件
    $sql = file_get_contents(ROOT_PATH . '/data/install/install.sql');
    $sql = str_replace("\r", "\n", $sql);
    $sql = explode(";\n", $sql);

    //替换表前缀
    $orginal = config('original_table_prefix');
    $sql = str_replace(" `{$orginal}", " `{$prefix}", $sql);
    //开始安装
    show_msg('开始安装数据表............');
    foreach ($sql as $value) {
        $value = trim($value);
        if(empty($value)) continue;
        if(substr($value, 0, 12) == 'CREATE TABLE') {
            $name = preg_replace("/^CREATE TABLE `(\w+)` .*/s", "\\1", $value);
            $msg = "创建数据表{$name}";
            if(false !== $db->execute($value)){
                show_msg($msg . '......[成功]');
            }else{
                show_msg($msg . '......[失败]', 'error');
                session('error', true);            }
        } else {
            $db->execute($value);
        }
    }

    show_msg('数据表安装完成............'); 
}


/**
 * 写入配置文件
 * @param  array $config 配置信息
 */
function write_config($config)
{
    if(is_array($config)){
        //读取配置内容
       
        $get_database = file_get_contents(ROOT_PATH . 'data/install/database.tpl');
        //替换配置项
        foreach ($config as $name => $value) {
          
            $get_database = str_replace("[{$name}]", $value, $get_database);
        }
        show_msg("开始写入配置文件............",'green');
        //写入应用配置文件
        if(!file_put_contents(APP_PATH . 'database.php', $get_database)){
            show_msg("配置文件写入失败...",'red');
        }else{
            show_msg("配置文件写入成功............",'green');
        }
        
    }
}


/**
 * 开始注册创始人账号信息
 */

function sp_create_admin_account($db,$table_prefix){

    show_msg('开始注册创始人账号信息............','green');
    $admin = session('admin_info');
    $username=$admin['username'];
    $password=md5($admin['password'] . Config::get('salt'));
    $create_date=date('Y-m-d H:i:s');
    $ip='127.0.0.1';
    $sql =<<<hello
    INSERT INTO `{$table_prefix}admin_user` 
    (id,username,password,headimgurl,status,create_time,last_login_time,last_login_ip) VALUES 
    ('1', '{$username}', '{$password}', '', '1', '{$create_date}', '{$create_date}','{$ip}');;
hello;
    if($db->execute($sql))
    {
    show_msg("管理员账号创建成功!");
    to_step5();
    }
}


/**
 * 及时显示提示信息
 * @param  string $msg 提示信息
 */
function show_msg($msg, $class = '')
{
    echo "<script type=\"text/javascript\">showmsg(\"{$msg}\", \"{$class}\")</script>";
    flush();
    ob_flush();
}


/**
 * 文件目录检测，可写绿色，不可写红色
 * @param  [type] $str [description]
 * @return [type]      [description]
 */
function get_write_color($str)
{
    if($str == '可写'){
        return '<span style="color:green"><i class="fa fa-check correct"></i>可写</span>';
    }else{
        return '<span style="color:red">不可写</span>';
    }
}

/**
 * 函数检测，支持绿色，不支持红色
 * @param  [type] $str [description]
 * @return [type]      [description]
 */
function get_fun_color($str)
{
    if($str == '支持'){
        return '<span style="color:green">支持</span>';
    }else{
        return '<span style="color:red">不支持</span>';
    }
}

/**
 * 跳转至安装完成
 * @return [type] [description]
 */
function to_step5()
{
    $url = url('index/step5');
    echo "<script type=\"text/javascript\">window.location.href='".$url."';</script>";
    ob_flush();//把数据从PHP的缓冲（buffer）中释放出来
    flush();//把不在缓冲（buffer）中的或者说是被释放出来的数据发送到浏览器
    
}
