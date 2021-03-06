<?php

namespace App\Models;

use App\Models\BaseModel as Model;

/**
 * Class AdidasWmsClientLog
 * @package App\Models
 * @version July 31, 2020, 5:05 pm CST
 *
 * @property integer id
 * @property string api_method
 * @property string app_name
 * @property string url
 * @property string input
 * @property string response
 * @property string status_code
 * @property string message
 * @property timestamp start_at
 * @property timestamp end_at
 */
class AdidasWmsClientLog extends Model
{
    protected $table = 'adidas_wms_client_log';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'api_method',
        'app_name',
        'keyword',
        'url',
        'input',
        'response',
        'status_code',
        'message',
        'start_at',
        'end_at'
    ];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'api_method' => 'string',
        'app_name' => 'string',
        'keyword' => 'string',
        'url' => 'string',
        'input' => 'json',
        'response' => 'string',
        'status_code' => 'string',
        'message' => 'string'
    ];

    /**
     * Validation rules
     *
     * @var array
     */
    public static $rules = [

    ];

}
