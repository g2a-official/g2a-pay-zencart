<?php
/**
 * @author    G2A Team
 * @copyright Copyright (c) 2016 G2A.COM
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
define('MODULE_PAYMENT_G2APAY_TEXT_TITLE', 'G2A Pay');
if (function_exists(zen_catalog_href_link)) {
    define('MODULE_PAYMENT_G2APAY_TEXT_DESCRIPTION',
        'Easily integrate 100+ global and local payment methods with all-in-one solution. 
<br />Set your <strong>IPN URL</strong> to:<br /><nobr><pre>' . str_replace('index.php?main_page=index', 'g2apayipn.php',
            zen_catalog_href_link(FILENAME_DEFAULT, '', 'SSL')) . '</pre></nobr>');
}
define('MODULE_PAYMENT_G2APAY_TEXT_TAX', 'Tax');

//REFUNDS
define('MODULE_PAYMENT_G2APAY_ENTRY_REFUND_TITLE', '<strong>Order Refund</strong>');
define('MODULE_PAYMENT_G2APAY_ENTRY_PARTIAL_REFUND_TITLE', '<strong>Order Partial Refund</strong>');
define('MODULE_PAYMENT_G2APAY_ENTRY_REFUND_FULL', 'If you wish to refund this order in its entirety, click here:');
define('MODULE_PAYMENT_G2APAY_ENTRY_REFUND_BUTTON_TEXT_FULL', 'Do Full Refund');
define('MODULE_PAYMENT_G2APAY_ENTRY_REFUND_BUTTON_TEXT_PARTIAL', 'Do Partial Refund');
define('MODULE_PAYMENT_G2APAY_ENTRY_REFUND_TEXT_FULL',
    '<br /> If you wish to refund only part of the order price you can do it by providing amount underneath');
define('MODULE_PAYMENT_G2APAY_TEXT_REFUND_FULL_CONFIRM_CHECK', 'Confirm');
define('MODULE_PAYMENT_G2APAY_TEXT_REFUND_SUCCESS', 'Sent online refund request for amount: ');
define('MODULE_PAYMENT_G2APAY_TEXT_REFUND_ERROR', 'Online refund request failed for amount: ');
define('MODULE_PAYMENT_G2APAY_TEXT_REFUND_NOT_CONFIRMED', 'You have to confirm full refund');
define('MODULE_PAYMENT_G2APAY_TEXT_REFUND_AMOUNT_NOT_NUMERIC', 'Please enter refund amount in number format');
define('MODULE_PAYMENT_G2APAY_TEXT_REFUND_INVALID_REQUEST_METHOD', 'Invalid request method');
define('MODULE_PAYMENT_G2APAY_TEXT_REFUND_INVALID_REQUEST_PARAMS',
    'Invalid request parameters, can not proceed with refund');
