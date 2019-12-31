<?php


namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class Upload extends Model
{
    protected $table='upload';
    protected $primaryKey='resources_uuid';
    protected $keyType='string';
    public $incrementing=false;

    protected $fillable=[
        'resources_uuid','source_path','result_path','status','video_duration','error_log','type'
    ];
}
