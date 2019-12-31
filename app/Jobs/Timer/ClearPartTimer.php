<?php


namespace App\Jobs\Timer;


use App\Models\UploadTemp;
use App\Util\Upload\PartialResource;
use Hhxsv5\LaravelS\Swoole\Timer\CronJob;
use Illuminate\Support\Facades\Storage;

class ClearPartTimer extends CronJob
{
    public function run()
    {
        $localDisk=Storage::disk('local');
        // TODO: Implement run() method.
        //-2 minute
        $rows=UploadTemp::select('source_path','resource_uuid')->where('created_at','<',date('Y-m-d H:i:s',strtotime("-6 hour")))->get();
        foreach ($rows as &$row){
            //删除文件
            $localDisk->delete($row['source_path'].PartialResource::$part);
             //删除数据
            $row->delete();
        }
    }
}
