<?php

use think\Db;

/**
 * 获取分类所有子分类
 * @param int $cid 分类ID
 * @return array|bool
 */
function get_category_children($cid)
{
    if (empty($cid)) {
        return false;
    }

    $children = Db::name('category')->where(['path' => ['like', "%,{$cid},%"]])->select();

    return array2tree($children);
}

/**
 * 根据分类ID获取文章列表（包括子分类）
 * @param int   $cid   分类ID
 * @param int   $limit 显示条数
 * @param array $where 查询条件
 * @param array $order 排序
 * @param array $filed 查询字段
 * @return bool|false|PDOStatement|string|\think\Collection
 */
function get_articles_by_cid($cid, $limit = 10, $where = [], $order = [], $filed = [])
{
    if (empty($cid)) {
        return false;
    }

    $ids = Db::name('category')->where(['path' => ['like', "%,{$cid},%"]])->column('id');
    $ids = (!empty($ids) && is_array($ids)) ? implode(',', $ids) . ',' . $cid : $cid;

    $fileds = array_merge(['id', 'cid', 'title', 'introduction', 'thumb', 'reading', 'publish_time'], (array)$filed);
    $map    = array_merge(['cid' => ['IN', $ids], 'status' => 1, 'publish_time' => ['<= time', date('Y-m-d H:i:s')]], (array)$where);
    $sort   = array_merge(['is_top' => 'DESC', 'sort' => 'DESC', 'publish_time' => 'DESC'], (array)$order);

    $article_list = Db::name('article')->where($map)->field($fileds)->order($sort)->limit($limit)->select();

    return $article_list;
}

/**
 * 根据分类ID获取文章列表，带分页（包括子分类）
 * @param int   $cid       分类ID
 * @param int   $page_size 每页显示条数
 * @param array $where     查询条件
 * @param array $order     排序
 * @param array $filed     查询字段
 * @return bool|\think\paginator\Collection
 */
function get_articles_by_cid_paged($cid, $page_size = 15, $where = [], $order = [], $filed = [])
{
    if (empty($cid)) {
        return false;
    }

    $ids = Db::name('category')->where(['path' => ['like', "%,{$cid},%"]])->column('id');
    $ids = (!empty($ids) && is_array($ids)) ? implode(',', $ids) . ',' . $cid : $cid;

    $fileds = array_merge(['id', 'cid', 'title', 'introduction', 'thumb', 'reading', 'publish_time'], (array)$filed);
    $map    = array_merge(['cid' => ['IN', $ids], 'status' => 1, 'publish_time' => ['<= time', date('Y-m-d H:i:s')]], (array)$where);
    $sort   = array_merge(['is_top' => 'DESC', 'sort' => 'DESC', 'publish_time' => 'DESC'], (array)$order);

    $article_list = Db::name('article')->where($map)->field($fileds)->order($sort)->paginate($page_size);

    return $article_list;
}

/**
 * 数组层级缩进转换
 * @param array $array 源数组
 * @param int   $pid
 * @param int   $level
 * @return array
 */
function array2level($array, $pid = 0, $level = 1)
{
    static $list = [];
    foreach ($array as $v) {
        if ($v['pid'] == $pid) {
            $v['level'] = $level;
            $list[]     = $v;
            array2level($array, $v['id'], $level + 1);
        }
    }

    return $list;
}

/**
 * 构建层级（树状）数组
 * @param array  $array          要进行处理的一维数组，经过该函数处理后，该数组自动转为树状数组
 * @param string $pid_name       父级ID的字段名
 * @param string $child_key_name 子元素键名
 * @return array|bool
 */
function array2tree(&$array, $pid_name = 'pid', $child_key_name = 'children')
{
    $counter = array_children_count($array, $pid_name);
    if (!isset($counter[0]) || $counter[0] == 0) {
        return $array;
    }
    $tree = [];
    while (isset($counter[0]) && $counter[0] > 0) {
        $temp = array_shift($array);
        if (isset($counter[$temp['id']]) && $counter[$temp['id']] > 0) {
            array_push($array, $temp);
        } else {
            if ($temp[$pid_name] == 0) {
                $tree[] = $temp;
            } else {
                $array = array_child_append($array, $temp[$pid_name], $temp, $child_key_name);
            }
        }
        $counter = array_children_count($array, $pid_name);
    }

    return $tree;
}

/**
 * 子元素计数器
 * @param array $array
 * @param int   $pid
 * @return array
 */
