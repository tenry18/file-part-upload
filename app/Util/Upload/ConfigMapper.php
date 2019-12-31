<?php

namespace App\Util\Upload;

use App\Util\Singleton;

class ConfigMapper
{
    use Singleton;

    private $chunk_size;
    private $resource_max_size;
    private $resource_mime_type;
    private $group;
    private $storage_dir;

    private function applyCommonConfig()
    {
        $this->chunk_size = config('site.upload.chunk_size');
        return $this;
    }

    public function applyGroupConfig($group)
    {
        if (!in_array($group, array_keys(config('site.upload.groups'))) ) {
            throw new \Exception('非法操作');
        }
        $this->group=$group;
        $this->storage_dir=config('site.upload.groups.' . $group . '.storage_dir').date('/Y/m/d/');
        $this->resource_max_size=config('site.upload.groups.' . $group . '.resource_max_size');
        $this->resource_mime_type=config('site.upload.groups.' . $group . '.resource_mime_type');
        return $this;
    }

    public static function get($property)
    {
        return self::getInstance()->{$property};
    }

    public static function set($property, $value)
    {
        self::getInstance()->{$property} = $value;
    }

}
