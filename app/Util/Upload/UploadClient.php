<?php
/**
 * 分段上传客户端
 */

namespace App\Util\Upload;


use GuzzleHttp\Client;

class UploadClient
{

    /**文件绝对路径
     * @var string
     */
    public $filePath;
    /**预处理地址
     * @var string
     */
    public $preprocessUrl;
    /**上传地址
     * @var string
     */
    public $uploadUrl;

    /**访问授权
     * @var
     */
    public $accessToken;

    /**
     * @var Client Client
     */
    public $client;

    /**块重试次数
     * @var int
     */
    private $chunkRetryNum=3;

    /**超时
     * @var int
     */
    private $timeout=10;

    const PREPROCESS_ERROR=0;//预处理失败
    const PREPROCESS_SUCCESS=1;//预处理成功
    const UPLOAD_ERROR=2;//上传失败
    const UPLOAD_PROCESS=3;//上传中
    const UPLOAD_SUCCESS=4;//上传成功



    /**
     * UploadClient constructor.
     * @param string $accessToken
     * @param string $preprocessUrl
     * @param string $uploadUrl
     */
    public function __construct(string $accessToken,string $preprocessUrl,string $uploadUrl)
    {
        $this->preprocessUrl=$preprocessUrl;
        $this->uploadUrl=$uploadUrl;
        $this->accessToken=$accessToken;
        $this->client = new Client();
    }

    /**
     * @param string $filePath
     * @param string $group
     * @param null $callback
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function send(string $filePath,string $group,$callback)
    {
        $this->filePath=$filePath;
        $this->preprocess($group,$callback);
    }


    /**预处理
     * @param string $group
     * @param $callback
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function preprocess(string $group,$callback)
    {
        if (!is_file($this->filePath)) {throw new \Exception('文件不存在');}

        $res = $this->client->request('POST', $this->preprocessUrl, [
            'headers' => [
                'Access-Token' => $this->accessToken,
            ],
            'timeout'=>$this->timeout,
            'form_params' => [
                'resource_size'=>$this->fileSize(),
                'resource_mime_type'=>$this->fileMimeType(),
                'group'=>$group
            ]
        ]);
        $contents=$res->getBody()->getContents();
        $res=\GuzzleHttp\json_decode($contents,true);
        //挂载勾子
        call_user_func($callback,['status'=>$res['code']==1?self::PREPROCESS_SUCCESS:self::PREPROCESS_ERROR,'resource_uuid'=>$res['data']['resource_uuid']]);
        if(empty($res)||$res['code']==0){return;}
        $this->upload($group,$res['data']['resource_uuid'],$res['data']['chunk_size'],$res['data']['chunk_total'],$callback);
    }


    /**上传文件
     * @param $group
     * @param $resourceUuid
     * @param $chunkSize
     * @param $chunkTotal
     * @param null $callback
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function upload($group,$resourceUuid,$chunkSize,$chunkTotal,$callback){
//        $fileSize=$this->fileSize();
        $handle  = fopen($this->filePath,"rb");//要分割的文件
        for ($chunkIndex=0;$chunkIndex<$chunkTotal;$chunkIndex++){
            fseek ($handle, ($chunkIndex*$chunkSize));//移动分割文件指针
            $resourceChunk = fread ($handle,$chunkSize);//被分割的资源块内容

            $resourceChunkHandle = fopen($this->filePath.'_tmp',"wb+");
            fwrite($resourceChunkHandle,$resourceChunk);
            //挂载钩子
            call_user_func($callback,['status'=>self::UPLOAD_PROCESS,'chunk_index'=>$chunkIndex,'chunk_total'=>$chunkTotal,'resource_uuid'=>$resourceUuid]);
            if ($this->uploadChunk($this->uploadUrl.'/'.$resourceUuid,[
                'headers' => [
                    'Access-Token' => $this->accessToken,
                ],
                'timeout'=>$this->timeout,
                'multipart' => [
                    [
                        'name'     => 'chunk_index',
                        'contents' => $chunkIndex+1
                    ],
                    [
                        'name'     => 'group',
                        'contents' => $group
                    ],
                    [
                        'name'     => 'resource_chunk',
                        'contents' => $resourceChunkHandle,
                    ]
                ]
            ],$callback)===false) {
                call_user_func($callback,['status'=>self::UPLOAD_ERROR,'chunk_index'=>$chunkIndex,'chunk_total'=>$chunkTotal,'resource_uuid'=>$resourceUuid]);
                return;
            }
//            fclose($resourceChunkHandle);//关闭临时文件指针 上面发送会自动关闭指针
            unlink($this->filePath.'_tmp');//释放临时文件
        }
        fclose ($handle);
    }


    /**上传资源块
     * @param string $url
     * @param array $data
     * @param $callback
     * @param int $currRetryNum
     * @return bool
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function uploadChunk(string $url,array $data,$callback,int $currRetryNum=0)
    {
        try{
            $res=$this->client->request('POST', $url, $data);
            $contents=$res->getBody()->getContents();
            $res=\GuzzleHttp\json_decode($contents,true);
            if(empty($res)||$res['code']==0){return  false;}
            if(isset($res['data']['resource_uuid'])){call_user_func($callback,['status'=>self::UPLOAD_SUCCESS,'resource_uuid'=>$res['data']['resource_uuid']]);}
            return true;
        }catch (\Exception $exception){
            if($currRetryNum<$this->chunkRetryNum){
                $this->uploadChunk($url,$data,$callback,++$currRetryNum);
            }else {
                return false;
            }
        }
    }

    /**
     * @return false|int
     */
    protected function fileSize()
    {
        return filesize($this->filePath);
    }

    /**
     * @return string
     */
    protected function fileMimeType()
    {
        return mime_content_type($this->filePath);
    }
}