function array_children_count($array, $pid)
{
    $counter = [];
    foreach ($array as $item) {
        $count = isset($counter[$item[$pid]]) ? $counter[$item[$pid]] : 0;
        $count++;
        $counter[$item[$pid]] = $count;
    }

    return $counter;
}

/**
 * 把元素插入到对应的父元素$child_key_name字段
 * @param        $parent
 * @param        $pid
 * @param        $child
 * @param string $child_key_name 子元素键名
 * @return mixed
 */
function array_child_append($parent, $pid, $child, $child_key_name)
{
    foreach ($parent as &$item) {
        if ($item['id'] == $pid) {
            if (!isset($item[$child_key_name]))
                $item[$child_key_name] = [];
            $item[$child_key_name][] = $child;
        }
    }

    return $parent;
}

/**
 * 循环删除目录和文件
 * @param string $dir_name
 * @return bool
 */
function delete_dir_file($dir_name)
{
    $result = false;
    if (is_dir($dir_name)) {
        if ($handle = opendir($dir_name)) {
            while (false !== ($item = readdir($handle))) {
                if ($item != '.' && $item != '..') {
                    if (is_dir($dir_name . DS . $item)) {
                        delete_dir_file($dir_name . DS . $item);
                    } else {
                        unlink($dir_name . DS . $item);
                    }
                }
            }
            closedir($handle);
            if (rmdir($dir_name)) {
                $result = true;
            }
        }
    }

    return $result;
}

/**
 * 判断是否为手机访问
 * @return  boolean
 */
function is_mobile()
{
    static $is_mobile;

    if (isset($is_mobile)) {
        return $is_mobile;
    }

    if (empty($_SERVER['HTTP_USER_AGENT'])) {
        $is_mobile = false;
    } elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'Mobile') !== false
        || strpos($_SERVER['HTTP_USER_AGENT'], 'Android') !== false
        || strpos($_SERVER['HTTP_USER_AGENT'], 'Silk/') !== false
        || strpos($_SERVER['HTTP_USER_AGENT'], 'Kindle') !== false
        || strpos($_SERVER['HTTP_USER_AGENT'], 'BlackBerry') !== false
        || strpos($_SERVER['HTTP_USER_AGENT'], 'Opera Mini') !== false
        || strpos($_SERVER['HTTP_USER_AGENT'], 'Opera Mobi') !== false
    ) {
        $is_mobile = true;
    } else {
        $is_mobile = false;
    }

    return $is_mobile;
}

/**
 * 手机号格式检查
 * @param string $mobile
 * @return bool
 */
function check_mobile_number($mobile)
{
    if (!is_numeric($mobile)) {
        return false;
    }
    $reg = '#^13[\d]{9}$|^14[5,7]{1}\d{8}$|^15[^4]{1}\d{8}$|^17[0,6,7,8]{1}\d{8}$|^18[\d]{9}$#';

    return preg_match($reg, $mobile) ? true : false;
}

function format_bytes($number)  
{  
    $len = strlen($number);  
    if($len < 4)  
    {  
        return sprintf("%d b", $number);  
    }  
    if($len >= 4 && $len <=6)  
    {  
        return sprintf("%0.2f Kb", $number/1024);  
    }  
    if($len >= 7 && $len <=9)  
    {  
        return sprintf("%0.2f Mb", $number/1024/1024);  
    }  
      
    return sprintf("%0.2f Gb", $number/1024/1024/1024);  
} 

/**
 * 导出数据为excal文件
 *
 * @param unknown $expTitle            
 * @param unknown $expCellName            
 * @param unknown $expTableData            
 */
