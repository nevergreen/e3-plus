<?php


namespace App\Services\Adaptor\Jingdong\Jobs;


use App\Jobs\Job;

class BaseDownloadJob extends Job
{
    public $queue = 'jingdong_download';

    // public $delay = 10;

    // public $tries = 5;

    // public $timeout = 10 单个处理超时时间


    public function failed($e)
    {
        // 异常预警处理
    }
}