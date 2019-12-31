<?php
/**
 * 上传文件验证token
 */
namespace App\Http\Middleware;

use Closure;

class UploadToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
//        dump($request->all());
        try{
            $access_token=$request->header('Access-Token');
            if(empty($access_token)||is_array($access_token)){throw new \Exception();}
            if (in_array($access_token,config('site.upload.AccessToken'))) {
                return $next($request);
            }
            throw new \Exception();
        }catch (\Exception $exception){
            return response(null,404);
        }
    }
}
