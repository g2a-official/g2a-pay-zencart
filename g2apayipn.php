<?php
/**
 * @author    G2A Team
 * @copyright Copyright (c) 2016 G2A.COM
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

//zencart blocks $_POST with key 'hash' so little trick here has to be done
if (empty($_POST['hash'])) {
    return false;
}

$_POST['g2a_pay_hash'] = $_POST['hash'];
unset($_POST['hash']);

require_once 'includes' . DIRECTORY_SEPARATOR . 'application_top.php';
require_once 'includes' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'payment'
    . DIRECTORY_SEPARATOR . 'g2apay' . DIRECTORY_SEPARATOR . 'G2APayIpn.php';

$g2aPayIpn = new G2APayIpn();
$message   = $g2aPayIpn->processIpn();
echo $message;
