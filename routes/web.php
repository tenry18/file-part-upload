<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::group(['prefix' => 'v3','middleware'=>'access_token'],function (){
    Route::post('/find','V3@find');
    Route::post('/preprocess','V3@preprocess');
    Route::post('/uploading/{uploadTemp}', 'V3@saveChunk');
    Route::options('/preprocess', 'V3@options');
    Route::options('/uploading', 'V3@options');
});
//Route::group(['prefix' => 'v4'],function (){
//    Route::post('/preprocess','V4@preprocess');
//    Route::post('/uploading/{uploadTemp}', 'V4@saveChunk');
//    Route::options('/preprocess', 'V4@options');
//    Route::options('/uploading', 'V4@options');
//});


Route::get('/test',function (){
        //加入缓存
    return view('test');
//    $url1='http://192.168.2.200:9501/v3/preprocess';
//    $url2='http://192.168.2.200:9501/v3/uploading';
//    $client=new \App\Util\Upload\UploadClient(config('site.upload.AccessToken.mitao'),$url1,$url2);
//    $client->send('/www/wwwroot/min.live.com/file/laravel/storage/app/zip/2019/11/07/123.zip','zip',function ($res){
//        dump($res);
//    });
//    return response(123);
});
