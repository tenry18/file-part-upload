<?php


namespace App\Http\Controllers;


class BC extends Controller
{
    /**
     * @param array $data
     * @param string $msg
     * @return \Illuminate\Http\JsonResponse
     */
    public function success($data=[],$msg='成功')
    {
        return response()->json(['code'=>1,'data'=>$data,'msg'=>$msg]);
    }

    /**
     * @param array $data
     * @param string $msg
     * @return \Illuminate\Http\JsonResponse
     */
    public function error($data=[],$msg='失败')
    {
        return response()->json(['code'=>0,'data'=>$data,'msg'=>$msg]);
    }
}
