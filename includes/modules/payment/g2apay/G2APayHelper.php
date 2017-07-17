<?php
/**
 * @author    G2A Team
 * @copyright Copyright (c) 2016 G2A.COM
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class G2APayHelper
{
    const G2A_CONFIRMED_TRANSACTION_TABLE_NAME = 'g2a_pay_confirmed_transactions';
    const G2A_REFUNDS_HISTORY_TABLE_NAME       = 'g2a_pay_refunds_history';

    /**
     * @param $params
     * @return string
     */
    public static function calculateHash($params, $order_id = null)
    {
        if (!is_null($order_id)) {
            $unhashedString = $order_id . self::getValidAmount($params->info['total'])
                . $params->info['currency'] . MODULE_PAYMENT_G2APAY_APISECRET;
        } else {
            $unhashedString = $params['transactionId'] . $params['userOrderId'] . $params['amount']
                . MODULE_PAYMENT_G2APAY_APISECRET;
        }

        return hash('sha256', $unhashedString);
    }

    /**
     * Return price in correct format.
     *
     * @param $amount
     * @return float
     */
    public static function getValidAmount($amount)
    {
        return number_format((float) $amount, 2, '.', '');
    }

    /**
     * @param $orderId
     * @return queryFactoryResult
     */
    public static function getOrderById($orderId)
    {
        global $db;

        $sql = 'SELECT * FROM ' . TABLE_ORDERS . ' WHERE orders_id = :id';
        $sql = $db->bindVars($sql, ':id', $orderId, 'integer');

        return $db->Execute($sql);
    }

    /**
     * @param $orderId
     * @return queryFactoryResult
     */
    public static function getTransactionByOrderId($orderId)
    {
        global $db;

        $sql = 'SELECT * FROM ' . self::G2A_CONFIRMED_TRANSACTION_TABLE_NAME
            . ' WHERE order_id = :orderId';
        $sql = $db->bindVars($sql, ':orderId', $orderId, 'integer');

        return $db->Execute($sql);
    }

    /**
     * @param $orderId
     * @param $transactionId
     */
    public static function addTransactionConfirmation($orderId, $transactionId)
    {
        global $db;

        $sql = 'INSERT INTO ' . self::G2A_CONFIRMED_TRANSACTION_TABLE_NAME
            . ' (order_id, transaction_id) VALUES (:orderId, :transactionId)';
        $sql = $db->bindVars($sql, ':orderId', $orderId, 'integer');
        $sql = $db->bindVars($sql, ':transactionId', $transactionId, 'string');
        $db->Execute($sql);
    }

    /**
     * @param $orderId
     * @param $refundAmount
     */
    public static function addRefundConfirmation($orderId, $refundAmount)
    {
        global $db;

        $sql = 'INSERT INTO ' . self::G2A_REFUNDS_HISTORY_TABLE_NAME
            . ' (order_id, refund_amount) VALUES (:orderId, ":refundAmount")';
        $sql = $db->bindVars($sql, ':orderId', $orderId, 'integer');
        $sql = $db->bindVars($sql, ':refundAmount', self::getValidAmount($refundAmount), 'float');
        $db->Execute($sql);
    }

    /**
     * @param $orderId
     * @param $orderStatus
     */
    public static function updateOrderStatus($orderId, $orderStatus)
    {
        global $db;

        $sql = 'UPDATE ' . TABLE_ORDERS . ' SET orders_status = :status WHERE orders_id = :orderId';
        $sql = $db->bindVars($sql, ':status', $orderStatus, 'integer');
        $sql = $db->bindVars($sql, ':orderId', $orderId, 'integer');
        $db->Execute($sql);
    }

    /**
     * @param $orderId
     * @return queryFactoryResult
     */
    public static function getRefundHistoryForOrder($orderId)
    {
        global $db;

        $sql = 'SELECT * FROM ' . G2APayHelper::G2A_REFUNDS_HISTORY_TABLE_NAME . ' WHERE order_id = :id';
        $sql = $db->bindVars($sql, ':id', $orderId, 'integer');

        return $db->Execute($sql);
    }
}
