<?php


namespace App\Services\Adaptor\Taobao\Jobs;


use App\Facades\Adaptor;

class TradeApiBatchDownloadJob extends BaseDownloadJob
{
    private $params;

    /**
     * TradeApiBatchDownloadJob constructor.
     *
     * @param $params
     */
    public function __construct($params)
    {
        $this->params = $params;
    }

    /**
     * @throws \Exception
     *
     * @author linqihai
     * @since 2020/1/18 16:38
     */
    public function handle()
    {
        Adaptor::platform('taobao')->download('tradeApi', $this->params);
    }

    /**
     * 获取分配给这个任务的标签
     *
     * @return array
     */
    public function tags()
    {
        return ['taobao_api_batch_download'];
    }
}
