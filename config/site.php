<?php
defined('UPLOAD_FILE_UNTREATED')        or define('UPLOAD_FILE_UNTREATED',0);//未处理
defined('UPLOAD_FILE_QUEUE')            or define('UPLOAD_FILE_QUEUE',1);//队列中
defined('UPLOAD_FILE_HANDLE')           or define('UPLOAD_FILE_HANDLE',2);//处理中
defined('UPLOAD_FILE_HANDLE_SUCCESS')   or define('UPLOAD_FILE_HANDLE_SUCCESS',3);//处理完成
defined('UPLOAD_FILE_SEND')             or define('UPLOAD_FILE_SEND',4);//上传中
defined('UPLOAD_FILE_SEND_SUCCESS')     or define('UPLOAD_FILE_SEND_SUCCESS',5);//上传完成
defined('UPLOAD_FILE_ERROR')            or define('UPLOAD_FILE_ERROR',6);//文件错误

defined('NO_DATA_FOUND')                or define('NO_DATA_FOUND','no data found');

return[
    'ffmpeg'=>  '/www/wwwroot/util/ffmpeg/_release/bin/ffmpeg',
    'ffprobe'=>  '/www/wwwroot/util/ffmpeg/_release/bin/ffprobe',
    'qt_faststart'=>'/www/wwwroot/util/ffmpeg/_release/bin/qt-faststart',
    'timeout'=>60*60*12,

    'upload'=>[
        /*v2接口
        |--------------------------------------------------------------------------
        | 上传分块大小（B）   1024B*1024B=1M
        |--------------------------------------------------------------------------
        |
        | 【一般设置】建议1MB～4MB之间，较小值占用内存少、效率低，较大值占用内存多、效率高，需要小于web服务器和php.ini中的上传限值。
        |
        */
        'chunk_size' => 1024*1024*2,


        /*
        |--------------------------------------------------------------------------
        | 资源分组
        |--------------------------------------------------------------------------
        |
        */
        'groups' => [
            'image' => [ # 分组名
                'storage_dir'                    => '/image', # 分组目录名
                'resource_max_size'             => 0, # 被允许的资源文件最大值(B)，0为不限制，32位系统最大值为2147483647
                # 被允许的资源文件头(白名单)，空为不限制
                'resource_mime_type'          => [
                    'image/gif','image/jpeg','image/png'
                ],
            ],
            'video' => [ # 分组名
                'storage_dir'                    => '/video', # 分组目录名
                'resource_max_size'             => 0, # 被允许的资源文件最大值(B)，0为不限制，32位系统最大值为2147483647
                # 被允许的资源文件头(白名单)，空为不限制
                'resource_mime_type'          => [
                    'video/quicktime','video/mp4'
                ],
            ],
            'zip' => [ # 分组名
                'storage_dir'                    => '/zip', # 分组目录名
                'resource_max_size'             => 0, # 被允许的资源文件最大值(B)，0为不限制，32位系统最大值为2147483647
                # 被允许的资源文件头(白名单)，空为不限制
                'resource_mime_type'          => [
                    'application/zip','application/x-gzip','x-zip-compressed'
                ],
            ],
        ],



        //上传接口 允许访问token
        'AccessToken'=>[
            'mitao'=>'a67af1d60f815a88d2e87665274c93b1',
        ],
    ],

    //m3u8文件生成keyinfo文件内url
//    'keyinfo'=>[
//        'url'=>'http://192.168.1.200/v1/key'
//    ],




];
