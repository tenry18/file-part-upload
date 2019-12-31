<?php


namespace App\Util;


use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use Illuminate\Support\Facades\Storage;

class FF
{
    use Singleton;
    private $ffmpeg;

    private function __construct()
    {
        $this->ffmpeg=FFMpeg::create([
            'ffmpeg.binaries'=>  config('site.ffmpeg'),
            'ffprobe.binaries'=> config('site.ffprobe') ,
            'timeout'=>config('site.timeout'),
            'ffmpeg.threads'   => swoole_cpu_num(),   //FFMpeg应该使用的线程数
        ]);
    }

    /**
     * @return FFMpeg
     */
    public function getFFmpeg()
    {
        return $this->ffmpeg;
    }

    /**生成图片
     * @param string $realDiskPath
     * @param $outDiskPath
     * @return string
     */
    public function jpg(string $realDiskPath,string $outDiskPath=null)
    {
        if (!is_file($realDiskPath)) {return false;}
        $video=$this->ffmpeg->open($realDiskPath);
        $outDiskPath=$outDiskPath?$outDiskPath.'/cover.jpg':dirname($realDiskPath).'/cover.jpg';
        $frame = $video->frame(TimeCode::fromSeconds(1));//提取第几秒的图像
        $frame->save($outDiskPath);
        return str_replace(storage_path('app'),'',$outDiskPath);
    }


    /**生成动图
     * @param string $realDiskPath
     * @param string|null $outDiskPath
     * @param int $duration 时长
     * @return bool|mixed
     */
    public function gif(string $realDiskPath,string $outDiskPath=null,$duration=2)
    {
        if (!is_file($realDiskPath)) {return false;}
        $duration=$duration<1?1:$duration;
        $video=$this->ffmpeg->open($realDiskPath);
        $outDiskPath=$outDiskPath?$outDiskPath.'/cover.gif':dirname($realDiskPath).'/cover.gif';
        $video
            ->gif(TimeCode::fromSeconds(10), $video->getStreams()->videos()->first()->getDimensions(), $duration)
            ->save($outDiskPath);
        return str_replace(storage_path('app'),'',$outDiskPath);
    }


    /**修改文件头
     * @param string $realDiskPath
     * @param string|null $outDiskPath
     * @return bool
     */
    public static function mp4Moov(string $realDiskPath,string $outDiskPath=null)
    {
        if(!is_file($realDiskPath)){return false;}
        $cmd=config('site.qt_faststart')." '{$realDiskPath}' '{$realDiskPath}_t'";
//        $cmd="{$this->ffmpeg} -i '{$old_path}' -vcodec copy -acodec copy  -movflags +faststart '{$path['disk_path']}'";
        exec($cmd,$out,$status);
        if(!isset($out[8])||$status!=0){return false;}
        //删除源(临时)文件
        if (unlink($realDiskPath)==false) {return false;}
        //重置文件名称
        return rename("{$realDiskPath}_t",$realDiskPath);
    }

    /**获取时长
     * @param string $realDiskPath
     * @return bool|int|mixed|string
     */
    public  function time(string $realDiskPath)
    {
        if(!is_file($realDiskPath)){return false;}
        if(strstr($realDiskPath,'m3u8')){
            preg_match_all('/#EXTINF:(\d+\.\d*)/', file_get_contents($realDiskPath), $duration);
            if (isset($duration[1])) {
                return (string)array_sum($duration[1]);
            }
            return 0;
        }else{
            $video=$this->ffmpeg->open($realDiskPath);
            return $video->getStreams()->videos()->first()->get('duration');
        }
    }


    /**生成keyinfo
     * @param $diskDirPath
     * @return bool|string
     */
    public static function keyInfo($diskDirPath)
    {
        try{
            $key=openssl_random_pseudo_bytes(16);
            /**生成key文件**/
            $keyFileName=uniqid().'.key';
            $keyDiskPath=$diskDirPath.'/'.$keyFileName;
            Storage::disk('local')->put($keyDiskPath,$key);

            $url=config('site.keyinfo.url');
            $url=$keyFileName;

            /**生成keyinfo文件**/
            $keyInfoDiskPath=$diskDirPath.'/'.uniqid().'.info';
            Storage::disk('local')->put($keyInfoDiskPath,$url."\n".storage_path('app'.$keyDiskPath)."\n");
            return storage_path('app'.$keyInfoDiskPath);
        }catch (\Exception $exception){
            return false;
        }

    }
}
