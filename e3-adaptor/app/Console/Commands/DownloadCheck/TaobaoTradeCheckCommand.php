<?php


namespace App\Console\Commands\DownloadCheck;


use App\Jobs\DingTalkNoticeTextSendJob;
use App\Models\SysStdPushQueue;
use App\Models\SysStdRefund;
use App\Models\SysStdTrade;
use App\Services\Adaptor\Taobao\Events\TaobaoTradeCreateEvent;
use App\Services\Adaptor\Taobao\Jobs\BatchDownload\TradeBatchDownloadJob;
use App\Services\Adaptor\Taobao\Jobs\TaobaoTradeBatchTransferJob;
use App\Services\Adaptor\Taobao\Repository\Rds\TaobaoRdsTradeRepository;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TaobaoTradeCheckCommand extends Command
{
    protected $signature = 'adaptor:taobao_check:trade_download
                            {--status=WAIT_SELLER_SEND_GOODS : WAIT_BUYER_PAY：等待买家付款，WAIT_SELLER_SEND_GOODS：等待卖家发货，SELLER_CONSIGNED_PART：卖家部分发货，WAIT_BUYER_CONFIRM_GOODS：等待买家确认收货，TRADE_BUYER_SIGNED：买家已签收（货到付款专用），TRADE_FINISHED：交易成功，TRADE_CLOSED：交易关闭，TRADE_CLOSED_BY_TAOBAO：交易被淘宝关闭，TRADE_NO_CREATE_PAY：没有创建外部交易（支付宝交易），WAIT_PRE_AUTH_CONFIRM：余额宝0元购合约中，PAY_PENDING：外卡支付付款确认中，ALL_WAIT_PAY：所有买家未付款的交易（包含：WAIT_BUYER_PAY、TRADE_NO_CREATE_PAY），ALL_CLOSED：所有关闭的交易（包含：TRADE_CLOSED、TRADE_CLOSED_BY_TAOBAO），PAID_FORBID_CONSIGN，该状态代表订单已付款但是处于禁止发货状态。}
                            {--create_from= : 下载开始时间 yyy-mm-dd hh:ii:ss}
                            {--create_to= : 下载结束时间 yyy-mm-dd hh:ii:ss}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '淘宝订单RDS 检查待发货订单未下载格式化的数据';

    public function handle()
    {
        if ('taobao' != config('adaptor.default')) {
            return true;
        }
        if ($this->hasOption('create_from') && !empty($this->option('create_from'))) {
            $from = $this->option('create_from');
        } else {
            $from = Carbon::now()->subHours(2)->toDateTimeString(); // 最近 6 小时
        }

        if ($this->hasOption('create_to') && !empty($this->option('create_to'))) {
            $to = $this->option('create_to');
        } else {
            $to = Carbon::now()->subMinutes(10)->toDateTimeString(); // 1 个小时前
        }

        $this->info('create_from' . $from);
        $this->info('create_to' . $to);
        if ($this->hasOption('status') && !empty($this->option('status'))) {
            $status = $this->option('status');
        } else {
            $status = 'WAIT_SELLER_SEND_GOODS';
        }

        $where = [
            ['jdp_modified', '>=', $from],
            ['jdp_modified', '<', $to],
        ];

        $pageSize = 5000;
        $jobBatch = 500;

        $this->info('start checking from rds:'. Carbon::now()->toDateTimeString());
        // 校验 订单是否正常下载
        $rds = new TaobaoRdsTradeRepository();
        $rds->builder()->select(['tid'])->where($where)->whereIn('status', [$status])
            ->orderBy('jdp_modified')->chunk($pageSize, function ($results, $page) use ($where, $jobBatch) {
                $results->pluck('tid')->chunk($jobBatch)->each(function ($tids, $k) use ($page) {
                    $existTrades = SysStdTrade::whereIn('tid', $tids)->get(['tid']);
                    if ($existTrades->isNotEmpty()) {
                        try {
                            $this->pushTradeCheck($existTrades->pluck('tid')->toArray());
                        } catch (\Exception $e) {
                            \Log::error('check push trade fail' . $e);
                        }
                        $tids = array_diff($tids->toArray(), $existTrades->pluck('tid')->toArray()); // 找出本地未存在的 tid
                    }
                    if ($tids) {
                        $key = 'taobao_trade_check_page_' . $k;
                        dispatch((new TradeBatchDownloadJob(['tids' => $tids, 'platform' => 'taobao', 'key' => $key]))->chain(
                            [
                                new TaobaoTradeBatchTransferJob(['tids' => $tids, 'key' => $key]),
                            ]));
                        // 通知部分订单，用于检查
                        $chunkNoticeTids = array_chunk($tids, 10);
                        $noticeTids = $chunkNoticeTids[0];
                        $message = sprintf("淘宝待发货订单漏单检查，检查到漏单数量：%s, 已经触发任务重试，请检查其中几个订单：%s",
                                           count($tids), implode(',', $noticeTids));
                        dispatch(new DingTalkNoticeTextSendJob(['message' => $message]));
                    }
                });
            });

        return true;
    }

    public function pushTradeCheck($tids)
    {
        $where = [];
        $where['method'] = 'tradeCreate';
        $where['platform'] = 'taobao';
        $queues = SysStdPushQueue::where($where)->whereIn('bis_id', $tids)->get();
        // 找出本地未存在的 tid
        $tids = array_diff($tids, $queues->pluck('bis_id')->toArray());
        if ($tids) {
            $stdTrades = SysStdTrade::whereIn('tid', $tids)->get();
            if ($stdTrades->isNotEmpty()) {
                $chunkNoticeTids = array_chunk($tids, 10);
                $noticeTids = implode($chunkNoticeTids[0]);
                \Event::dispatch(new TaobaoTradeCreateEvent($stdTrades));
                dispatch(new DingTalkNoticeTextSendJob(['message' => '淘宝待发货订单未及时推送检测到： ' . count($tids) . "，以重新处理:{$noticeTids}"]));
            }
            // 检查是否有已推送订单，取消单未推送的情况
            $refunds = SysStdRefund::whereIn('tid', $tids)->where('has_good_return', '0')->get('refund_id');
            if ($refunds->isNotEmpty()) {
                $refundQueues = SysStdPushQueue::whereIn('bis_id', $refunds->pluck('refund_id')->toArray())
                    ->where('status', 2)
                    ->where('platform', 'taobao')->where('method', 'tradeCancel')->get();
                $data = ['status' => 0];
                SysStdPushQueue::where('id', $refundQueues->pluck('id')->toArray())->update($data);
            }
        }
    }
}
