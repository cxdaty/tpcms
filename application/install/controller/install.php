<?php

defined('IN_SYS') or exit('No permission resources.');
bizma_cjfaster::ld_my_class('cpanel', 'cpanel', 0);


class install {

    public function index() {

        include template('install', 'index');
    }

    public function step2() {
        //环境检测
        $env = $this->check_env();
        //文件目录检测
        $dirfile = $this->check_dirfile();
        //函数检测
        $fun = $this->check_fun();
        include template('install', 'step2');
    }

    public function step3() {

        include template('install', 'step3');
    }

    public function step4($db = null) {
        
        $DB = isset($_POST['db']) ? $_POST['db'] : '';
        if(!is_array($DB) || empty($DB[0])|| empty($DB[1])|| empty($DB[2])|| empty($DB[3])){
           echo '请填写完整的数据库配置';
           exit();
        }else{
           //缓存数据库配置
           $_SESSION['db_config']= $DB; 
           //创建数据库
           $dbname = $DB['database'];
           $link=@new mysqli("$DB[1]:$DB[5]",$DB[3],$DB[4]);
          
           // 设置字符集
           $link->query("SET NAMES 'utf8'");           
            // 获取错误信息
            $error=$link->connect_error;
            if (!is_null($error)) {
                // 转义防止和alert中的引号冲突
                $error=addslashes($error);
                die("<script>alert('数据库链接失败:$error');history.go(-1)</script>");
            } 
            // 创建数据库并选中
            if(!$link->select_db($DB[2])){
                $create_sql='CREATE DATABASE IF NOT EXISTS '.$DB[2].' DEFAULT CHARACTER SET utf8;';
                $link->query($create_sql) or die('创建数据库失败');
                $link->select_db($DB[2]);
            }
            // 导入sql数据并创建表
            $sql = file_get_contents(realpath('') . '/ext/database/install.sql');
            $sql_array=preg_split("/;[\r\n]+/", str_replace('biz_',$DB[6],$sql));
            foreach ($sql_array as $k => $v) {
                if (!empty($v)) {
                    $link->query($v);
                }
            }
            $link->close();exit;
//            else{
//               $dbconfig = $_SESSION['db_config'];
//               $prefix = $dbconfig['prefix'];
//               
//               //创建数据表
//               $this->create_tables($db,$prefix);
//               //写入配置文件
//               $this->write_config($dbconfig);              
//               $_SESSION['_install_step']= 4; 
//           }
        }
       
        include template('install', 'step4');
    }

    /**
     * 环境监测函数
     */
    function check_env() {
        $items = array(
            'os' => array('操作系统', '不限制', PHP_OS, 'success'),
            'php' => array('PHP版本', '5.4', PHP_VERSION, 'success'),
            'upload' => array('附件上传', '不限制', '未知', 'success'),
            'gd' => array('GD库', '2.0', '未知', 'success'),
            'disk' => array('磁盘空间', '5M', '未知', 'success'),
        );

        //PHP环境检测
        if ($items['php'][2] < $items['php'][1]) {
            $items['php'][3] = 'error';
            session('error', true);
        }

        //附件上传检测
        if (@ini_get('file_uploads'))
            $items['upload'][2] = ini_get('upload_max_filesize');

        //GD库检测
        $tmp = function_exists('gd_info') ? gd_info() : array();
        if (empty($tmp['GD Version'])) {
            $items['gd'][2] = '未安装';
            $items['gd'][3] = 'error';
            session('error', true);
        } else {
            $items['gd'][2] = $tmp['GD Version'];
        }
        unset($tmp);

        //磁盘空间检测
        if (function_exists('disk_free_space')) {
            $items['disk'][2] = floor(disk_free_space(realpath('') . '/') / (1024 * 1024)) . 'M';
        }

        return $items;
    }
    
    /**
    * 目录，文件读写检测
    * @return array 检测数据
    */
    function check_dirfile(){
        define('INSTALL_APP_PATH', realpath('') . '/');
        $items = array(
            array('dir',  '可写', 'success', '/'),
            array('dir',  '可写', 'success', '/app'),
            array('dir',  '可写', 'success', '/file'),
            array('file', '可写', 'success', '/ext'),
        );
        
        foreach($items as $val){
            if('dir' == $val[0]){
                if(!is_writeable(INSTALL_APP_PATH . $val[3])) {
                    if(is_dir($val[3])){
                       $val[1] = '可读';
                       $val[2] = 'error';
                       $_SESSION['error']=true;
                    }else{
                        $val['1'] = '不存在';
                        $val['2'] = 'error';
                        $_SESSION['error']=true;
                    }
                }else{
                    if(file_exists(INSTALL_APP_PATH . $val[3])){
                        if(!is_writeable(INSTALL_APP_PATH . $val[3])){
                           $val[1] = '不可写';
                           $val[2] = 'error'; 
                           $_SESSION['error']=true;
                        }
                    }else{
                        if(!is_writable(dirname(INSTALL_APP_PATH . $val[3]))){
                            $val[1] = '不存在';
                            $val[2] = 'error';
                            $_SESSION['error']=true;
                        }
                    }
                }
            }
        }
        return $items;
    }
    
    
    function check_fun(){
        $items = array(
            array('iconv','支持','success'),
            array('file_get_contents','支持','success'),
            array('mb_strlen','支持','success'),
        );
        
        foreach($items as &$val){
            if(!function_exists($val[0])){
                $val[1] = '不支持';
                $val[2] = 'error';
                $val[3] = '开启';
                $_SESSION['error']=true;
            }
        }
        return $items;
    }
    
    /**
    * 创建数据表
    * @param  resource $db 数据库连接资源
    */
    function create_tables($db,$prefix){ 
        //读取SQL文件
        $sql = file_get_contents(realpath('') . '/ext/database/install.sql');
        $sql_array=preg_split("/;[\r\n]+/", str_replace('biz_',$prefix,$sql));
        foreach ($sql_array as $k => $v) {
            if (!empty($v)) {
                $db->query($v);
            }
        }
        $db->close();
        
        
        $sql = str_replace("\r","\n",$sql);
        $sql = explode(":\n",$sql);
        
        $sql = str_replace(" `biz_", " `$prefix", $sql);
        //开始安装
       // showmessage('开始安装数据表............');
        foreach($sql as $value){
            $value = trim($value);
            if(empty($value)) continue;
            if(substr($value,0,12) == 'CREATE TABLE'){
              $name = preg_replace("/^CREATE TABLE `(\w+)` .*/s", "\\1", $value);
              $msg = "创建数据表{$name}";             
              if(false !== $db->query($value)){
                $this->show_msg($msg . '......[成功]');
              }else{
               $this->show_msg($msg . '......[失败]', 'error');  
              }
            }else{
                $db->query($value);
            }
        }
        $this->show_msg('数据表安装完成............');
    }
    
    /**
     * 写入配置文件
     * @param  array $config 配置信息
     */
    
    function write_config($config){
        
        if(is_array($config)){
            //读取配置内容
            $get_database = file_get_contents(ROOT_PATH . '/ext/database/database.tpl');
            //替换配置项
            foreach($config as $name => $value){
                $get_database = str_replace("[{$name}]",$value,$get_datebase);
            }
            $this->show_msg("开始写入配置文件.............",'green');
            //写入应用配置文件
            if(!file_put_contents(APP_PATH . 'database.php', $get_database)){
                $this->show_msg("配置文件写入失败...",'red');
            }else{
                $this->show_msg("配置文件写入成功............",'green');
            }
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

}
