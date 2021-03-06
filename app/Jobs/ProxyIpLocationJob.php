<?php

namespace App\Jobs;

use App\Http\Business\ProxyIpBusiness;

class ProxyIpLocationJob extends Job
{
    /**
     * The name of the queue the job should be sent to.
     *
     * @var string|null
     */
    public $queue = "ip-location";

    /**
     * 透明度
     *
     * @var
     */
    private $proxy_ip;

    /**
     * @var
     */
    private $expired_at;

    /**
     * ProxyIpLocationJob constructor.
     * @param array $proxy_ip
     */
    public function __construct(array $proxy_ip)
    {
        $this->proxy_ip = $proxy_ip;
        $this->delay(3);
        $this->expired_at = time() + 120;
    }

    /**
     * @param ProxyIpBusiness $proxy_ip_business
     * @author jiangxianli
     * @created_at 2019-10-23 15:31
     */
    public function handle(ProxyIpBusiness $proxy_ip_business)
    {
        //超时
        if ($this->expired_at <= time()) {
            return;
        }

        //检查是否已经有IP地址
        $ip = $proxy_ip_business->getProxyIpList([
            'unique_id' => $this->proxy_ip['unique_id'],
            'first'     => 'true'
        ]);
        if ($ip && !empty($ip->ip_address)) {
            return;
        }

        try {
            //查询IP库 存入缓存
            $ip_location = $proxy_ip_business->ipLocation($this->proxy_ip['ip']);
            //更新数据IP定位信息
            $proxy_ip_business->updateProxyIp($this->proxy_ip['unique_id'], [
                'isp'        => $ip_location['isp'],
                'country'    => $ip_location['country'],
                'ip_address' => $ip_location['country'] . ' ' . $ip_location['region'] . ' ' . $ip_location['city']
            ]);
        } catch (\Exception $exception) {
            app("Logger")->error("代理IP定位失败", [
                'proxy_ip'   => $this->proxy_ip,
                'error_code' => $exception->getCode(),
                'error_msg'  => method_exists($exception, 'formatError') ? $exception->formatError() : $exception->getMessage(),
            ]);
        }

        sleep(1);

        $this->delete();
    }
}
