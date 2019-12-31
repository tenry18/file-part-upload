<?php


namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class UploadTemp extends Model
{
    protected $table='upload_temp';
    protected $primaryKey='resource_uuid';
    protected $keyType='string';
    public $incrementing=false;

    protected $fillable=[
        'resource_uuid','source_path','resource_size','chunk_total','chunk_index'
    ];
}
