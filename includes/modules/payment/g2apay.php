<?php
/**
 * @author    G2A Team
 * @copyright Copyright (c) 2016 G2A.COM
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
require_once 'g2apay' . DIRECTORY_SEPARATOR . 'G2APayClient.php';
require_once 'g2apay' . DIRECTORY_SEPARATOR . 'G2APayHelper.php';
require_once 'g2apay' . DIRECTORY_SEPARATOR . 'G2APayRest.php';
require_once 'g2apay' . DIRECTORY_SEPARATOR . 'G2APayException.php';

class g2apay extends base
{
    public $code;
    public $title;
    public $description;
    public $enabled;

    const PRODUCTION_URL = 'https://checkout.pay.g2a.com/index/';
    const SANDBOX_URL    = 'https://checkout.test.pay.g2a.com/index/';
    const SANDBOX_NAME   = 'Sandbox';

    /**
     * g2apay constructor.
     */
    public function __construct()
    {
        global $order;

        $this->code        = 'g2apay';
        $this->title       = MODULE_PAYMENT_G2APAY_TEXT_TITLE;
        $this->description = MODULE_PAYMENT_G2APAY_TEXT_DESCRIPTION;
        $this->sort_order  = MODULE_PAYMENT_G2APAY_SORT_ORDER;
        $this->enabled     = ((MODULE_PAYMENT_G2APAY_STATUS === 'True') ? true : false);

        if ((int) MODULE_PAYMENT_G2APAY_ORDER_STATUS_ID > 0) {
            $this->order_status = MODULE_PAYMENT_G2APAY_ORDER_STATUS_ID;
        }

        if (is_object($order)) {
            $this->update_status();
        }
    }

    /**
     * @param $orderId
     * @return string
     */
    public function admin_notification($orderId)
    {
        if (!defined('MODULE_PAYMENT_G2APAY_STATUS')) {
            return;
        }

        $transaction   = G2APayHelper::getTransactionByOrderId($orderId);
        $transactionId = $transaction->fields['transaction_id'];

        if (!$transactionId) {
            return;
        }

        $orderDbObject  = G2APayHelper::getOrderById($orderId);
        $order          = $orderDbObject->fields;

        $output = '<td><table class="noprint">';
        $output .= '<tr style="background-color : #cccccc; border-style : dotted;">';

        $refundsDbObject = G2APayHelper::getRefundHistoryForOrder($orderId);

        $refunded = $this->getRefundedAmountForOrder($orderId);

        //show refund form only when order hasn't refunded status
        if ($order['orders_status'] !== MODULE_PAYMENT_G2APAY_REFUNDED_STATUS_ID) {
            $output .= $this->createRefundForm();
        }

        if ($refundsDbObject->RecordCount()) {
            $output .= $this->createRefundHistoryTable($refundsDbObject, $refunded);
        }

        $output .= '</tr>';
        $output .= '</table></td>';

        return $output;
    }

    /**
     * @param $orderId
     * @return string
     */
    public function getRefundedAmountForOrder($orderId)
    {
        global $db;

        $sql              = 'SELECT SUM(refund_amount) as refunded FROM ' . G2APayHelper::G2A_REFUNDS_HISTORY_TABLE_NAME
            . ' WHERE order_id = :id';
        $sql              = $db->bindVars($sql, ':id', $orderId, 'integer');
        $refundsAmountSum = $db->Execute($sql);

        return $refundsAmountSum->fields['refunded'];
    }

    /**
     * @param $refunds
     * @param $refunded_amount
     * @return string
     */
    private function createRefundHistoryTable($refunds, $refunded_amount)
    {
        $refundHistoryTable = '<table style="margin-top: 30px; width: 400px" border="1" cellspacing="0" cellpadding="5">
          <caption><strong>Refund History: </strong></caption>
          <tbody>
            <tr>
                <td align="center"><strong>Refund Date</strong></td>
                <td align="center"><strong>Amount</strong></td>
            </tr>';

        while (!$refunds->EOF) {
            $refundHistoryTable .= '<tr>
                <td align="center"><strong>' . $refunds->fields['refund_date'] . '</strong></td>
                <td align="center"><strong>' . G2APayHelper::getValidAmount($refunds->fields['refund_amount'])
                . '</strong></td></tr>';
            $refunds->MoveNext();
        }

        $refundHistoryTable .= '<tr><td align="right"><strong>Total:</strong></td>
                <td align="center"><strong>' . G2APayHelper::getValidAmount($refunded_amount) . '</strong></td></tr>
                </tbody></table><br />';

        return $refundHistoryTable;
    }

    /**
     * @return string
     */
    private function createRefundForm()
    {
        $refundForm  = '<td><table class="noprint">';
        $refundForm .= '<tr style="background-color : #eeeeee; border-style : dotted;">';
        $refundForm .= '<td class="main">' . MODULE_PAYMENT_G2APAY_ENTRY_REFUND_TITLE . '<br />';
        $refundForm .= zen_draw_form('g2a_pay_refund', FILENAME_ORDERS, zen_get_all_get_params(array('action'))
                . 'action=doRefund', 'post', '', true) . zen_hide_session_id();
        $refundForm .= MODULE_PAYMENT_G2APAY_ENTRY_REFUND_FULL;
        $refundForm .= '<br /><input type="submit" name="fullrefund" value="'
            . MODULE_PAYMENT_G2APAY_ENTRY_REFUND_BUTTON_TEXT_FULL . '" title="'
            . MODULE_PAYMENT_G2APAY_ENTRY_REFUND_BUTTON_TEXT_FULL . '" />' . ' '
            . MODULE_PAYMENT_G2APAY_TEXT_REFUND_FULL_CONFIRM_CHECK
            . zen_draw_checkbox_field('reffullconfirm', '', false) . '<br /><br />';
        $refundForm .= MODULE_PAYMENT_G2APAY_ENTRY_PARTIAL_REFUND_TITLE;
        $refundForm .= MODULE_PAYMENT_G2APAY_ENTRY_REFUND_TEXT_FULL . ' <br />'
            . zen_draw_input_field('refund_amount', 'Enter amount', 'length="8"');
        $refundForm .= '<input type="submit" name="partialrefund" value="'
            . MODULE_PAYMENT_G2APAY_ENTRY_REFUND_BUTTON_TEXT_PARTIAL
            . '" title="' . MODULE_PAYMENT_G2APAY_ENTRY_REFUND_BUTTON_TEXT_PARTIAL . '" /><br />';
        $refundForm .= '</form>';
        $refundForm .= '</td></tr></table></td><br />';

        return $refundForm;
    }

    /**
     * checks if all conditions for using module are met.
     */
    public function update_status()
    {
        global $db;
        global $order;
        // check zone
        if ($this->enabled && (int) $this->zone > 0 && isset($order->billing['country']['id'])) {
            $check_flag = false;
            $sql        = 'SELECT zone_id
              FROM ' . TABLE_ZONES_TO_GEO_ZONES
                . ' WHERE geo_zone_id = :zoneId
              AND zone_country_id = :countryId
              ORDER BY zone_id';
            $sql   = $db->bindVars($sql, ':zoneId', $this->zone, 'integer');
            $sql   = $db->bindVars($sql, ':countryId', $order->billing['country']['id'], 'integer');
            $check = $db->Execute($sql);
            while (!$check->EOF) {
                if ($check->fields['zone_id'] < 1) {
                    $check_flag = true;
                    break;
                } elseif ($check->fields['zone_id'] === $order->billing['zone_id']) {
                    $check_flag = true;
                    break;
                }
                $check->MoveNext();
            }
        }
        if ($check_flag === false) {
            $this->enabled = false;
        }
        if ($order->info['total'] == 0) {
            $this->enabled = false;
        }
        if (!MODULE_PAYMENT_G2APAY_APIHASH or !strlen(MODULE_PAYMENT_BITPAY_APIKEY) or
            !MODULE_PAYMENT_G2APAY_APISECRET or !strlen(MODULE_PAYMENT_G2APAY_APISECRET) or
            !MODULE_PAYMENT_G2APAY_MERCHANTEMAIL or !strlen(MODULE_PAYMENT_G2APAY_MERCHANTEMAIL)) {
            $this->enabled = false;
        }
    }

    /**
     * Check the user input submitted on checkout_payment.php with javascript (client-side).
     * Examples: validate credit card number, make sure required fields are filled in.
     *
     * @return bool
     */
    public function javascript_validation()
    {
        return false;
    }

    /**
     * @return array
     */
    public function selection()
    {
        return array('id' => $this->code,
            'module'      => $this->title, );
    }

    /**
     * Pre confirmation checks (ie, check if credit card information is right before sending the info to the payment server.
     *
     * @return bool
     */
    public function pre_confirmation_check()
    {
        return false;
    }

    /**
     * Functions to execute before displaying the checkout confirmation page.
     *
     * @return bool
     */
    public function confirmation()
    {
        return false;
    }

    /**
     * Functions to execute before finishing the form
     * Examples: add extra hidden fields to the form.
     *
     * @return bool
     */
    public function process_button()
    {
        return false;
    }

    /**
     * @return bool
     */
    public function before_process()
    {
        return false;
    }

    /**
     * @return bool
     */
    public function after_process()
    {
        global $insert_id;
        global $order;
        global $messageStack;

        G2APayHelper::updateOrderStatus($insert_id, MODULE_PAYMENT_G2APAY_UNPAID_STATUS_ID);

        $postVars = $this->prepareVarsArray($order, $insert_id);

        /** @var $client G2APayClient */
        $client = new G2APayClient($this->getPaymentUrl() . 'createQuote');
        $client->setMethod(G2APayClient::METHOD_POST);
        $response = $client->request($postVars);

        try {
            if (empty($response['token'])) {
                throw new G2APayException('Empty Token');
            }
            $_SESSION['cart']->reset(true);
            zen_redirect($this->getPaymentUrl() . 'gateway?token=' . $response['token']);
        } catch (G2APayException $ex) {
            $messageStack->add_session('checkout_payment', 'Some error occurs processing payment', 'error');
            zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
        }

        return false;
    }

    /**
     * This method prepare array which is send to G2A Pay based on order, payment configuration
     * and customer address.
     *
     * @param $order
     * @param $order_id
     * @return array
     */
    private function prepareVarsArray($order, $order_id)
    {
        $return_url  = zen_href_link('checkout_success');
        $cancel_url  = zen_href_link('shopping_cart');
        $vars        = array(
            'api_hash'    => MODULE_PAYMENT_G2APAY_APIHASH,
            'hash'        => G2APayHelper::calculateHash($order, $order_id),
            'order_id'    => $order_id,
            'amount'      => G2APayHelper::getValidAmount($order->info['total']),
            'currency'    => $order->info['currency'],
            'url_failure' => $cancel_url,
            'url_ok'      => $return_url,
            'items'       => $this->getItemsArray($order),
            'addresses'   => $this->getAddressesArray($order),
        );

        return $vars;
    }

    /**
     * @param $order
     * @return array
     */
    public function getItemsArray($order)
    {
        $itemsInfo  = array();
        foreach ($order->products as $orderItem) {
            $productUrl   = zen_href_link('product_info', 'products_id=' . $orderItem['id']);
            $itemsInfo[]  = array(
                'sku'    => $orderItem['model'],
                'name'   => $orderItem['name'],
                'amount' => G2APayHelper::getValidAmount($orderItem['final_price'] * $orderItem['qty']),
                'qty'    => (integer) $orderItem['qty'],
                'id'     => $orderItem['id'],
                'price'  => G2APayHelper::getValidAmount($orderItem['final_price']),
                'url'    => $productUrl,
            );
        }

        $orderDetails    = $order->info;
        $itemsInfo[]     = array(
            'sku'    => $orderDetails['shipping_module_code'],
            'name'   => $orderDetails['shipping_method'],
            'amount' => G2APayHelper::getValidAmount($orderDetails['shipping_cost']),
            'qty'    => 1,
            'id'     => 1,
            'price'  => G2APayHelper::getValidAmount($orderDetails['shipping_cost']),
            'url'    => zen_href_link('index.php', '', 'NONSSL', true, true, true),
        );

        if ($orderDetails['tax'] > 0) {
            $itemsInfo[] = array(
                'sku'    => '1',
                'name'   => MODULE_PAYMENT_G2APAY_TEXT_TAX,
                'amount' => G2APayHelper::getValidAmount($orderDetails['tax']),
                'qty'    => 1,
                'id'     => '1',
                'price'  => G2APayHelper::getValidAmount($orderDetails['tax']),
                'url'    => zen_href_link('index.php', '', 'NONSSL', true, true, true),
            );
        }
        $discountAmount = $_SESSION['order_summary']['credits_applied'];
        if ($discountAmount > 0) {
            $itemsInfo[] = array(
                'sku'    => '1',
                'name'   => 'Credits',
                'amount' => -$discountAmount,
                'qty'    => 1,
                'id'     => '1',
                'price'  => -$discountAmount,
                'url'    => zen_href_link('index.php', '', 'NONSSL', true, true, true),
            );
        }

        return $itemsInfo;
    }

    /**
     * @param $order
     * @return array
     */
    public function getAddressesArray($order)
    {
        $billingAddress  = $order->billing;
        $shippingAddress = $order->delivery;

        $addresses['billing'] = array(
            'firstname' => $billingAddress['firstname'],
            'lastname'  => $billingAddress['lastname'],
            'line_1'    => $billingAddress['street_address'],
            'line_2'    => is_null($billingAddress['suburb']) ? '' : $billingAddress['suburb'],
            'zip_code'  => $billingAddress['postcode'],
            'company'   => is_null($billingAddress['company']) ? '' : $billingAddress['company'],
            'city'      => $billingAddress['city'],
            'county'    => $billingAddress['state'],
            'country'   => $billingAddress['country']['iso_code_2'],
        );
        $addresses['shipping'] = array(
            'firstname' => $shippingAddress['firstname'],
            'lastname'  => $shippingAddress['lastname'],
            'line_1'    => $shippingAddress['street_address'],
            'line_2'    => is_null($shippingAddress['suburb']) ? '' : $shippingAddress['suburb'],
            'zip_code'  => $shippingAddress['postcode'],
            'company'   => is_null($shippingAddress['company']) ? '' : $shippingAddress['company'],
            'city'      => $shippingAddress['city'],
            'county'    => $shippingAddress['state'],
            'country'   => $shippingAddress['country']['iso_code_2'],
        );

        return $addresses;
    }

    /**
     * @return string
     */
    public function getPaymentUrl()
    {
        return MODULE_PAYMENT_G2APAY_SERVER === self::SANDBOX_NAME ? self::SANDBOX_URL : self::PRODUCTION_URL;
    }

    /**
     * @param $oID
     * @return bool
     * @throws G2APayException
     */
    public function _doRefund($oID)
    {
        global $messageStack;

        try {
            if ($_SERVER['REQUEST_METHOD'] !== G2APayClient::METHOD_POST) {
                throw new G2APayException(MODULE_PAYMENT_G2APAY_TEXT_REFUND_INVALID_REQUEST_METHOD);
            }
            if (isset($_POST['fullrefund']) && !isset($_POST['reffullconfirm'])) {
                throw new G2APayException(MODULE_PAYMENT_G2APAY_TEXT_REFUND_NOT_CONFIRMED);
            }

            $amount = str_replace(',', '.', $_POST['refund_amount']);

            if (!is_numeric($amount) && isset($_POST['partialrefund'])) {
                throw new G2APayException(MODULE_PAYMENT_G2APAY_TEXT_REFUND_AMOUNT_NOT_NUMERIC);
            }

            $transaction   = G2APayHelper::getTransactionByOrderId($oID);
            $transactionId = $transaction->fields['transaction_id'];

            $orderDbObject  = G2APayHelper::getOrderById($oID);
            $order          = $orderDbObject->fields;

            if (isset($_POST['partialrefund'])) {
                $amount = G2APayHelper::getValidAmount($amount);
            } elseif (isset($_POST['fullrefund'])) {
                $amount = G2APayHelper::getValidAmount($order['order_total'])
                    - G2APayHelper::getValidAmount($this->getRefundedAmountForOrder($oID));
            } else {
                throw new G2APayException(MODULE_PAYMENT_G2APAY_TEXT_REFUND_INVALID_REQUEST_PARAMS);
            }

            $g2aRest = new G2APayRest();

            $success = $g2aRest->refundOrder($order, $amount, $transactionId);

            if (!$success) {
                throw new G2APayException(MODULE_PAYMENT_G2APAY_TEXT_REFUND_ERROR . $amount);
            }
            $messageStack->add_session(MODULE_PAYMENT_G2APAY_TEXT_REFUND_SUCCESS . $amount, 'success');
        } catch (G2APayExceptionZ $e) {
            $messageStack->add_session($e->getMessage(), 'error');

            return false;
        }

        return true;
    }

    /**
     * If an error occurs with the process, output error messages here.
     *
     * @return bool
     */
    public function get_error()
    {
        return false;
    }

    /**
     * Check if module is installed (Administration Tool).
     *
     * @return int
     */
    public function check()
    {
        global $db;
        if (!isset($this->_check)) {
            $check_query  = $db->Execute('SELECT configuration_value FROM ' . TABLE_CONFIGURATION
                . " WHERE configuration_key = 'MODULE_PAYMENT_G2APAY_STATUS'");
            $this->_check = $check_query->RecordCount();
        }

        return $this->_check;
    }

    /**
     * install module by inserting all module config keys to configuration table.
     *
     * @return string
     */
    public function install()
    {
        global $db, $messageStack;

        if (defined('MODULE_PAYMENT_G2APAY_STATUS')) {
            $messageStack->add_session('G2A Pay module already installed.', 'error');
            $urlParams = array(
                'set'    => 'payment',
                'module' => 'g2apay',
            );
            zen_redirect(zen_href_link(FILENAME_MODULES, http_build_query($urlParams)));

            return 'failed';
        }

        $db->Execute('INSERT INTO ' . TABLE_CONFIGURATION . ' (configuration_title, configuration_key,
         configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) '
            . "VALUES ('Enable G2A Pay Module', 'MODULE_PAYMENT_G2APAY_STATUS', 'True', 
            'Easily integrate 100+ global and local payment methods with all-in-one solution.', '6', '0', 
            'zen_cfg_select_option(array(\'True\', \'False\'), ', now());");

        $db->Execute('INSERT INTO ' . TABLE_CONFIGURATION . " (configuration_title, configuration_key, 
        configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) 
        VALUES ('Environment', 'MODULE_PAYMENT_G2APAY_SERVER', 'Sandbox', 
        '', '6', '25', 'zen_cfg_select_option(array(\'Production\', \'Sandbox\'), ', now())");

        $db->Execute('INSERT INTO ' . TABLE_CONFIGURATION . ' (configuration_title, configuration_key, 
        configuration_value, configuration_description, configuration_group_id, sort_order, date_added) '
            . "VALUES ('API Hash', 'MODULE_PAYMENT_G2APAY_APIHASH', '', '', '6', '0', now());");

        $db->Execute('INSERT INTO ' . TABLE_CONFIGURATION . ' (configuration_title, configuration_key, 
        configuration_value, configuration_description, configuration_group_id, sort_order, date_added) '
            . "VALUES ('API Secret', 'MODULE_PAYMENT_G2APAY_APISECRET', '', '', '6', '0', now());");

        $db->Execute('INSERT INTO ' . TABLE_CONFIGURATION . ' (configuration_title, configuration_key, 
        configuration_value, configuration_description, configuration_group_id, sort_order, date_added) '
            . "VALUES ('Merchant Email', 'MODULE_PAYMENT_G2APAY_MERCHANTEMAIL', '', '', '6', '0', now());");

        $db->Execute('INSERT INTO ' . TABLE_CONFIGURATION . ' (configuration_title, configuration_key, 
        configuration_value, configuration_description, configuration_group_id, sort_order, set_function, 
        use_function, date_added) '
            . "VALUES ('Unpaid Order Status', 'MODULE_PAYMENT_G2APAY_UNPAID_STATUS_ID', '"
            . intval(DEFAULT_ORDERS_STATUS_ID) .  "', 'Automatically set the status of unpaid orders to this value.', 
            '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");

        $db->Execute('INSERT INTO ' . TABLE_CONFIGURATION . ' (configuration_title, configuration_key, 
        configuration_value, configuration_description, configuration_group_id, sort_order, set_function, 
        use_function, date_added) '
            . "VALUES ('Paid Order Status', 'MODULE_PAYMENT_G2APAY_PAID_STATUS_ID', '2', 
            'Automatically set the status of paid orders to this value.', '6', '0', 
            'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");

        $db->Execute('INSERT INTO ' . TABLE_CONFIGURATION . ' (configuration_title, configuration_key, 
        configuration_value, configuration_description, configuration_group_id, sort_order, set_function, 
        use_function, date_added) '
            . "VALUES ('Refunded Order Status', 'MODULE_PAYMENT_G2APAY_REFUNDED_STATUS_ID', '2', 
            'Automatically set the status of fully refunded orders to this value.', '6', '0', 
            'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");

        $db->Execute('INSERT INTO ' . TABLE_CONFIGURATION . ' (configuration_title, configuration_key, 
        configuration_value, configuration_description, configuration_group_id, sort_order, set_function, 
        use_function, date_added) '
            . "VALUES ('Partialy refunded Order Status', 'MODULE_PAYMENT_G2APAY_PARTIAL_REFUNDED_STATUS_ID', '2', 
            'Automatically set the status of partial refunded orders to this value.', '6', '0', 
            'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");

        $db->Execute('INSERT INTO ' . TABLE_CONFIGURATION . ' (configuration_title, configuration_key, 
        configuration_value, configuration_description, configuration_group_id, sort_order, use_function, 
        set_function, date_added) '
            . "VALUES ('Payment Zone', 'MODULE_PAYMENT_G2APAY_ZONE', '0', 
            'If a zone is selected, only enable this payment method for that zone.', '6', '2', 
            'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");

        $db->Execute('INSERT INTO ' . TABLE_CONFIGURATION . ' (configuration_title, configuration_key, 
        configuration_value, configuration_description, configuration_group_id, sort_order, date_added) '
            . "VALUES ('Sort order of display.', 'MODULE_PAYMENT_G2APAY_SORT_ORDER', '0', 
            'Sort order of display. Lowest is displayed first.', '6', '2', now())");

        $db->Execute('CREATE TABLE IF NOT EXISTS ' . G2APayHelper::G2A_CONFIRMED_TRANSACTION_TABLE_NAME
            . ' (id INT NOT NULL AUTO_INCREMENT, order_id INT NOT NULL, transaction_id VARCHAR(50) NOT NULL, 
            PRIMARY KEY (id))');

        $db->Execute('CREATE TABLE IF NOT EXISTS ' . G2APayHelper::G2A_REFUNDS_HISTORY_TABLE_NAME
            . ' (id INT NOT NULL AUTO_INCREMENT, order_id INT NOT NULL, refund_amount VARCHAR(10) NOT NULL, 
            refund_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id))');
    }

    /**
     * remove module config from database.
     */
    public function remove()
    {
        global $db;
        $db->Execute('DELETE FROM ' . TABLE_CONFIGURATION
            . " WHERE configuration_key IN ('" . implode("', '", $this->keys()) . "')");
        $db->Execute('DROP TABLE IF EXISTS ' . G2APayHelper::G2A_CONFIRMED_TRANSACTION_TABLE_NAME);
    }

    /**
     * return module configuration keys.
     *
     * @return array
     */
    public function keys()
    {
        return array(
            'MODULE_PAYMENT_G2APAY_STATUS',
            'MODULE_PAYMENT_G2APAY_SERVER',
            'MODULE_PAYMENT_G2APAY_APIHASH',
            'MODULE_PAYMENT_G2APAY_APISECRET',
            'MODULE_PAYMENT_G2APAY_MERCHANTEMAIL',
            'MODULE_PAYMENT_G2APAY_UNPAID_STATUS_ID',
            'MODULE_PAYMENT_G2APAY_PAID_STATUS_ID',
            'MODULE_PAYMENT_G2APAY_REFUNDED_STATUS_ID',
            'MODULE_PAYMENT_G2APAY_PARTIAL_REFUNDED_STATUS_ID',
            'MODULE_PAYMENT_G2APAY_ZONE',
            'MODULE_PAYMENT_G2APAY_SORT_ORDER',
        );
    }
}
