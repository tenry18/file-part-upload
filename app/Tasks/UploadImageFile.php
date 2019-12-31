<?php

namespace App\Tasks;

use Hhxsv5\LaravelS\Swoole\Task\Task;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UploadImageFile extends Task
{

    /**
     * @var string
     */
    private $uuid;

    /**
     * UploadImageFile constructor.
     * @param $uuid
     */
    public function __construct(string $uuid)
    {
        $this->uuid=$uuid;
    }


    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::channel('transcoding')->info("上传图片==开始==:".$this->uuid);
        $data=\App\Models\Upload::find($this->uuid);

        $data->status=UPLOAD_FILE_SEND_SUCCESS;
        $data->result_path=$data['source_path'];
        $data->save();
return;;

        if(empty($data)){Log::channel('transcoding')->error("上传图片==找不到数据==:".$this->uuid);return;}
        try{
            $this->upload($data['source_path']);
            $data->status=UPLOAD_FILE_SEND_SUCCESS;
            $data->result_path=$data['source_path'];
            $data->save();
            Log::channel('transcoding')->info("上传图片==成功==:".$this->uuid);
        }catch (\Exception $exception){
            $msg="上传图片==失败==:".$exception->getMessage().' line:'.$exception->getLine();
            $data->error_log=$msg;
            $data->status=UPLOAD_FILE_ERROR;
            $data->save();
            Log::channel('transcoding')->error($msg);
        }
    }


    /**
     * @param string $filePath
     * @param int $retry
     * @return mixed
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    private function upload(string $filePath,int $retry=0)
    {
        $localDisk=Storage::disk('local');
        $cloudDisk=Storage::disk(config('filesystems.cloud'));
        if($retry>=10){throw new \Exception("已达最大重试次数 {$retry} Path:".$filePath);}
        if(!$cloudDisk->exists($filePath)){
            $res=$cloudDisk->put($filePath,$localDisk->get($filePath));
            $retry++;
            return $res?:$this->upload($filePath,$retry);
        }
    }
}
