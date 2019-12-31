<?php


namespace App\Tasks;


use Hhxsv5\LaravelS\Swoole\Task\Task;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Finder\Finder;

class UploadM3u8File extends Task
{

    private $uuid;

    /**
     * UploadM3u8File constructor.
     * @param string $uuid
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
        Log::channel('transcoding')->info("上传M3U8==开始==:".$this->uuid);
        $data=\App\Models\Upload::find($this->uuid);

        $data->status=UPLOAD_FILE_SEND_SUCCESS;
        $data->save();
        return;

        if(empty($data)){Log::channel('transcoding')->error("上传M3U8==找不到数据==:".$this->uuid);return;}
        try{
            $cloudDisk=Storage::disk(config('filesystems.cloud'));
            foreach (Finder::create()->files()->name(['*.jpg','*.ts','*.m3u8','*.key','*.gif'])->in(storage_path('app'.dirname($data['result_path']).'/'))->depth('== 0') as $file){
                if ($data->status!=UPLOAD_FILE_SEND) {
                    $data->status=UPLOAD_FILE_SEND;
                    $data->save();
                }
                $this->upload($cloudDisk,dirname($data['result_path']),$file);
            }
            $data->status=UPLOAD_FILE_SEND_SUCCESS;
            $data->save();
            Log::channel('transcoding')->info("上传M3U8==成功==:".$this->uuid);
            dump("success=={$this->uuid}");
        }catch (\Exception $exception){
            $msg="上传M3U8==失败==: ".$exception->getMessage().' line:'.$exception->getLine();
            $data->error_log=$msg;
            $data->status=UPLOAD_FILE_ERROR;
            $data->save();
            dump("error=={$this->uuid} {$exception->getMessage()}");
            Log::channel('transcoding')->error($msg);
        }
    }

    /**
     * @param $cloudDisk
     * @param string $dirPath
     * @param $file
     * @param int $retry
     * @return mixed
     * @throws \Exception
     */
    private function upload(&$cloudDisk,string $dirPath,&$file,int $retry=0)
    {
        if($retry>=10){throw new \Exception("已达最大重试次数 {$retry}".$dirPath.'/'.$file->getFilename());}
        if(!$cloudDisk->exists($dirPath.'/'.$file->getFilename())){
            $res=$cloudDisk->put($dirPath.'/'.$file->getFilename(),$file->getContents());
            $retry++;
            return $res?:$this->upload($cloudDisk,$dirPath,$file,$retry);
        }
    }
}
