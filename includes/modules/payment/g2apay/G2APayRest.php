<?php
/**
 * @author    G2A Team
 * @copyright Copyright (c) 2016 G2A.COM
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class G2APayRest
{
    /**
     * @var array REST base urls grouped by environment
     */
    protected static $REST_BASE_URLS = array(
        'Production' => 'https://pay.g2a.com/rest',
        'Sandbox'    => 'https://www.test.pay.g2a.com/rest',
    );

    /**
     * @param $order
     * @param $amount
     * @param $transaction_id
     * @return bool
     */
    public function refundOrder(array $order, $amount, $transaction_id)
    {
        try {
            $amount = G2APayHelper::getValidAmount($amount);

            $data = [
                    'action' => 'refund',
                    'amount' => $amount,
                    'hash'   => $this->generateRefundHash($order, $amount, $transaction_id),
                ];

            $path   = sprintf('transactions/%s', $transaction_id);
            $url    = $this->getRestUrl($path);
            $client = $this->createRestClient($url, G2APayClient::METHOD_PUT);

            $result = $client->request($data);

            return is_array($result) && isset($result['status']) && strcasecmp($result['status'], 'ok') === 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param $url
     * @param $method
     * @return G2APayClient
     */
    protected function createRestClient($url, $method)
    {
        $client = new G2APayClient($url);
        $client->setMethod($method);
        $client->addHeader('Authorization', MODULE_PAYMENT_G2APAY_APIHASH . ';' . $this->getAuthorizationHash());

        return $client;
    }

    /**
     * @param $order
     * @param $amount
     * @param $transaction_id
     * @return mixed
     */
    protected function generateRefundHash($order, $amount, $transaction_id)
    {
        $string = $transaction_id . $order['orders_id'] . G2APayHelper::getValidAmount($order['order_total'])
            . $amount . MODULE_PAYMENT_G2APAY_APISECRET;

        return hash('sha256', $string);
    }

    /**
     * @param string $path
     * @return string
     */
    public function getRestUrl($path = '')
    {
        $path     = ltrim($path, '/');
        $base_url = self::$REST_BASE_URLS[MODULE_PAYMENT_G2APAY_SERVER];

        return $base_url . '/' . $path;
    }

    /**
     * Returns generated authorization hash.
     *
     * @return string
     */
    public function getAuthorizationHash()
    {
        $string = MODULE_PAYMENT_G2APAY_APIHASH . MODULE_PAYMENT_G2APAY_MERCHANTEMAIL . MODULE_PAYMENT_G2APAY_APISECRET;

        return hash('sha256', $string);
    }
}
