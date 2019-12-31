<?php
/**
 * 并行上传文件 后续合并
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
use Ramsey\Uuid\Uuid;
use Swoole\Http\Server;
use Swoole\Process;

class VideoToM3u8 implements CustomProcessInterface
{

    /**音频码率因子
     * @var float
     */
    private static $audio_factor=0.6;
    /**视频码率因子
     * @var float
     */
    private static $video_factor=0.6;
    /**redis队列阻塞超时
     * @var float|int
     */
    private static $redis_timeout=30;


    public static function getName()
    {
        return 'video_to_m3u8';
    }

    /**
     * @param Server $swoole
     * @param Process $process
     */
    public static function callback(Server $swoole, Process $process)
    {
        while (true) {
            $resources_uuid=Redis::blPop('video_to_m3u8',self::$redis_timeout);
            if(empty($resources_uuid)||count($resources_uuid)!=2){continue;}
            $resources_uuid=$resources_uuid[1];
            $row=Upload::find($resources_uuid);
            if(empty($row)){Log::channel('transcoding')->error("视频转码==找不到数据==:".$row['resources_uuid']);continue;}
            self::transcoding($row);
        }
    }

    /**转码
     * @param Upload $row
     * @return bool
     */
    private static function transcoding(Upload &$row)
    {
        Log::channel('transcoding')->info("视频转码==开始==:".$row['resources_uuid']);
        try{
            $uuid=Uuid::uuid1()->toString();
            $ffmpeg=FF::getInstance()->getFFmpeg();
            $video=$ffmpeg->open(storage_path('app'.$row['source_path']));
            $video_format=$video->getFormat()->all();
            $video_info=$video->getStreams()->videos()->first()->all();
            $audio_info=$video->getStreams()->audios()->first()->all();
            $format=new X264('aac','libx264');

            $keyinfo_path=FF::keyInfo(dirname($row['source_path']));

            $video_bit_rate=$video_info['bit_rate']*self::$video_factor<1500*1024?$video_info['bit_rate']*self::$video_factor:1500*1024;

            $audio_bit_rate=$audio_info['bit_rate']*self::$audio_factor<64*1024?$audio_info['bit_rate']*self::$audio_factor:64*1024;//音频比特率
//dump($video_info['bit_rate'],$audio_info['bit_rate'],$audio_bit_rate,$video_bit_rate);return;
            $format
                ->setKiloBitrate($video_bit_rate/1024)  //码率 比特率
                ->setAudioChannels($audio_info['channels'])   // 声道设置，1单声道，2双声道，3立体声
                ->setAudioKiloBitrate($audio_bit_rate)//音频比特率
                ->setAdditionalParameters(['-vf','scale=-2:480','-f','hls','-hls_time', '10' , '-hls_list_size', '0','-hls_key_info_file',$keyinfo_path,'-hls_playlist_type','vod','-hls_allow_cache','1','-hls_segment_filename',storage_path('app'.dirname($row['source_path'])."/{$uuid}-%05d.ts")]);//附加参数生成hls

            $format->on('progress', function ($video, X264 $format, $percentage)use ($video_format,&$row) {
                if($row->status!=UPLOAD_FILE_HANDLE){
                    $row->status=UPLOAD_FILE_HANDLE;
                    $row->save();
                }
            });

            $m3u8Path=dirname($row['source_path'])."/{$uuid}.m3u8";
            $video->save($format,storage_path('app'.$m3u8Path));

            $row->cover_path=FF::getInstance()->jpg(storage_path('app'.$row['source_path']));
            $row->gif_path=FF::getInstance()->gif(storage_path('app'.$row['source_path']));
            $row->video_duration=FF::getInstance()->time(storage_path('app'.$row['source_path']));
            $row->status=UPLOAD_FILE_HANDLE_SUCCESS;
            $row->result_path=$m3u8Path;
            $row->save();
            Log::channel('transcoding')->info("视频转码==成功==:".$row['resources_uuid']);
            //投递上传任务
            Task::deliver(new UploadM3u8File($row['resources_uuid']));
            return true;
        }catch (\Exception $exception){
            $msg="视频转码==失败==:".$exception->getMessage().' line:'.$exception->getLine();
            $row->status=UPLOAD_FILE_ERROR;
            $row->error_log=$msg;
            $row->save();
            Log::channel('transcoding')->error($msg);
            return false;
        }
    }

    public static function onReload(Server $swoole, Process $process)
    {
        // TODO: Implement onReload() method.
        $process->exit(0);
    }

}
