<?php

define('INSTALL_APP_PATH', realpath('') . '/');
return [
    'original_table_prefix' => 'think_', //默认表前缀

     'view_replace_str'=>[
        '__PUBLIC__'=>'/public/',
         '__ROOT__' => '/',
       ],

    'template' => [
        // 模板路径
        'view_path' => 'application\install\view' . DS,
    ],

    'systemname' => '唯美博客系统',
    'version' => 'V2.0',
    


   
];



