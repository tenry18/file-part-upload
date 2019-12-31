<?php
/**
 * 串行上传文件
 */

namespace App\Http\Controllers;



use App\Models\Upload;
use App\Models\UploadTemp;
use App\Tasks\UploadImageFile;
use App\Tasks\UploadM3u8File;
use App\Util\Upload\ConfigMapper;
use App\Util\Upload\PartialResource;
use App\Util\ZipFolder;
use Hhxsv5\LaravelS\Swoole\Task\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;


class V3 extends BC
{

    /**预处理
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
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
                'chunk_size'=>config('site.upload.chunk_size',1024*1024*1),
                'source_path'=>'',
                'resource_size'=>$request->input('resource_size'),
                'chunk_total'=>ceil($request->input('resource_size')/config('site.upload.chunk_size')),
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

    /**合并
     * @param UploadTemp $uploadTemp
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveChunk(UploadTemp $uploadTemp,Request $request)
    {
        try{
            $this->validate(request(), [
//                'chunk_total'            => 'required',
                'chunk_index'            => 'required',
                'resource_chunk'         => 'required',
                'group'                  =>'required',
            ]);
//            $chunkTotal=$request->input('chunk_total');//资源总数
            $chunkIndex=$request->input('chunk_index');//资源索引
            $resourceChunk=$request->file('resource_chunk');//资源块
            $group=$request->input('group');//分组
            ConfigMapper::getInstance()->applyGroupConfig($group);

            $partialResource = new PartialResource($uploadTemp['resource_uuid']);
            if ( $partialResource->exists($uploadTemp['source_path']) === false ) {throw new \Exception(PartialResource::$msg['invalid_operation']);}
            if ( $resourceChunk->getError() > 0 ) {throw new \Exception(PartialResource::$msg['upload_error']);}
            if ( $resourceChunk->isValid() === false ) {throw new \Exception(PartialResource::$msg['http_post_only']);}

            //判断顺序
            if ( (int)($uploadTemp['chunk_index']) !== (int)$chunkIndex - 1 ) {throw new \Exception(PartialResource::$msg['sequence_error']);}
            $partialResource->append($uploadTemp['source_path'],$resourceChunk);


            $uploadTemp['chunk_index'] = $chunkIndex;

            if ( $chunkIndex == $uploadTemp['chunk_total'] ) {
                $partialResource->checkSize($uploadTemp['source_path']);
                $ext=$partialResource->checkMimeType($uploadTemp['source_path']);
                //填充加密
                $partialResource->encrypt($uploadTemp['source_path']);
                $partialResource->rename($uploadTemp['source_path'],$uploadTemp['source_path'].'.'.$ext);
                $uploadTemp['source_path']=$uploadTemp['source_path'].'.'.$ext;
                $uploadTemp->save();
                //移动数据
                if (($upload=Upload::create(['resources_uuid'=>$uploadTemp['resource_uuid'],'source_path'=>$uploadTemp['source_path'],'type'=>$group]))===false) {throw new \Exception('数据异常');}
                $uploadTemp->delete();
                //调用处理逻辑
                if(!method_exists($this,$group)){throw new \Exception('没有找到对应方法');}
                $this->{$group}($upload);

                return $this->success([
                    'resource_uuid'=>$uploadTemp['resource_uuid']
                ]);
            }
            $uploadTemp->save();
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

//    public function retry(Request $request)
//    {
//        $uuids=$request->input('uuid');
//        if(empty($uuids)){throw new \Exception('uuid key is empty');}
//        $uuids=is_string($uuids)?[$uuids]:$uuids;
//        $rows=Upload::whereIn('resources_uuid',$uuids)->get()->toArray();
//        foreach ($rows as $row){
//            //重新加入上传队列
//            $upload['status']=UPLOAD_FILE_QUEUE;
//            $upload->save();
////            Redis::rpush('zip_to_m3u8',$upload['resources_uuid']);
//        }
//    }

    /**
     * @param Upload $upload
     */
    private function image(Upload &$upload)
    {
        $upload['status']=UPLOAD_FILE_QUEUE;
        $upload->save();
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
        $upload['status']=UPLOAD_FILE_QUEUE;
        $upload->save();
        //加入缓存
        Redis::rpush('video_to_m3u8',$upload['resources_uuid']);
        //加入队列转码
//        Task::deliver(new VideoToM3u8($upload['resources_uuid']));
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
        $outPath=storage_path('app'.dirname($upload['source_path']).'/'.$outDirName.'/source/');

        if(!ZipFolder::unzip_file(storage_path('app'.$upload['source_path']),$outPath)){
            //删除源文件
            $localDisk->delete($upload['source_path']);
            throw new \Exception('解析失败');
        }
        $m3u8=glob($outPath.'*.m3u8');
        $ts=glob($outPath.'*.ts');
        $coverJpg=glob($outPath.'cover.jpg');
        $key=glob($outPath.'*.key');
        //删除源zip文件
        $localDisk->delete($upload['source_path']);
        if(empty($m3u8)||empty($ts)||empty($coverJpg)){
            //删除文件夹
            $localDisk->deleteDirectory(dirname($upload['source_path']).'/'.$outDirName.'/');
            //删除数据
            $upload->delete();
            throw new \Exception('必须包含 .m3u8 .ts cover.jpg');
        }
        //更新路径
        $upload['source_path']='/'.ltrim($m3u8[0],storage_path('app'));
        $upload['status']=UPLOAD_FILE_QUEUE;
        // 如果无加密 加入队列转码,有加密 直接使用
        if(empty($key)){
            $upload->save();
            //加入缓存
            Redis::rpush('zip_to_m3u8',$upload['resources_uuid']);
//             Task::deliver(new ZipToM3u8($upload['resources_uuid']));
        }else{
            $upload['cover_path']=dirname($upload['source_path']).'/cover.jpg';
            $upload['result_path']=$upload['source_path'];
            $upload->save();
            Task::deliver(new UploadM3u8File($upload['resources_uuid']));
        }
    }



    public function options()
    {
        return response();
    }
}
