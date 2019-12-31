<?php
/**
 * 分段上传
 * 并发
 */


namespace App\Http\Controllers;

use App\Models\Upload;
use App\Models\UploadTemp;
use App\Tasks\UploadImageFile;
use App\Tasks\UploadM3u8File;
use App\Tasks\VideoToM3u8;
use App\Tasks\ZipToM3u8;
use App\Util\Upload\ConfigMapper;
use App\Util\Upload\PartialResource;
use App\Util\ZipFolder;
use Hhxsv5\LaravelS\Swoole\Task\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;

class V4 extends BC
{
    public function preprocess(Request $request)
    {
        try{
            $this->validate($request,[
                'resource_size'=>'required|integer|min:1',
                'resource_mime_type'=>'required',
                'group'=>'required',
            ]);
            $data=[
                'resource_uuid'=>Uuid::uuid1(1)->toString(),
                'chunk_size'=>config('site.upload.chunk_size'),
                'chunk_total'=>ceil($request->input('resource_size')/config('site.upload.chunk_size')),
                'source_path'=>'',
                'resource_size'=>$request->input('resource_size')
            ];

            ConfigMapper::getInstance()->applyGroupConfig($request->input('group'));

            $partialResource=new PartialResource($data['resource_uuid']);
            $partialResource->filterBySize($request->input('resource_size'));
            $partialResource->filterByMimeType($request->input('resource_mime_type'));

            $data['source_path']=$partialResource->createFile($data['resource_uuid']);
            if (!UploadTemp::create($data)) {
                throw new \Exception('创建数据失败');
            }
            return $this->success([
                'resource_uuid'=>$data['resource_uuid'],
                'chunk_size'=>$data['chunk_size'],
                'chunk_total'=>$data['chunk_total']
            ]);
        }catch (\Exception $exception){
            return $this->error([],$exception->getMessage());
        }
    }

    public function saveChunk(UploadTemp $uploadTemp,Request $request)
    {

        try{
            $this->validate(request(), [
                'chunk_total'            => 'required',
                'chunk_index'            => 'required',
                'resource_chunk'         => 'required',
                'group'                  =>'required',
            ]);
//            $chunkTotal=$request->input('chunk_total');//资源总数
            $chunkIndex=$request->input('chunk_index');//资源索引
            $resourceChunk=$request->file('resource_chunk');//资源块
            ConfigMapper::getInstance()->applyGroupConfig($request->input('group'));
            $partialResource = new PartialResource($uploadTemp['resource_uuid']);
            if ( $resourceChunk->getError() > 0 ) {throw new \Exception(PartialResource::$msg['upload_error']);}
            if ( $resourceChunk->isValid() === false ) {throw new \Exception(PartialResource::$msg['http_post_only']);}
            //生成块文件
            $partialResource->append2($uploadTemp['source_path'],$resourceChunk,(int)$chunkIndex);
            //判断并合并文件
            //bug 每个进程写入时长不一致 导致文件判断法错误,可考虑table判断
            if ($partialResource->mergeChunk($uploadTemp['source_path'],$uploadTemp['chunk_total'])) {

                $partialResource->checkSize($uploadTemp['source_path']);
                $ext=$partialResource->checkMimeType($uploadTemp['source_path']);
                $partialResource->rename($uploadTemp['source_path'],$uploadTemp['source_path'].'.'.$ext);
                $uploadTemp['source_path']=$uploadTemp['source_path'].'.'.$ext;
                $uploadTemp->save();
                //移动数据
                if (($upload=Upload::create(['resources_uuid'=>$uploadTemp['resource_uuid'], 'source_path'=>$uploadTemp['source_path'],]))===false) {throw new \Exception('数据异常');}
                //调用处理逻辑
                if(!method_exists($this,$request->input('group'))){throw new \Exception('没有找到对应方法');}

//                $this->{$request->input('group')}($upload);
                $uploadTemp->delete();
                return $this->success([
                    'resource_uuid'=>$uploadTemp['resource_uuid']
                ]);
            }
            return $this->success();
        }catch (\Exception $exception){
            return $this->error([],$exception->getMessage());
        }
    }

    /**查询数据
     * @return \Illuminate\Http\JsonResponse
     */
    public function find(Request $request)
    {
        try{
            $uuids=$request->input('uuid');
            if(empty($uuids)){throw new \Exception('uuid key is empty');}
            $uuids=is_string($uuids)?[$uuids]:$uuids;
            $updateFile=Upload::whereIn('resources_uuid',$uuids)
                ->select('resources_uuid','result_path','cover_path','video_duration','status')
                ->get()->toArray();
            if (empty($updateFile)) {throw new \Exception(NO_DATA_FOUND);}
            return $this->success($updateFile);
        }catch (\Exception $exception){
            return $this->error([],$exception->getMessage());
        }
    }

    /**
     * @param Upload $upload
     */
    private function image(Upload &$upload)
    {
        Task::deliver(new UploadImageFile($upload['resources_uuid']));
    }

    /**
     * @param Upload $upload
     * @throws \Exception
     */
    private function video(Upload &$upload)
    {
        //移动文件到二级目录
        $localDisk=Storage::disk('local');
        $savePath=dirname($upload['source_path']).'/'.uniqid().'/'.basename($upload['source_path']);
        if ($localDisk->move($upload['source_path'],$savePath)===false) {
            throw new \Exception('移动文件失败');
        }
        $upload['source_path']=$savePath;
        $upload->save();
        //加入队列转码
        Task::deliver(new VideoToM3u8($upload['resources_uuid']));
    }

    /**
     * @param Upload $upload
     * @throws \Exception
     */
    private function zip(Upload &$upload)
    {
        $localDisk=Storage::disk('local');
        $outDirName=uniqid();
        //解压到二级目录
        $outPath=storage_path('app'.dirname($upload['source_path']).'/'.$outDirName.'/');
        if(!ZipFolder::unzip_file(storage_path('app'.$upload['source_path']),$outPath)){throw new \Exception('解析失败');}

        $m3u8=glob($outPath.'/*.m3u8');
        $ts=glob($outPath.'/*.ts');
        $coverJpg=glob($outPath.'/cover.jpg');
        $key=glob($outPath.'/*.key');
        if(empty($m3u8)||empty($ts)||empty($coverJpg)){
            //删除源文件
            $localDisk->delete($upload['source_path']);
            //删除文件夹
            $localDisk->deleteDirectory(dirname($upload['source_path']).'/'.$outDirName.'/');
            //删除数据
            $upload->delete();
            throw new \Exception('必须包含 .m3u8 .ts cover.jpg');
        }
        // 如果无加密 加入队列转码,有加密 直接使用
        if(empty($key)){
            Task::deliver(new ZipToM3u8($upload['resources_uuid']));
        }else{
            $upload['result_path']=$outPath.basename($upload['source_path']);
            $upload->save();
            Task::deliver(new UploadM3u8File($upload['resources_uuid']));
        }
    }



    public function options()
    {
        return response();
    }
}
