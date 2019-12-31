<?php


namespace App\Util\Upload;


use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class PartialResource
{
    public static $part='.part';

    private $localDisk;
    private $resourcesUuid;

    public static $msg=[
        'upload_error'            => '错误: 上传发生错误',
        'invalid_resource_size'   => '错误：不允许的文件大小',
        'invalid_resource_type'   => '错误：无效的文件类型',
        'create_dir_fail'         => '错误：创建文件夹失败',
        'create_resource_fail'    => '错误：创建文件失败',
        'write_resource_fail'     => '错误：写文件失败',
        'rename_resource_fail'    => '错误：重命名文件失败',
        'invalid_operation'       => '错误：非法操作',
        'http_post_only'          => '错误：文件必须通过HTTP POST上传',
        'sequence_error'          => '错误：请按照顺序依次上传',
    ];

    public function __construct(string $resourcesUuid)
    {
        $this->localDisk = Storage::disk('local');
        $this->resourcesUuid=$resourcesUuid;
    }

    /**
     * @param string $uuid
     * @return string
     * @throws \Exception
     */
    public function createFile(string $uuid)
    {
        $realPath=ConfigMapper::get('storage_dir');

        //分配文件夹
        if (Storage::disk('local')->makeDirectory($realPath) === false ) {
            throw new \Exception(self::$msg['create_dir_fail']);
        }
        //分配文件
        if (Storage::disk('local')->put($realPath.$uuid.self::$part, '') === false ) {
            throw new \Exception(self::$msg['create_resource_fail']);
        }
        return $realPath.$uuid;
    }


    /**验证文件
     * @param string $realPath
     */
    public static function exists(string $realPath)
    {
        Storage::disk('local')->exists($realPath.self::$part);
    }


    /**写入数据
     * @param string $realPath
     * @param UploadedFile $resourceChunk
     * @throws \Exception
     */
    public function append(string $realPath,UploadedFile $resourceChunk)
    {
        $handle = @fopen($resourceChunk->getRealPath(), 'rb');
        if ( File::append(storage_path('app'.$realPath.self::$part), $handle) === false ) {
            throw new \Exception(self::$msg['write_resource_fail']);
        }
        fclose($handle);
    }

    /**填充加密
     * @param string $realPath
     * @throws \Exception
     */
    public function encrypt(string $realPath)
    {
        return '';
        if (File::prepend(storage_path('app'.$realPath.self::$part),pack('H*',str_replace('-','',basename($realPath))))===false) {
            throw new \Exception(self::$msg['write_resource_fail']);
        }
    }

    /**并发数据块
     * @param string $realPath
     * @param UploadedFile $resourceChunk
     * @param $chunkIndex
     * @throws \Exception
     */
    public function append2(string $realPath,UploadedFile $resourceChunk,$chunkIndex)
    {
        $handle = @fopen($resourceChunk->getRealPath(), 'rb');
        if (File::append(storage_path('app'.$realPath.'_'.str_pad($chunkIndex,4,"0",STR_PAD_LEFT).self::$part), $handle) === false ) {
            throw new \Exception(self::$msg['write_resource_fail']);
        }
        fclose($handle);
    }

    /**合并块
     * @param string $realPath
     * @param $chunkTotal
     * @return bool
     * @throws \Exception
     */
    public function mergeChunk(string $realPath,$chunkTotal)
    {
        $files=glob(storage_path('app'.$realPath.'_*'.self::$part));
        if (count($files)==$chunkTotal) {
            sort($files);
            foreach ($files as $file){
                $handle = @fopen($file, 'rb');
                if (File::append(storage_path('app'.$realPath.self::$part), $handle) === false ) {
                    throw new \Exception(self::$msg['write_resource_fail']);
                }else{
                    //删除源文件
                    File::delete($file);
                }
            }
            return true;
        }
        return false;
    }


    /**
     * @param string $realPath
     * @return mixed
     * @throws \Exception
     */
    public function checkSize(string $realPath)
    {
        return $this->filterBySize(filesize(storage_path('app'.$realPath.self::$part)));
    }

    /**
     * @param string $realPath
     * @return string|null
     * @throws \Exception
     */
    public function checkMimeType(string $realPath)
    {
        $mimeType=mime_content_type(storage_path('app'.$realPath.self::$part));
        $this->filterByMimeType($mimeType);
        return MimeType::search($mimeType);
    }

    /**
     * @param $resourceSize
     * @return mixed
     * @throws \Exception
     */
    public function filterBySize($resourceSize)
    {
        $maxSize = (int)ConfigMapper::get('resource_max_size');
        if ( (int)$resourceSize === 0 || ((int)$resourceSize > $maxSize && $maxSize !== 0) ) {
            throw new \Exception(self::$msg['invalid_resource_size']);
        }
        return $resourceSize;
    }

    /**
     * @param $resourceMimeType
     * @return mixed
     * @throws \Exception
     */
    public function filterByMimeType($resourceMimeType)
    {
        $mimeType =ConfigMapper::get('resource_mime_type');
        if (!empty($mimeType)&&!in_array($resourceMimeType,$mimeType)) {
            throw new \Exception(self::$msg['invalid_resource_type']);
        }
        return $resourceMimeType;
    }


    /**
     * @param string $realPath
     * @param string $savePath
     * @throws \Exception
     */
    public function rename(string $realPath,string $savePath)
    {
        if ($this->localDisk->exists($realPath.self::$part) === false ) {
            throw new \Exception('文件不存在');
        }
        if ($this->localDisk->move($realPath.self::$part, $savePath) === false ) {
            throw new \Exception(self::$msg['rename_resource_fail']);
        }
    }
}
