<?php
/**
 * 压缩包处理
 */

namespace App\Processes;


use App\Models\Upload;
use App\Tasks\UploadM3u8File;
use App\Util\FF;
use FFMpeg\Format\Video\X264;
use Hhxsv5\LaravelS\Swoole\Process\CustomProcessInterface;
use Hhxsv5\LaravelS\Swoole\Task\Task;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Swoole\Http\Server;
use Swoole\Process;

class ZipToM3u8 implements CustomProcessInterface
{
    /**redis队列阻塞超时
     * @var float|int
     */
    private static $redis_timeout=30;


    public static function getName()
    {
        return 'zip_to_m3u8';
    }

    public static function callback(Server $swoole, Process $process)
    {
        while (true) {
            $resources_uuid=Redis::blPop('zip_to_m3u8',self::$redis_timeout);
            if(empty($resources_uuid)||count($resources_uuid)!=2){continue;}
            $resources_uuid=$resources_uuid[1];
            $row=Upload::find($resources_uuid);
            if(empty($row)){Log::channel('transcoding')->error("压缩包转码==找不到数据==:".$row['resources_uuid']);continue;}
            self::transcoding($row);
        }
    }

    private static function transcoding(Upload &$row)
    {
        try{
            $localDisk=Storage::disk('local');
            $localDisk->copy(dirname($row['source_path']).'/cover.jpg',dirname(dirname($row['source_path'])).'/cover.jpg');
            $ffmpeg=FF::getInstance()->getFFmpeg();

            $video=$ffmpeg->open(storage_path('app'.$row['source_path']));

            $video_format=$video->getFormat()->all();
            $video_info=$video->getStreams()->videos()->first()->all();
            $audio_info=$video->getStreams()->audios()->first()->all();
            $format=new X264('aac','libx264');

            $keyinfo_path=FF::keyInfo(dirname(dirname($row['source_path'])));

            $parameter=['-f','hls','-hls_time', '10' , '-hls_list_size', '0','-hls_key_info_file',$keyinfo_path,'-hls_playlist_type','vod','-hls_allow_cache','1',];

            //读取水印描述文件
            $waterFileDiskPath=dirname(storage_path('app'.$row['source_path'])).'/water.json';
            if (is_file($waterFileDiskPath)) {
                $water=json_decode(file_get_contents($waterFileDiskPath),true);
                if(!empty($water)&&isset($water['x'])&&isset($water['y'])&&isset($water['w'])&&isset($water['h'])){
                    array_push($parameter,'-vf');
                    array_push($parameter,"delogo=x={$water['x']}:y={$water['y']}:w={$water['w']}:h={$water['h']}");
                }
            }

            $format
//            ->setKiloBitrate($video_info['bit_rate']/1000*0.8)  //码率 比特率
                ->setAudioChannels($audio_info['channels'])   // 声道设置，1单声道，2双声道，3立体声
//            ->setAudioKiloBitrate($audio_info['bit_rate']/1000)//音频比特率
                ->setAdditionalParameters($parameter);

            $format->on('progress', function ($video, X264 $format, $percentage)use ($video_format,&$data) {
                if($data->status!=UPLOAD_FILE_HANDLE){
                    $data->status=UPLOAD_FILE_HANDLE;
                    $data->save();
                }
            });
            $m3u8Path=dirname(dirname($data['source_path'])).'/'.$row['resources_uuid'].'.m3u8';
            $video->save($format,storage_path('app'.$m3u8Path));
            $row->cover_path=dirname(dirname($data['source_path'])).'/cover.jpg';
            $row->video_duration=FF::getInstance()->time(storage_path('app'.$data['source_path']));
            $row->status=UPLOAD_FILE_HANDLE_SUCCESS;
            $row->result_path=$m3u8Path;
            $data->save();
            Task::deliver(new UploadM3u8File($row['resources_uuid']));
            Log::channel('transcoding')->info("压缩包转码==成功==:".$row['resources_uuid']);
        }catch (\Exception $exception){
            $msg="压缩包转码==失败==:".$exception->getMessage().' line:'.$exception->getLine();
            $row->error_log=$msg;
            $row->status=UPLOAD_FILE_ERROR;
            $row->save();
            Log::channel('transcoding')->error($msg);
        }
    }


    public static function onReload(Server $swoole, Process $process)
    {
        $process->exit(0);
    }

}
