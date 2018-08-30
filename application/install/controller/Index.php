<?php
namespace app\install\controller;
use think\Controller;
use think\Request;
use think\Db;
use think\Session;
use think\Config;
/**
 * 安装模块
 * @author  tangtnglove <dai_hang_love@126.com>
 */
class Index extends Controller {
    
       
          protected function _initialize()
          {
              header("Content-type: text/html; charset=utf-8"); 
              // 检测程序安装
              if (is_file(ROOT_PATH . 'data/install.lock')) {
                  echo '程序已经安装！重新安装请删除data目录下install.lock文件';
                  exit();
              }
          }
    
    //首页
    public function index() {
        Session::delete('db_config');
        return $this->fetch('index');
    }
    public function step2() {
        //环境检测
        $env = check_env();
        //函数检测
        $fun = check_fun();
        //文件目录检测
        $dirfile = check_dirfile();
        $this->assign('env', $env);
        $this->assign('fun', $fun);
        $this->assign('dirfile', $dirfile);
        return $this->fetch('');
    }
    public function step3() {
        return $this->fetch('');
    }
        public function step4($db = null, $admin = null) {

        if (request()->isPost()) {

               if(!is_array($admin) || empty($admin[0]) || empty($admin[1])){
                return $this->error('请填写完整管理员信息');
            } else if($admin[1] != $admin[2]){
                return $this->error('确认密码和密码不一致');
            } else {
                $info = array();
                list($info['username'], $info['password'], $info['repassword'])= $admin;

                //缓存管理员信息
                session('admin_info', $info);
            }


            if (!is_array($db) || empty($db[0]) || empty($db[1]) || empty($db[2]) || empty($db[3])) {
                return $this->error('请填写完整的数据库配置');
            } else {
                $DB = array();
                list($DB['type'], $DB['hostname'], $DB['database'], $DB['username'], $DB['password'], $DB['hostport'], $DB['prefix']) = $db;
                //缓存数据库配置
                session('db_config', $DB);
                //创建数据库
                $dbname = $DB['database'];
                unset($DB['database']);
                $db = Db::connect($DB);
                $sql = "CREATE DATABASE IF NOT EXISTS `{$dbname}` DEFAULT CHARACTER SET utf8";
                if (!$db->execute($sql)) {
                    return $this->error($db->getError());
                    exit();
                } else {

                    echo $this->fetch();
                    $dbconfig = session('db_config');
                    $db = Db::connect($dbconfig);
                    $prefix = $dbconfig['prefix'];
                    //创建数据表
                    create_tables($db, $dbconfig['prefix']);
                    //写入配置文件
                    write_config($dbconfig);
                    
                    //创建管理员
                    sp_create_admin_account($db, $prefix);
                    session("_install_step", 4);
                   
                }
            }
        }
    }


    public function step5() {
        if (session("_install_step") == 4) {
            file_put_contents(INSTALL_APP_PATH.'data/install.lock', 'lock');
             Session::delete('_install_step');
              Session::delete('admin_info');
               Session::delete('db_config');
            return $this->fetch('');
        } else {
            $this->error("非法安装！","index/");
        }
    }
}