function dataExcel($expTitle, $expCellName, $expTableData)
{  
    include 'extend/lib/PHPExcel/PHPExcel.php';   
    $fileName = $expTitle . date('_YmdHis'); // or $xlsTitle 文件名称可根据自己情况设定
    $cellNum = count($expCellName);
    $dataNum = count($expTableData);
    //var_dump($expCellName,$expTableData);exit;
    $objPHPExcel = new \PHPExcel();
    $cellName = array(
        'A',
        'B',
        'C',
        'D',
        'E',
        'F',
        'G',
        'H',
        'I',
        'J',
        'K',
        'L',
        'M',
        'N',
        'O',
        'P',
        'Q',
        'R',
        'S',
        'T',
        'U',
        'V',
        'W',
        'X',
        'Y',
        'Z',
        'AA',
        'AB',
        'AC',
        'AD',
        'AE',
        'AF',
        'AG',
        'AH',
        'AI',
        'AJ',
        'AK',
        'AL',
        'AM',
        'AN',
        'AO',
        'AP',
        'AQ',
        'AR',
        'AS',
        'AT',
        'AU',
        'AV',
        'AW',
        'AX',
        'AY',
        'AZ'
    );
   // var_dump($cellNum,$dataNum);exit;
    for ($i = 0; $i < $cellNum; $i ++) {
        //设置标题
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cellName[$i] . '1', $expCellName[$i][1]);
    }
    for ($i = 0; $i < $dataNum; $i ++) {
        for ($j = 0; $j < $cellNum; $j ++) {
            $objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j] . ($i + 2), $expTableData[$i][$expCellName[$j][0]]); 
        }
    }
    $objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(30); //设置宽度
   // $objPHPExcel->getActiveSheet()->getStyle('E4')->getAlignment()->setWrapText(true);  自动换行
    $objPHPExcel->setActiveSheetIndex(0);
  
    header('pragma:public'); header("Expires: 0"); 
    header('Content-type:application/vnd.ms-excel;charset=utf-8;name="' . $expTitle . '.xls"');   
    header("Content-Disposition:attachment;filename=$fileName.xls"); // attachment新窗口打印inline本窗口打印
    $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
    $objWriter->save('php://output');
}

/** 
*  数据导入 
* @param string $file excel文件 
* @param string $sheet 
 * @return string   返回解析数据 
 * @throws PHPExcel_Exception 
 * @throws PHPExcel_Reader_Exception 
*/  
function importExecl($file='', $sheet=0){  
    $file = iconv("utf-8", "gb2312", $file);   //转码  
    if(empty($file) OR !file_exists($file)) {  
        die('file not exists!');  
    } 
    
    include 'extend/lib/PHPExcel/PHPExcel.php';  //引入PHP EXCEL类  
    $objRead  = \PHPExcel_IOFactory::createReaderForFile($file);//方法1
//    方法2
//    $objRead = new PHPExcel_Reader_Excel2007();   //建立reader对象    
//    if(!$objRead->canRead($file)){  
//        $objRead = new PHPExcel_Reader_Excel5();  
//        if(!$objRead->canRead($file)){  
//            die('No Excel!');  
//        }  
//    }  
  
    $cellName = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AV', 'AW', 'AX', 'AY', 'AZ');  
  
    $obj = $objRead->load($file);  //建立excel对象  
    $currSheet = $obj->getSheet($sheet);   //获取指定的sheet表  
    $columnH = $currSheet->getHighestColumn();   //取得最大的列号  
    $columnCnt = array_search($columnH, $cellName);  
    $rowCnt = $currSheet->getHighestRow();   //获取总行数  
  
    $data = array();  
    for($_row=2; $_row<=$rowCnt; $_row++){  //读取内容  
        for($_column=0; $_column<=$columnCnt; $_column++){  
            $cellId = $cellName[$_column].$_row;  
            $cellValue = $currSheet->getCell($cellId)->getValue();  
             //$cellValue = $currSheet->getCell($cellId)->getCalculatedValue();  #获取公式计算的值  
            if($cellValue instanceof PHPExcel_RichText){   //富文本转换字符串  
                $cellValue = $cellValue->__toString();  
            }  
  
            $data[$_row][$cellName[$_column]] = $cellValue;  
        }  
    }  
  
    return $data;  
}  

// 获取用户真实 IP
function getIP() {
    
    static $realip = NULL;
    if ($realip !== NULL) {
        return $realip;
    }
    //判断服务器是否允许$_SERVER
    if (isset($_SERVER)) {
        if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            $realip = $_SERVER["HTTP_X_FORWARDED_FOR"]; //客户端跟服务器”握手”时候的IP
        } else if (isset($_SERVER["HTTP_CLIENT_IP"])) {
            $realip = $_SERVER["HTTP_CLIENT_IP"]; //IIS服务器,用的是CLIENT_IP代表客户的IP
        } else {
            $realip = $_SERVER["REMOTE_ADDR"]; //代理服务器的IP
        }
    } else {
        //不允许就使用getenv获取 
        if (getenv("HTTP_X_FORWARDED_FOR")) {
            $realip = getenv("HTTP_X_FORWARDED_FOR");
        } else if (getenv("HTTP_CLIENT_IP")) {
            $realip = getenv("HTTP_CLIENT_IP");
        } else {
            $realip = getenv("REMOTE_ADDR");
        }
    }
    return $realip;
        
}