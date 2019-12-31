<?php
namespace App\Util;

/**
 * Zip 文件包工具
 * @date 2019-07-07
 */
class ZipFolder
{

    public static function unzip_file(string $zipPath,string $outPath){
        //检测要解压压缩包是否存在
        if(!is_file($zipPath)){return false;}
        //检测目标路径是否存在
        if(!is_dir($outPath)){mkdir($outPath,0777,true);}
        $zip=new \ZipArchive();
        if($zip->open($zipPath)){
            $zip->extractTo($outPath);
            $zip->close();
            return true;
        }else{
            return false;
        }
    }
}
