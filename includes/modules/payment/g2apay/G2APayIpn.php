<?php
/**
 * @author    G2A Team
 * @copyright Copyright (c) 2016 G2A.COM
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
require_once 'G2APayClient.php';
require_once 'G2APayHelper.php';
class G2APayIpn
{
    private $postParams;

    const STATUS_CANCELED          = 'canceled';
    const STATUS_COMPLETE          = 'complete';
    const STATUS_REFUNDED          = 'refunded';
    const STATUS_PARTIALY_REFUNDED = 'partial_refunded';
    const SUCCESS                  = 'Success';

    /**
     * G2APayIpn constructor.
     */
    public function __construct()
    {
        $this->postParams = $this->createArrayOfRequestParams();
    }

    public function processIpn()
    {
        if ($_SERVER['REQUEST_METHOD'] !== G2APayClient::METHOD_POST) {
            return 'Invalid request method';
        }

        $orderId = isset($this->postParams['userOrderId']) ? $this->postParams['userOrderId'] : false;

        if (!$orderId) {
            return 'Invalid parameters';
        }

        $order = G2APayHelper::getOrderById($orderId);

        if (!$this->comparePrices($this->postParams, $order)) {
            return 'Price does not match';
        }
        if (isset($this->postParams['status']) && $this->postParams['status'] === self::STATUS_CANCELED) {
            return 'Canceled';
        }
        if (!$this->isCalculatedHashMatch($this->postParams)) {
            return 'Calculated hash does not match';
        }
        if (isset($this->postParams['status']) && isset($this->postParams['transactionId'])
            && $this->postParams['status'] === self::STATUS_COMPLETE) {
            G2APayHelper::updateOrderStatus($orderId, MODULE_PAYMENT_G2APAY_PAID_STATUS_ID);
            G2APayHelper::addTransactionConfirmation($orderId, $this->postParams['transactionId']);

            return self::SUCCESS;
        }
        if (isset($this->postParams['status']) && isset($this->postParams['refundedAmount'])
            && $this->postParams['status'] === self::STATUS_REFUNDED) {
            G2APayHelper::updateOrderStatus($orderId, MODULE_PAYMENT_G2APAY_REFUNDED_STATUS_ID);
            G2APayHelper::addRefundConfirmation($orderId, $this->postParams['refundedAmount']);

            return self::SUCCESS;
        }
        if (isset($this->postParams['status']) && isset($this->postParams['refundedAmount'])
            && $this->postParams['status'] === self::STATUS_PARTIALY_REFUNDED) {
            G2APayHelper::updateOrderStatus($orderId, MODULE_PAYMENT_G2APAY_PARTIAL_REFUNDED_STATUS_ID);
            G2APayHelper::addRefundConfirmation($orderId, $this->postParams['refundedAmount']);

            return self::SUCCESS;
        }
    }

    /**
     * Modify request from G2A Pay to array format.
     *
     * @return array
     */
    private function createArrayOfRequestParams()
    {
        $vars   = array();
        foreach ($_REQUEST as $key => $value) {
            $vars[$key] = (string) $value;
        }

        return $vars;
    }

    /**
     * @param $vars
     * @param $orderDb
     * @return bool
     */
    private function comparePrices($vars, $orderDb)
    {
        $price = (float) $orderDb->fields['order_total'];
        if ($vars['amount'] == $price) {
            return true;
        }

        return false;
    }

    /**
     * @param $vars
     * @return bool
     */
    private function isCalculatedHashMatch($vars)
    {
        return G2APayHelper::calculateHash($vars) === $vars['hash'];
    }
}
