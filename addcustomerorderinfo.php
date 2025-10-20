<?php
include("../includes/functions.php");
require('../includes/config.inc.php');
require('../includes/AuthnetXML.class.php');
require('../includes/paypal.php');
require('../includes/config.php');
include('../includes/PHPMailer.php');
include('../includes/SMTP.php');

$orderPriceCalc = OrderPriceCalc();
$totalCartItems = \Bulkapparel\Cart\Cart::instance()->withEstimatedDelivery()->items()->getLineItemsTotalQuantity();
$totalCartPrice = \Bulkapparel\Cart\Cart::instance()->items()->getSubTotal();

global $db;

// Start - Declined transactions should show showed last 4 digits of the card, addresses, and names and email if possible - AP - 05/04/2021
$orderNumber = '';
if (isset($_SESSION['currentOrder']) && !empty($_SESSION['currentOrder']))
  $orderNumber =  $_SESSION['currentOrder'][0]['oid'];
// End - Declined transactions should show showed last 4 digits of the card, addresses, and names and email if possible - AP - 05/04/2021

function randomPassword() {
    $alphabet = '1234567890';
    $pass = array(); //remember to declare $pass as an array
    $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
    for ($i = 0; $i < 14; $i++) {
        $n = rand(0, $alphaLength);
        $pass[] = $alphabet[$n];
    }
    return implode($pass); //turn the array into a string
}

function generateOrderNo() {
        return 'B' . substr(round(microtime(true) * 1000),1);
}

$email = "";
$user_id = $_SESSION["cid"];
if (isset($_SESSION["uid"]) && $_SESSION["uid"] != "") {
        $user_id = $_SESSION["uid"];
}
$errorMsg['cid'] = $user_id; // Start - check out issue something went wrong - AP - 05/30/2022

$orderno = generateOrderNo();

if (isset($user_id) && $user_id!="") {
        global $db;

        $addresses = $db->rawQuery("SELECT * FROM ci_address WHERE cid = ? AND ordId = 0", [$user_id]);

        $shippingAddress = array_values(array_filter($addresses, function ($address) {
                return $address['addressType'] == 1;
        }));

        if (!empty($shippingAddress)) {
                $cshipdetails = $shippingAddress[0];
                $custname = explode('^', $cshipdetails['customer']);
                $email = $cshipdetails['email'];

                unset($cshipdetails['id']);
                $cshipdetails['ordId'] = $orderno;

                // Display company name in orderconfirm page - SF - 06/24/2025
                if (empty($cshipdetails['company'])) {
                        // Fetch from shirtchamp.ci_customer_address company
                        $customerDetails = $db->rawQueryOne("SELECT company FROM shirtchamp.ci_customer_address WHERE customerId = ?", [$user_id]);

                        if (!empty($customerDetails) && !empty($customerDetails['company'])) {
                                $cshipdetails['company'] = $customerDetails['company'];
                        }
                }
                // Display company name in orderconfirm page - SF - 06/24/2025

                $insertS = $db->insert("ci_address", $cshipdetails);
        }

        $billingAddress = array_values(array_filter($addresses, function ($address) {
                return $address['addressType'] == 0;
        }));

        if (!empty($billingAddress)) {
                $cbilldetails = $billingAddress[0];
                $custnameb = explode('^', $cbilldetails['customer']);
                $email = $cbilldetails['email'];

                unset($cbilldetails['id']);
                $cbilldetails['ordId'] = $orderno;

                // START - Add disclaimer on checkout page for text receive - JA - 11232023
                // START - Add disclaimer on checkout page for text receive (ENHANCEMENT) - JA - 12142023

                // if(isset($_POST['ischeckReceiveOrderUpdate']) && $_POST['ischeckReceiveOrderUpdate'] ==1) {
                //      $isReceiveOrderUpdates = 1;
                // } else{
                //      $isReceiveOrderUpdates = 0;
                // }

                // $cbilldetails['receive_order_updates'] = $isReceiveOrderUpdates;

                if(isset($_POST['ischeckReceiveOrderUpdate']) && $_POST['ischeckReceiveOrderUpdate'] ==1) {
                        $isReceiveOrderUpdates = 1;
                } else{
                        $isReceiveOrderUpdates = 0;
                }

                if ($cbilldetails['telAdd'] != '') {

                        $vars = [];

                        $sql = "select * from ci_customer where id = '" . $cbilldetails['cid'] . "'";
                        $customer = $db->rawQueryOne($sql);

                        $email = null;
                        if ($customer) {
                                $vars = [
                                        'email' => $customer['email'],
                                        'customerId' => $customer['id'],
                                        'phoneNumber' => $cbilldetails['telAdd']
                                ];

                                $vars['status'] = $isReceiveOrderUpdates;
                                $email = $customer['email'];
                        } else {
                                $sql = "select * from ci_customer where email = '" . $cbilldetails['email'] . "'";
                                $customer = $db->rawQueryOne($sql);

                                if ($customer) {
                                        $vars = [
                                                'email' => $customer['email'],
                                                'customerId' => $customer['id'],
                                                'phoneNumber' => $cbilldetails['telAdd']
                                        ];

                                        $vars['status'] = $isReceiveOrderUpdates;
                                        $email = $customer['email'];
                                } else {
                                        $vars = [
                                                'email' => $cbilldetails['email'],
                                                'customerId' => $cbilldetails['cid'],
                                                'phoneNumber' => $cbilldetails['telAdd']
                                        ];

                                        $vars['status'] = $isReceiveOrderUpdates;
                                        $email = $cbilldetails['email'];
                                }
                        }

                        if ($email) {
                                $sql = "select * from ci_promotion_status_subs where email = '" . $email . "'";
                                $promotion_status_subs = $db->rawQueryOne($sql);
                                if ($promotion_status_subs) {
                                        $vars['updated_at'] = date('Y-m-d H:i:s');
                                        $db->where('email', $email)->update("ci_promotion_status_subs", $vars);
                                } else {
                                        $vars['created_at'] = date('Y-m-d H:i:s');
                                        $vars['updated_at'] = date('Y-m-d H:i:s');
                                        $db->insert("ci_promotion_status_subs", $vars);
                                }
                        }
                }

                // END - Add disclaimer on checkout page for text receive (ENHANCEMENT) - JA - 12142023
                // END - Add disclaimer on checkout page for text receive - JA - 11232023

                $insertB = $db->insert("ci_address", $cbilldetails);
        }


        // $cshipdetails = $db->rawQueryOne ("select * from ci_address where cid=? and addressType=1 and ordId=0 ", array($user_id));
        // $custname = explode("^",$cshipdetails['customer']);
        // $email = $cshipdetails['email'];
        // $cshipdetails['ordId']= $orderno;
        // unset($cshipdetails['id']);
        // $insertS = $db->insert("ci_address",$cshipdetails);

        // $cbilldetails = $db->rawQueryOne ("select * from ci_address where cid=? and addressType=0 and ordId=0 ", array($user_id));
        // $custnameb=explode("^",$cbilldetails['customer']);
        // $email = $cbilldetails['email'];
        // $cbilldetails['ordId']= $orderno;
        // unset($cbilldetails['id']);
        // $insertB = $db->insert("ci_address",$cbilldetails);
} else {
        $cshipdetails = $db->rawQueryOne ("select * from ci_address where cid=? and addressType=1 and ordId=0 ", array($user_id));
        $custname=explode("^",$cshipdetails['customer']);
        $email = $cshipdetails['email'];
        $cshipdetails['ordId']=$orderno;
        unset($cshipdetails['id']);
        $insertS = $db->insert("ci_address",$cshipdetails);

        $cbilldetails = $db->rawQueryOne ("select * from ci_address where cid=? and addressType=0 and ordId=0 ", array($user_id));
        $custnameb=explode("^",$cbilldetails['customer']);
        $email = $cbilldetails['email'];
        $cbilldetails['ordId']=$orderno;
        unset($cbilldetails['id']);
        $insertB = $db->insert("ci_address",$cbilldetails);
}

//Start - Bulk Admin Error - RM - 07/25/2022
if(!$insertS) {
        writeFailedTransaction('Failed to insert shipping address to ci_address table '. $orderno, $db->getLastError());
}

if(!$insertB) {
        writeFailedTransaction('Failed to insert billing address to ci_address table '. $orderno, $db->getLastError());
}
//End - Bulk Admin Error - RM - 07/25/2022

$estimateDeliveryHtml = '<strong>Estimated Delivery - </strong> 1-3 Business Days';
$transits = \Bulkapparel\Cart\Cart::instance()->items()->getTransits();
if (!empty($transits) && !empty($earliest = $transits->getEarliest())) {
        $estimateDeliveryHtml = '<strong>Estimated Delivery - </strong> ' . $earliest->display();

        if (!empty($furthest = $transits->getFurthest()))
                $estimateDeliveryHtml .= ' - ' . $furthest->display();
}

// Start - Fix checking out empty cart - John - 05/29/2020 1:40PM
if($totalCartItems <= 0) {
        if (isset($_POST['payty'])&& $_POST['payty']=="paypay") {
                header("Location: ".base_url_site."cart");
                exit();
        }

        $errorMsg['result'] = 1;
        $errorMsg['msg'] = ["Your cart is empty. You've not been charged. Redirecting you to cart page"];
        $errorMsg['error'] = '';
        $errorMsg['redirect'] = base_url_site."cart";

        // Start - check out and cart empty cart refunction - AP - 11/09/2021
                $data_empty_cart['ipaddress'] =  get_ip_address();
        $data_empty_cart['useragent'] =  $_SERVER['HTTP_USER_AGENT'];
        $data_empty_cart['userid'] = ($user_id) ? $user_id : 0;
        $data_empty_cart['refId'] = $_COOKIE['RefID']; // Start - lets add a new field to cookies - AP - 05/04/2021
                $data_empty_cart['oid'] = $orderNumber; // Start - Declined transactions should show showed last 4 digits of the card, addresses, and names and email if possible - AP - 05/04/2021
        $data_empty_cart['errormessage'] = json_encode($errorMsg);
        $data_empty_cart['createdat'] = date('Y-m-d H:i:s');
        $data_empty_cart['updatedat'] = date('Y-m-d H:i:s');

        // Start - prefill and existing logs for abdon cart logs adjustment - AP - 12/01/2021
                $authData = [];
                if (isset($_SESSION["uid"]) && $_SESSION["uid"] != "")
                        $authData = ['column' => 'abd_cid', 'value' => $_SESSION["uid"]];
                else if (isset($_COOKIE["csid"]) && $_COOKIE["csid"]!="")
                        $authData = ['column' => 'abd_cokiesid', 'value' => $_COOKIE["csid"]];

                if (!empty($authData)) {
                        $abdonCart = $db->rawQueryOne('SELECT abd_cokiesid, abd_expired FROM ci_abdoncart WHERE ' . $authData['column'] . ' = ? LIMIT 1', [$authData['value']]);
                $data_empty_cart['sessionid'] = session_id();
                $data_empty_cart['cookieid'] = !empty($abdonCart['abd_cokiesid']) ? $abdonCart['abd_cokiesid'] : '';
                $data_empty_cart['abdexpired'] = !empty($abdonCart['abd_expired']) ? $abdonCart['abd_expired'] : '';
                $data_empty_cart['url'] = base_url_site . $_SERVER['REQUEST_URI'];
                }
                // End - prefill and existing logs for abdon cart logs adjustment - AP - 12/01/2021

        $insertcartempty = $db->insert("ci_empty_cart_logs",$data_empty_cart);

                writeFailedTransaction($errorMsg['msg'], [], null, null, 'System');

                echo json_encode($errorMsg);
                exit();

        // End - check out and cart empty cart refunction - AP - 11/09/2021

}
// End - Fix checking out empty cart - John - 05/29/2020

//Start - Alpha API issue with zero QTY on our orders - Roi - 09/28/2020
if(!empty($outOfStockItems = \Bulkapparel\Cart\Cart::instance()->items()->outOfStocks())){
        \Bulkapparel\Cart\Cart::instance()->items()->clearOutOfStocks();

        $textMsg = implode(', ', array_map(function ($line) {
                return implode(' ', [$line->brandName, $line->styleName, $line->colorName, $line->sizeName]);
        }, $outOfStockItems));
        //$textMsg = implode(', ', $items_empty);

        if (isset($_POST['payty'])&& $_POST['payty']=="paypay") {

                //Start - Dynamic error messages - RM - 03/11/2022
                $dynamicErrorMsg = getTranslatedPaymentResponse((object) ['errorCode' => "SE03", "errorText" => "Inventory update: ".$textMsg." is no longer available and removed from your cart."]);
                $dynamicErrorMsg = str_replace('{textmsg}', $textMsg, $dynamicErrorMsg);
                //$_SESSION['errorEmptyQty'] = "Inventory update: ".$textMsg." is no longer available and removed from your cart.";
                $_SESSION['errorEmptyQty'] = $dynamicErrorMsg;
                //End - Dynamic error messages - RM - 03/11/2022

                header("Location: ".base_url_site."cart");
                exit();
        }

        //Start - Dynamic error messages - RM - 03/11/2022
        $dynamicErrorMsg = getTranslatedPaymentResponse((object) ['errorCode' => "SE03", "errorText" => "Inventory update: ".$textMsg." is no longer available and removed from your cart."]);
        $dynamicErrorMsg = str_replace('{textmsg}', $textMsg, $dynamicErrorMsg);
        //$_SESSION['errorEmptyQty'] = "Inventory update: ".$textMsg." is no longer available and removed from your cart.";
        $_SESSION['errorEmptyQty'] = $dynamicErrorMsg;
        //End - Dynamic error messages - RM - 03/11/2022

        $errorMsg['result'] = 6; //empty qty from DB
        echo json_encode($errorMsg);
        exit();
}
//End - Alpha API issue with zero QTY on our orders - Roi - 09/28/2020

if ((round($totalCartPrice) <= round(EDIRATE)) && (round(EDIRATE) != 0 || round(EDIRATE) != "0.00")) {

        $shipping_addr = [
                'customer' => $cshipdetails['company'],
                'attn' => $custnameb[0]." ".$custnameb[1],
                'address' => $cbilldetails['address'].",".$cbilldetails['address2'],
                'city' => $cbilldetails['city'],
                'state' => $cbilldetails['state'],
                'zip' => $cbilldetails['zip'],
                'residential' => true
        ];
        $wereAbbr = getTransistNwarehouses($cshipdetails['zip']);

        if ($totalCartItems > 0) {
                foreach($orderPriceCalc as $key => $val3) {
                        $warehouse[] = [
                                "warehouseAbbr" => $wereAbbr,
                                "identifier" => $val3['sku'],
                                "qty" => $val3['qty']
                        ];
                }
        }
        $shippingAddress=serialize($shipping_addr);
        $warehouses=serialize($warehouse);
        $orderBData = ['customerOrdNo' => $orderno, 'shippingAddress' => $shippingAddress, 'warehouses' => $warehouses, 'isSentEdi' => 0];
        //print_r($orderData);
        $insert = $db->insert("ci_batchorder", $orderBData);
        $logTime = date('h:i:s');
        $logDate = date('m/d/Y');
        $contentlog = "Order #".$orderno." sent to EDI Process at ".$logTime." on ".$logDate;
        /*$dataA = array ('orderId' => $orderno,'contentlog' => $contentlog,'logTime' => $orderno,'logDate' => date('Y-m-d'),'logtype' => '0');
        $db->insert("ci_activitylog",$dataA);*/
        $ordStat="Pending";
        $scordStat="Approved";
} else {
        $ordStat = "Pending";
        if ($_POST['cardno'] == '1234567899999225' && $_POST['cmonth'] == '1' && $_POST['cyear'] == '2030' && $_POST['ccode'] == '999') {
                $scordStat = "Not Paid";
        } else {
                $scordStat = "Pending";
        }
}

if (strlen($email) <= 6) {
        try {
                $email = $_SESSION["email"];
        } catch (Exception $e){
                $errorMsg['result'] = 1;
                $errorMsg['msg'] = "Incorrect Email Address";
                $errorMsg['error'] = $e->getMessage();

                writeFailedTransaction('Incorrect Email Address', $e->getMessage(), null, null, 'System'); // Start - Authnet response implementation - AP - 03/04/2022
        }
}

$phoneNumber = "";
if (isset($_SESSION["phone"])) {
        $phoneNumber = $_SESSION["phone"];
}

// Start - Issues during customer checkout - AP - 02/22/2022

function writeFailedTransaction($error, $data = [], $total = null, $shippingChargeFailed = null, $type = 'Authnet') {
        global $db, $user_id, $email, $totalCartPrice, $cshipdetails, $orderNumber, $xml, $total, $cbilldetails; //Start - Transaction failures - RM - 05/03/2022 //Start - Transaction Failures Display Request - RM - 05/16/2022 added: cbilldetails
        //global $db, $user_id, $email, $cshipdetails, $orderNumber, $xml;

    $request = $_REQUEST;
    if (!empty($request['cardno']))
        $request['cardno'] = obfuscateString($request['cardno'], 4);

    if (!empty($request['ccode']))
        $request['ccode'] = obfuscateString($request['ccode']);

        // Start - Lets verify failed transaction. There were over 146 failed transactions and some don't have any info logged - AP - 03/16/2022
    if (isset($data['AuthnetXMLxml'])) {
        $requestXml = simplexml_load_string($data['AuthnetXMLxml']);

        if ($requestXml->transactionRequest->payment->creditCard->cardNumber) {
            $requestXml->transactionRequest->payment->creditCard->cardNumber = obfuscateString((string) $requestXml->transactionRequest->payment->creditCard->cardNumber, 4);
        }

        if ($requestXml->transactionRequest->payment->creditCard->cardCode) {
            $requestXml->transactionRequest->payment->creditCard->cardCode = obfuscateString((string) $requestXml->transactionRequest->payment->creditCard->cardCode);
        }

        $data['AuthnetXMLxml'] = $requestXml->asXML();
    } else if (isset($data['xml']) && isset($data['xml']['AuthnetXMLxml'])) {
                $requestXml = simplexml_load_string($data['xml']['AuthnetXMLxml']);

        if ($requestXml->transactionRequest->payment->creditCard->cardNumber) {
            $requestXml->transactionRequest->payment->creditCard->cardNumber = obfuscateString((string) $requestXml->transactionRequest->payment->creditCard->cardNumber, 4);
        }

        if ($requestXml->transactionRequest->payment->creditCard->cardCode) {
            $requestXml->transactionRequest->payment->creditCard->cardCode = obfuscateString((string) $requestXml->transactionRequest->payment->creditCard->cardCode);
        }

        $data['xml']['AuthnetXMLxml'] = $requestXml->asXML();
        }
    // End - Lets verify failed transaction. There were over 146 failed transactions and some don't have any info logged - AP - 03/16/2022

        $digitalBill = null;
        $digitalShip = null;

        if (isset($request['addresses']) && !empty($request['addresses'])) {
                $digitalPayAddress = json_decode($request['addresses']);

                $digitalBill = $digitalPayAddress->billing;
                $digitalShip = $digitalPayAddress->shipping;
        }

        $failedTransaction = [
                'customerNo' => $user_id,
                'email' => $email,
                'subtotal' => $totalCartPrice,
                'shipping' => \Bulkapparel\Cart\Cart::instance()->items()->getShippingAmount(),
                'tax' => \Bulkapparel\Cart\Cart::instance()->items()->getTaxAmount(),
                'total' => $total,
                'paymentType' => isset($request['paymentType']) && in_array($request['paymentType'], ['ApplePay', 'GooglePay']) ? $request['paymentType'] : $type, // Start - We need to add apple and droid pay - AP - 07/15/2022
                'dateTime' => date('Y-m-d H:i:s'),
                'error' => $error,
                // Start - Authnet response implementation - AP - 02/28/2022
                'errorCode' => isset($xml->transactionResponse->errors) ? (string) $xml->transactionResponse->errors->error->errorCode : '',
                'errorText' => isset($xml->transactionResponse->errors) ? (string) $xml->transactionResponse->errors->error->errorText : '',
                'responseCode' => (string) $xml->transactionResponse->responseCode,
                'authCode' => (string) $xml->transactionResponse->authCode,
                'avsResultCode' => (string) $xml->transactionResponse->avsResultCode,
                'cvvResultCode' => (string) $xml->transactionResponse->cvvResultCode,
                'cavvResultCode' => (string) $xml->transactionResponse->cavvResultCode,
                'transId' => (string) $xml->transactionResponse->transId,
                'refTransId' => (string) $xml->transactionResponse->refTransId,
                // End - Authnet response implementation - AP - 02/28/2022
                'lastFourCC' => substr($_POST['cardno'], -4),
                'address' => $cshipdetails['address'],
                'city' => $cshipdetails['city'],
                'state' => $cshipdetails['state'],
                'zip' => $cshipdetails['zip'], //End - failed transaction detail reports - RM - 04/27/2021
                'customerName' => $cshipdetails['customer'], //Start - Transaction failures - RM - 05/03/2022
                'billingAddress' => $cbilldetails['address'].' '.$cbilldetails['city'].' '.$cbilldetails['state'].' '.$cbilldetails['zip'], //Start - Transaction Failures Display Request - RM - 05/16/2022
                'oid' => $orderNumber, // Start - Declined transactions should show showed last 4 digits of the card, addresses, and names and email if possible - AP - 05/04/2021
                'source' => json_encode(debug_backtrace()), // Start - Issues during customer checkout - AP - 02/22/2022
                'cookies' => json_encode($_COOKIE),
                'sessions' => json_encode($_SESSION),
                'requests' => json_encode($request),
                'userAgent' => $_SERVER['HTTP_USER_AGENT'],
                'ipAddress' => get_ip_address(),
                'refId' => $_COOKIE['RefID'],
                'data' => json_encode($data),
                'digitalBill' => json_encode($digitalBill),
                'digitalShip' => json_encode($digitalShip),
        ];

        return $db->insert('ci_failed_transactions', $failedTransaction);
}
// End - Issues during customer checkout - AP - 02/22/2022

// check for fraud
$fraudEmails = [
        'miskinpeople@yahoo.com',
        'degangarage.id@gmail.com',
        'mark@yahoo.com',
        'merch@protonmail.com',
        'fred@yahoo.com',
        'market@protonmail.com',
        'attack@protonmail.com',
        'cha@yahoo.com',
        'jin@yahoo.com',
        'emma@yahoo.com',
        'rock@protonmail.com',
        'danny@yahoo.com',
        'tommy@yahoo.com',
        'merch@protonmail.com',
        'i@yahoo.com',
        'tom@yahoo.com',
        'dean@yahoo.com',
        'store@protonmail.com',
        'cho@yahoo.com',
        'attack@protonmail.com',
        'rea@yahoo.com',
        'roy@gmail.com',
        'rid@yahoo.com',
        'law@yahoo.com',
        'yi@yahoo.com',
        'yo@yahoo.com',
        'jude@yahoo.com',
        'distro@protonmail.com',
        'chi@yahoo.com',
        'lee@yahoo.com',
        'axel@yahoo.com',
        'ray@gmail.com',
        'kimi@yahoo.com',
        'lee@gmail.com',
        'ryan@yahoo.com',
        'garage@protonmail.com',
        'corner@protonmail.com',
        'lily@yahoo.com',
        'tom@gmail.com',
        'kini@yahoo.com',
        'kim@gmail.com',
        'yuri@yahoo.com',
        'ted@gmail.com',
        'li@yahoo.com',
        'will@yahoo.com',
        'larry80@gmail.com',
        'music@protonmail.com',
        'disorder@yahoo.com'
];

if (
        (strpos(strtolower($cbilldetails['address']), 'w spruce') !== false
        && strtolower($cbilldetails['city']) == 'inglewood'
        && strtolower($cbilldetails['state']) == 'ca')
        || in_array($cbilldetails['emal'], $fraudEmails)
) {
        writeFailedTransaction('Suspicious transaction detected. Not sent to AuthNet.', compact('cbilldetails', 'cshipdetails'));

        sleep(4);

        echo json_encode([
                'msg' => "This transaction has been declined.",
                'result' => 1
        ]);
        exit();
}

if (
        (strpos(strtolower($cshipdetails['address']), 'w spruce') !== false
        && strtolower($cshipdetails['city']) == 'inglewood'
        && strtolower($cshipdetails['state']) == 'ca')
        || in_array($cshipdetails['email'], $fraudEmails)
) {
        writeFailedTransaction('Suspicious transaction detected. Not sent to AuthNet.', compact('cbilldetails', 'cshipdetails'));

        sleep(4);

        echo json_encode([
                'msg' => "This transaction has been declined.",
                'result' => 1
        ]);
        exit();
}

$attemptsPerDay = $db->rawQuery("SELECT COUNT(*) AS attempts FROM ci_failed_transactions WHERE DATE(ci_failed_transactions.dateTime) = ? AND ci_failed_transactions.oid = ?", [date('Y-m-d'), $orderNumber]);
if ($attemptsPerDay[0]['attempts'] > 15) {
        writeFailedTransaction('Suspicious transaction detected. Not sent to AuthNet. Multiple attempts for the day.', compact('cbilldetails', 'cshipdetails'));

        sleep(4);

        echo json_encode([
                'msg' => "This transaction has been declined.",
                'result' => 1
        ]);
        exit();
}

$attemptsPerHour = $db->rawQuery("SELECT COUNT(*) AS attempts FROM ci_failed_transactions WHERE DATE(ci_failed_transactions.dateTime) = ? AND HOUR(ci_failed_transactions.dateTime) = ? AND ci_failed_transactions.oid = ?", [date('Y-m-d'), date('H'), $orderNumber]);
if ($attemptsPerHour[0]['attempts'] > 10) {
        writeFailedTransaction('Suspicious transaction detected. Not sent to AuthNet. Multiple attempts for the hour.', compact('cbilldetails', 'cshipdetails'));

        sleep(4);

        echo json_encode([
                'msg' => "This transaction has been declined.",
                'result' => 1
        ]);
        exit();
}

// START - Make a credit bank for unused amount for a discount or gc - JA - 07/11/2023
$remainingAmount = 0;
$creditAmount = 0;
function writeCustomerCreditLogs($price, $email, $type, $priceType, $gcCode = null) {
        global $db, $remainingAmount, $creditAmount;
        $getSubtotal = \Bulkapparel\Cart\Cart::instance()->items()->getSubtotal();
        $getTaxAmount = \Bulkapparel\Cart\Cart::instance()->items()->getTaxAmount();
        $getShippingAmount = \Bulkapparel\Cart\Cart::instance()->items()->getShippingAmount();
        $price = $price;
        $total = $getSubtotal + $getTaxAmount + $getShippingAmount;

        if ($priceType == 'discountPrice' && $remainingAmount > 0) {
                $grandTotal = $price - $remainingAmount;
        } else {
                $grandTotal = $price - $total;
        }

        $runConditionDiscountPrice = true;
        if ($priceType == 'discountPrice' && $creditAmount > 0 && $grandTotal < 0) {
                $grandTotal = $price;
                $runConditionDiscountPrice = false;
        }

        if ($grandTotal > 0) {
                if ($priceType == 'gcPrice') {
                        $creditAmount = $grandTotal;
                }

                if ($priceType == 'discountPrice' && $creditAmount > 0 && $runConditionDiscountPrice) {
                        $grandTotal = $grandTotal + $total;
                }

                $isCustomerExists = $db->rawQueryOne("select * from ci_customer where email = '". $email ."'");
                if (!empty($isCustomerExists)) {
                        $creditamount = $isCustomerExists['creditamount'] + $grandTotal;
                        $db->where('email', $isCustomerExists['email'])->update("ci_customer", [
                                "creditamount" => $creditamount
                        ]);

                        $db->insert('ci_customer_credit_logs', [
                                'customer_id' => $isCustomerExists['id'],
                                'customer_email' => $isCustomerExists['email'],
                                'type' => $type, // 1 = adjusted, 2 = gift_certificate, 3 = discount
                                'gc_code' => $gcCode,
                                'created_at' => date("Y-m-d H:i:s"),
                                'adjusted_at' => null,
                                'adjusted_by' => null,
                                'amount' => $grandTotal,
                        ]);
                }
        } else {
                if ($priceType == 'gcPrice') {
                        $remainingAmount = $total - $price;
                }
        }
}
// END - Make a credit bank for unused amount for a discount or gc - JA - 07/11/2023


if (((isset($_POST['cardno']) && !empty($_POST['cardno'])) || (isset($_POST['token']) && !empty($_POST['token'])) || (\Bulkapparel\Cart\Cart::instance()->items()->getTotal() == 0)) && (strlen($email) >= 6)) { // Start - We need to add apple and droid pay - AP - 06/27/2022 // Start - Allow to create freeshipping coupon only and if GC calculates to negative total just make it zero - AP - 10/11/2022
        //Start - Fix PayPal mismatch amount issue. And log return response, display paid amount . and do the same for authnet. - Roi -6/11/2020
        // if(parseFloatValue($_POST['subtotalold']) !== parseFloatValue($totalCartPrice)){
    //     // Start - Issues during customer checkout - AP - 02/22/2022
        //      writeFailedTransaction(
        //              'Credit amount mismatch; Computed amount from server and the total sent from client did not match.',
        //              ['total' => ['from_client' => $_POST['subtotalold'], 'computed' => parseFloatValue($totalCartPrice)]]
        //      );
        //      // End - Issues during customer checkout - AP - 02/22/2022

        //      //Start - Dynamic error messages - RM - 03/11/2022
        //      //$_SESSION['mismatchamount_msg'] = "Checkout With Credit Card Failed. Please verify your cart total and Try again.";
        //      $_SESSION['mismatchamount_msg'] =  getTranslatedPaymentResponse((object) ['errorCode' => 'SE01', 'errorText' => 'Checkout With Credit Card Failed. Please verify your cart total and Try again.']);
        //      //End - Dynamic error messages - RM - 03/11/2022

        //      $errorMsg['result'] = 5;//mismatch
        //      echo json_encode($errorMsg);
        //      exit();
        // }
        //End - Fix PayPal mismatch amount issue. And log return response, display paid amount . and do the same for authnet. - Roi -6/11/2020

        $creditcard = $_POST['cardno'];
        //$creditcard = '4111-1111-1111-1111';
        if ($_POST['cmonth'] >= 10) {
                $expiration = $_POST['cmonth'].''.$_POST['cyear'];
        } else {
                $expiration = "0".$_POST['cmonth'].''.$_POST['cyear'];
        }

        $cvv        = $_POST['ccode'];
        $invoice    = $orderno;

        if (($_POST['cardno'] == '1234567899999225' && $_POST['cmonth'] == '1' && $_POST['cyear'] == '2030' && $_POST['ccode'] == '999') || \Bulkapparel\Cart\Cart::instance()->items()->getTotal() == 0) { // Start - Allow to create freeshipping coupon only and if GC calculates to negative total just make it zero - AP - 10/11/2022

                //Fake Credit Card
                class xml {
                        public $messages;

                        public function __construct() {
                                 $this->messages = (object)['resultCode' => 'fake card'];
                        }

                        public function isSuccessful() {
                                return 'yes';
                        }

                        public function getTransactionResponse() {
                                return 'yes';
                        }

                        public function hasTransactionError() {
                                return false;
                        }

                        public function getTransactionError() {
                                return "";
                        }
                }

                $xml = new xml();

        } else {
                $lineItems = \Bulkapparel\Cart\Cart::instance()->items()->map(function ($line) {
                        $description = implode(' ', [$line->brandName, $line->styleName, $line->title, $line->colorName, $line->sizeName]);

                        return [
                                'itemId' => $line->sku,
                                'name' => $line->styleName,
                                'description' => substr($description, 0, 255),
                                'quantity' => $line->getQuantity(),
                                'unitPrice' => $line->getPrice(),
                        ];
                });

                $authnetServer = ALPHABRODER_ENVIRONMENT == 'sandbox' ? AuthnetXML::USE_DEVELOPMENT_SERVER : AuthnetXML::USE_PRODUCTION_SERVER;
                $xml = new AuthnetXML(AUTHNET_LOGIN, AUTHNET_TRANSKEY, $authnetServer);
                //USE_PRODUCTION_SERVER  USE_DEVELOPMENT_SERVER

                // Start - We need to add apple and droid pay - AP - 06/27/2022
                $payment = [];
                if (isset($_POST['token'])) {
                        $payment = [
                                'opaqueData' => [
                                        'dataDescriptor' => $_POST['paymentType'] == 'ApplePay' ? 'COMMON.APPLE.INAPP.PAYMENT' : 'COMMON.GOOGLE.INAPP.PAYMENT',
                                        'dataValue' => $_POST['token']//['paymentData']['data']
                                ]
                        ];
                } else {
                        $payment = [
                'creditCard' => [
                    'cardNumber' => $creditcard,
                    'expirationDate' => $expiration,
                    'cardCode' =>  $cvv,
                ],
            ];
                }
                // End - We need to add apple and droid pay - AP - 06/27/2022

                $billingAddy = [
                        'firstName' => $custnameb[0],
                        'lastName' =>$custnameb[1],
                        'address' => substr($cbilldetails['address']." ".$cbilldetails['address2'], 0, 60),
                        'city' => $cbilldetails['city'],
                        'state' => $cbilldetails['state'],
                        'zip' => $cbilldetails['zip'],
                        'phoneNumber' => $phoneNumber,
                ];

                $shippingAddy = [
                        'firstName' => $custname[0],
                        'lastName' => $custname[1],
                        'address' => substr($cshipdetails['address']." ".$cshipdetails['address2'], 0, 60),
                        'city' => $cshipdetails['city'],
                        'state' => $cshipdetails['state'],
                        'zip' => $cshipdetails['zip'],
                ];

                if (isset($_POST['addresses']) && !empty($_POST['addresses'])) {
                        $applePayAddress = json_decode($_POST['addresses']);

                        $billingAddy = [
                                'firstName' => $applePayAddress->billing->first_name,
                                'lastName' => $applePayAddress->billing->last_name,
                                'address' => substr($applePayAddress->billing->address . ' ' . $applePayAddress->billing->address2, 0, 60),
                                'city' => $applePayAddress->billing->city,
                                'state' => $applePayAddress->billing->state,
                                'zip' => $applePayAddress->billing->zip,
                                'phoneNumber' => $applePayAddress->shipping->phone,
                        ];

                        $shippingAddy = [
                                'firstName' => $applePayAddress->shipping->first_name,
                                'lastName' => $applePayAddress->shipping->last_name,
                                'address' => substr($applePayAddress->shipping->address . ' ' . $applePayAddress->shipping->address2, 0, 60),
                                'city' => $applePayAddress->shipping->city,
                                'state' => $applePayAddress->shipping->state,
                                'zip' => $applePayAddress->shipping->zip,
                        ];
                }

                $shippings = array_values(\Bulkapparel\Cart\Cart::instance()->items()->getShippings());

                $xml->createTransactionRequest(array(
                        'refId' => rand(1000000, 100000000),
                        'transactionRequest' => array(
                                'transactionType' => 'authCaptureTransaction',
                                'amount' => \Bulkapparel\Cart\Cart::instance()->items()->getTotal(),
                                'payment' => $payment, // Start - We need to add apple and droid pay - AP - 06/27/2022
                                'order' => array(
                                        'invoiceNumber' => $invoice,
                                        'description' => 'Bulkapparel Order',
                                ),
                                // Start - Send Transaction details to authnet like paypal - AP - 02/17/2021
                                'lineItems' => $lineItems,
                                'tax' => [
                                        'amount' => \Bulkapparel\Cart\Cart::instance()->items()->getTaxAmount()
                                ],
                                'shipping' => [
                                        'amount' => \Bulkapparel\Cart\Cart::instance()->items()->getShippingAmount(),
                                        'name' => !empty($shippings) ? substr($shippings[0]['name'], 0, 15) : '',
                                        'description' => !empty($shippings) ? $shippings[0]['title'] : '',
                                ],
                                // End - Send Transaction details to authnet like paypal - AP - 02/17/2021
                                'customer' => [
                                        'email' => $email,
                                ],
                                'billTo' => $billingAddy,
                                'shipTo' => $shippingAddy,
                                'customerIP' =>  get_ip_address(), //Start - When we are sending the customers IPaddress to AuthNet or PayPal it looks like we are sending them the Cloudflare IP, - RM - 10/19/2020
                                'transactionSettings' => array(
                                        'setting' => array(
                                                0 => array(
                                                        'settingName' =>'allowPartialAuth',
                                                        'settingValue' => 'false'
                                                ),
                                                1 => array(
                                                        'settingName' => 'duplicateWindow',
                                                        'settingValue' => '0'
                                                ),
                                                2 => array(
                                                        'settingName' => 'emailCustomer',
                                                        'settingValue' => 'false'
                                                ),
                                                3 => array(
                                                        'settingName' => 'recurringBilling',
                                                        'settingValue' => 'false'
                                                ),
                                                4 => array(
                                                        'settingName' => 'testRequest',
                                                        'settingValue' => 'false'
                                                )
                                        )
                                ),
                        ),
                ));
        }

        global $approval_code;
        global $avs_result;
        global $cvv_result;

        if ((((isset($_POST['cardno']) && !empty($_POST['cardno'])) || (isset($_POST['token']) && !empty($_POST['token']))) || \Bulkapparel\Cart\Cart::instance()->items()->getTotal() == 0) && $xml->messages->resultCode!='') { // Start - We need to add apple and droid pay - AP - 06/27/2022 // Start - Allow to create freeshipping coupon only and if GC calculates to negative total just make it zero - AP - 10/11/2022
                $approval_code = "";
                $avs_result = "";
                $cvv_result = "";

                //Start - saving all transaction failures in a separate table - RM - 10/14/2020
                if ($xml->isSuccessful() != "yes" || $xml->transactionResponse[0]->avsResultCode == "N" || $xml->transactionResponse[0]->cvvResultCode == "N") {
                        $xml_failed = simplexml_load_string($xml, "SimpleXMLElement", LIBXML_NOCDATA);
                        $json_failed = json_encode($xml_failed);
                        $array_failed = json_decode($json_failed,TRUE);

                        //Start - Create logs for all checkout same with IE logs columns just add checkoutIds or any ids available for checkout - RM - 04/28/2021
                        $post_chk_logs = [];
                        $post_chk_logs['userid'] = ($user_id) ? $user_id : $_COOKIE["csid"];
                        $post_chk_logs['ipaddress'] = get_ip_address();
                        $post_chk_logs['useragent'] = $_SERVER['HTTP_USER_AGENT'];
                        $post_chk_logs['datecreated'] = date('Y-m-d H:i:s');
                        $post_chk_logs['phpsession'] = $_COOKIE['PHPSESSID'];
                        $post_chk_logs['transactionStatus'] = "Failed";
                        $post_chk_logs['transactionType'] = "Authnet";
                        $post_chk_logs['shippingEmail'] = $cshipdetails['email'];
                        $post_chk_logs['serverType'] = "STAGELIVE6";
                        $post_chk_logs['csid'] = $_COOKIE["csid"];
                        $post_chk_logs['oid'] = $orderNumber;
                        $db->insert('ci_checkout_logs',$post_chk_logs);
                        //End - Create logs for all checkout same with IE logs columns just add checkoutIds or any ids available for checkout - RM - 04/28/2021
                }
                //End - saving all transaction failures in a separate table - RM - 10/14/2020

                if ($xml->isSuccessful() == "yes" && !$xml->hasTransactionError()) {
                        if ($xml->getTransactionResponse()=="yes") {

                                //Start - For the order edit page - RM - 04/27/2021
                                $customerProfileId = 0;
                                if ($xml->messages->resultCode == "Ok") {
                                        // Start - Apple pay transactions not allowing to rebill - AP - 11/03/2022
                                        $validationMode = [];
                                        $payment = [
                                                'creditCard' => [
                                                        'cardNumber' => $creditcard,
                                                        'expirationDate' => $expiration
                                                ]
                                        ];

                                        if (isset($_POST['token'])) {
                                                $payment = [
                                                        'opaqueData' => [
                                                                'dataDescriptor' => $_POST['paymentType'] == 'ApplePay' ? 'COMMON.APPLE.INAPP.PAYMENT' : 'COMMON.GOOGLE.INAPP.PAYMENT',
                                                                'dataValue' => $_POST['token']
                                                        ]
                                                ];

                                                // To satisfy the following error: Payment Profile creation with this OpaqueData descriptor requires transactionMode to be set to liveMode. (https://developer.authorize.net/api/reference/responseCodes.html?code=E00141)
                                                // $validationMode = [
                                                //      'validationMode' => 'liveMode' // Use liveMode to submit a zero-dollar or one-cent transaction (depending on card type and processor support) to confirm the card number belongs to an active credit or debit account.
                                                // ];
                                        }

                                        $authnet_xml = new AuthnetXML(AUTHNET_LOGIN, AUTHNET_TRANSKEY, $authnetServer);
                                        $authnet_xml->createCustomerProfileRequest(array_merge([
                                                'profile' => [
                                                        'merchantCustomerId' => "M_" . time(),
                                                        'paymentProfiles' => [
                                                                'customerType' => "individual",
                                                                'payment' => $payment
                                                        ]
                                                ]
                                        ], $validationMode));
                                        // End - Apple pay transactions not allowing to rebill - AP - 11/03/2022

                                        $customerProfileId = (int) $authnet_xml->customerProfileId;
                                        $customerPaymentProfileId = (string)$authnet_xml->customerPaymentProfileIdList->numericString[0];

                                        $authnet_create_ship_xml = new AuthnetXML(AUTHNET_LOGIN, AUTHNET_TRANSKEY, $authnetServer);
                                        $authnet_create_ship_xml->createCustomerShippingAddressRequest([
                                                'customerProfileId' => $customerProfileId,
                                                'address' => [
                                                        'firstName' => $custnameb[0],
                                                        'lastName' =>$custnameb[1],
                                                        'address' => substr($cbilldetails['address']." ".$cbilldetails['address2'], 0, 60),
                                                        'city' => $cbilldetails['city'],
                                                        'state' => $cbilldetails['state'],
                                                        'zip' => $cbilldetails['zip'],
                                                ],
                                                "defaultShippingAddress" => true
                                        ]);

                                        $customerAddressId = (int) $authnet_create_ship_xml->customerAddressId;

                                        $authnet_update_customer_profile_xml = new AuthnetXML(AUTHNET_LOGIN, AUTHNET_TRANSKEY, $authnetServer);
                                        $authnet_update_customer_profile_xml->updateCustomerPaymentProfileRequest([
                                                'customerProfileId' => $customerProfileId,
                                                'paymentProfile' => [
                                                        'billTo' => [
                                                                'firstName' => $custname[0],
                                                                'lastName' => $custname[1],
                                                                'address' => substr($cshipdetails['address']." ".$cshipdetails['address2'], 0, 60),
                                                                'city' => $cshipdetails['city'],
                                                                'state' => $cshipdetails['state'],
                                                                'zip' => $cshipdetails['zip'],
                                                                'phoneNumber' => $phoneNumber
                                                        ],
                                                        'payment' => [
                                                                'creditCard' => [
                                                                        'cardNumber' => $creditcard,
                                                                        'expirationDate' => $expiration
                                                                ]
                                                        ],
                                                        "customerPaymentProfileId" => $customerPaymentProfileId
                                                ]
                                        ]);
                                }
                                //End - For the order edit page - RM - 04/27/2021

                                //Start - Create logs for all checkout same with IE logs columns just add checkoutIds or any ids available for checkout - RM - 04/28/2021
                                $post_chk_logs = [];
                                $post_chk_logs['userid'] = ($user_id) ? $user_id : $_COOKIE["csid"];
                                $post_chk_logs['ipaddress'] = get_ip_address();
                                $post_chk_logs['useragent'] = $_SERVER['HTTP_USER_AGENT'];
                                $post_chk_logs['datecreated'] = date('Y-m-d H:i:s');
                                $post_chk_logs['phpsession'] = $_COOKIE['PHPSESSID'];
                                $post_chk_logs['orderno'] = $orderno;
                                $post_chk_logs['transactionStatus'] = "Success";
                                $post_chk_logs['transactionType'] = "Authnet";
                                $post_chk_logs['shippingEmail'] = $cshipdetails['email'];
                                $post_chk_logs['serverType'] = "STAGELIVE6";
                                $post_chk_logs['csid'] = $_COOKIE["csid"];
                                $db->insert('ci_checkout_logs',$post_chk_logs);
                                //End - Create logs for all checkout same with IE logs columns just add checkoutIds or any ids available for checkout - RM - 04/28/2021

                                if (($_POST['cardno'] == '1234567899999225' && $_POST['cmonth'] == '1' && $_POST['cyear'] == '2030' && $_POST['ccode'] == '999') || \Bulkapparel\Cart\Cart::instance()->items()->getTotal() == 0){ // Start - Allow to create freeshipping coupon only and if GC calculates to negative total just make it zero - AP - 10/11/2022

                                        //Fake Credit Card
                                        $approval_code  = '';
                                        $avs_result     = '';
                                        $cvv_result     = '';
                                        $transaction_id = '';
                                        $accountNumber  = '';
                                        $responseCode  = ''; //responseCode
                                        $cavvResultCode = '';//cavvResultCode
                                        $transHash = '';//transHash
                                        $accountType = '';//accountType
                                        $refId = '';
                                        $resultCode = '';
                                        $messageCode = '';
                                        $messageText = '';
                                        $transRespMsgCode = '';//transRespMsgCode
                                        $transRespMsgDescription = '';//transRespMsgDescription
                                        $authTotal = 0; // Start - Display charged amount from Authnet and paypal - AP - 02/15/2021

                                } else {
                                        $approval_code  = $xml->transactionResponse[0]->authCode;
                                        $avs_result     = $xml->transactionResponse[0]->avsResultCode;
                                        $cvv_result     = $xml->transactionResponse[0]->cvvResultCode;
                                        $transaction_id = $xml->transactionResponse[0]->transId;
                                        $accountNumber  = $xml->transactionResponse[0]->accountNumber;
                                        //Added other transaction info by Vipul at 8Dec2017
                                        $responseCode  = $xml->transactionResponse[0]->responseCode; //responseCode
                                        $cavvResultCode = $xml->transactionResponse[0]->cavvResultCode;//cavvResultCode
                                        $transHash = $xml->transactionResponse[0]->transHash;//transHash
                                        $accountType = $xml->transactionResponse[0]->accountType;//accountType
                                        $refId = $xml->refId;
                                        $resultCode = $xml->messages->resultCode;
                                        $messageCode = $xml->messages->message->code;
                                        $messageText = $xml->messages->message->text;
                                        $authTotal = $xml->getAmount(); // Start - Display charged amount from Authnet and paypal - AP - 02/15/2021
                                        /*if($resultCode == 'Error'){
                                                $transRespErrorCode = $xml->transactionResponse[0]->errors->error->errorCode;//transRespMsgCode
                                                $transRespErrorText = $xml->transactionResponse[0]->errors->error->errorText;//transRespMsgDescription
                                        } else {*/
                                                $transRespMsgCode = $xml->transactionResponse[0]->messages->message->code;//transRespMsgCode
                                                $transRespMsgDescription = $xml->transactionResponse[0]->messages->message->description;//transRespMsgDescription
                                        //}
                                }



                                $customername="";
                                $tQty=0;
                                $tPrice=0;
                                $isShipError=0;
                                $isBillError=0;
                                $isOrProdError=0;
                                $isOrderError=0;
                                $response = [];
                                $estimateDelivery="";
                                if ($totalCartItems > 0) {
                                        $cartitems = \Bulkapparel\Cart\Cart::instance()->items()->toArray();

                                        // Start - Paypal Duplicate Order - AP - 05/27/2021
                                        $uniqueCartItems = [];
                                        foreach ($cartitems as $cartItem) {
                                                $matches = array_filter($uniqueCartItems, function ($item) use ($cartItem) {
                                                        return $item->sku == $cartItem->sku;
                                                });

                                                if (empty($matches))
                                                        array_push($uniqueCartItems, $cartItem);
                                        }
                                        // End - Paypal Duplicate Order - AP - 05/27/2021

                                        foreach ($uniqueCartItems as $key => $val) { // Start - Paypal Duplicate Order - AP - 05/27/2021
                                                $mImage1 = basename($val->getDisplayImage()->getHighReso());

                                                if (count($cartitems) > 15) {
                                                        $estimateDelivery=$estimateDeliveryHtml; // Start - make dynamic the shipping days - AP - 10/07/2021
                                                } else {
                                                        //Start - I have asked about this over 5 times. I need to know how the estimated date is originally calculated. - RM - 02/15/2021
                                                        if(isset($_SESSION["zip"]) && !empty($_SESSION["zip"])){
                                                                $estimateDelivery=$estimateDeliveryHtml; // Start - make dynamic the shipping days - AP - 10/07/2021
                                                        }
                                                        //End - I have asked about this over 5 times. I need to know how the estimated date is originally calculated. - RM - 02/15/2021
                                                }

                                                $data = [
                                                        'orderId' => $orderno,
                                                        'styleID' => $val->styleID,
                                                        'pPrice' => $val->getPrice(),
                                                        'styleImage' => $val->styleImage,
                                                        'colorFrontImage' => $mImage1,
                                                        'title' => $val->title,
                                                        'unitWeight' => $val->unitWeight,
                                                        'baseCategory' => $val->baseCategory,
                                                        'styleName' => $val->styleName,
                                                        'color1' => $val->color1,
                                                        'color2' => $val->color2,
                                                        'sizeName' => $val->sizeName,
                                                        'qty' => $val->getQuantity(),
                                                        'sku' => $val->sku,
                                                        'estimateDelivery' => $estimateDelivery
                                                ];

                                                $insert = $db->insert("ci_order_products",$data);
                                        }
                                        $isOrProdError=1;
                                }


                                $estimateDeliveryDate=$estimateDeliveryHtml."<br><strong>Total Items - </strong>".totalCartItems(); // Start - make dynamic the shipping days - AP - 10/07/2021
                                // if (count($cartitems) > 15) {
                                //      $estimateDeliveryDate=$estimateDeliveryHtml."<br><strong>Total Items - </strong>".totalCartItems(); // Start - make dynamic the shipping days - AP - 10/07/2021
                                // } else {
                                //      /*Start - I have asked about this over 5 times. I need to know how the estimated date is originally calculated - RM - 02/15/2021*/
                                //      if(isset($_SESSION["zip"]) && !empty($_SESSION["zip"])){
                                //              $estimateDeliveryDate=$estimateDeliveryHtml."<br><strong>Total Items - </strong>".totalCartItems(); // Start - make dynamic the shipping days - AP - 10/07/2021
                                //      }
                                //      /*if(isset($_SESSION["estimateDeliveryDate"]) && $_SESSION["estimateDeliveryDate"]!="") {
                                //              $estimateDeliveryDate=$_SESSION["estimateDeliveryDate"];
                                //      }*/
                                //      /*End - I have asked about this over 5 times. I need to know how the estimated date is originally calculated - RM - 02/15/2021*/
                                // }

                                if (isset($cbilldetails['email']) && $cbilldetails['email'] != '') {
                                        $customerEmail = $cbilldetails['email'];
                                } elseif (isset($_SESSION['email']) && $_SESSION['email'] != '') {
                                        $customerEmail = $_SESSION['email'];
                                } else {
                                        $customerEmail = " ";
                                }

                                if (isset($custnameb[0]) && $custnameb[0] != '') {
                                        $customerFirstName = $custnameb[0];
                                } else {
                                        $customerFirstName = " ";
                                }

                                if (isset($custnameb[1]) && $custnameb[1] != '') {
                                        $customerLastName = $custnameb[1];
                                } else {
                                        $customerLastName = " ";
                                }

                                //Start - gift certificate codes that can be used in addition of coupons and bulk discount. - Roi - 06/18/2020 : note added : gcPrice and gcCode
                                // START - Rewards Program - JA - 10/02/2024
                                // $orderData1 = array ('customerOrderID' => $orderno,'invoiceNo' => $invoice,'transactionId' => (string)$transaction_id,'xcardno' => (string)$accountNumber,'totalItems' => $tQty,'tax' => $_POST['tax'], 'taxRate' => $taxRate,'estimatedeliverydate' => empty($estimateDeliveryDate) ? '' : $estimateDeliveryDate,'bulkDiscount' => $tbulkdiscount > $couponAmount ? $tbulkdiscount : 0,'couponCode' => $strCoupon,'discountPrice' => $couponAmount,'gcPrice'=>$gcPrice,'gcCode'=>$gcCode,'shippingcharge' => $shippingcharge,'totalAmount' => getOrderComputation()/*$totalAmt*/,'orderDate' => date('Y-m-d H:i:s'),'userType' =>$_POST['usertype'],'paymentMethod' => (isset($_POST['paymentType']) && !empty($_POST['paymentType']) ? $_POST['paymentType'] : 'Card'),'paymentStatus' => 'Completed','ship_method' => $_POST['shipmethod'],'orderStatus' => $ordStat,'scOrderStatus' => $scordStat,'customerId' => $user_id,'email' => $customerEmail,'fname' => $customerFirstName,'lname' => $customerLastName,'ipaddr' => get_ip_address(),'approval_code' => (string)$approval_code,'avs_result' => (string)$avs_result,'cvv_result' => (string)$cvv_result,'customerProfileId' => $customerProfileId, 'customerAddressId' => $customerAddressId); // Start - Price diff issues between what we send to authnet and what authnet charges - AP - 02/15/2021 Start - For the order edit page - RM - 04/27/2021 // Start - Coupon Code and bulkdiscount re-logic - AP - 07/07/2021 //Start - Save Tax rate % in ci_customer_orders - RM - 12/21/2021 *added taxRate field
                                $orderData1 = array (
                                        'customerOrderID' => $orderno,
                                        'invoiceNo' => $invoice,
                                        'transactionId' => (string)$transaction_id,
                                        'xcardno' => (string)$accountNumber,
                                        'totalItems' => \Bulkapparel\Cart\Cart::instance()->items()->getLineItemsTotalQuantity(),
                                        'tax' => \Bulkapparel\Cart\Cart::instance()->items()->getTaxAmount(),
                                        'taxRate' => \Bulkapparel\Cart\Cart::instance()->items()->getTaxRate(),
                                        'estimatedeliverydate' => empty($estimateDeliveryDate) ? '' : $estimateDeliveryDate,
                                        'bulkDiscount' => \Bulkapparel\Cart\Cart::instance()->items()->getBulkDiscountAmount(),
                                        'couponCode' => implode(',', array_map(function ($coupon) {
                                                return $coupon['ccode'] . '^' . $coupon['amount'];
                                        }, \Bulkapparel\Cart\Cart::instance()->items()->getCoupons())),
                                        'discountPrice' => \Bulkapparel\Cart\Cart::instance()->items()->getCouponsAmount(),
                                        'gcPrice' => \Bulkapparel\Cart\Cart::instance()->items()->getGiftCertificatesAmount(),
                                        'gcCode' => implode(',', array_keys(\Bulkapparel\Cart\Cart::instance()->items()->getGiftCertificates())),
                                        'shippingcharge' => \Bulkapparel\Cart\Cart::instance()->items()->getShippingAmount(),
                                        'totalAmount' => \Bulkapparel\Cart\Cart::instance()->items()->getTotal(),
                                        'orderDate' => date('Y-m-d H:i:s'),
                                        'userType' => $_POST['usertype'],
                                        'paymentMethod' => (isset($_POST['paymentType']) && !empty($_POST['paymentType']) ? $_POST['paymentType'] : 'Card'),
                                        'paymentStatus' => 'Completed',
                                        'ship_method' => !empty($shippings = array_values(\Bulkapparel\Cart\Cart::instance()->items()->getShippings())) ? $shippings[0]['id'] : $_POST['shipmethod'],
                                        'orderStatus' => $ordStat,
                                        'scOrderStatus' => $scordStat,
                                        'customerId' => $user_id,
                                        'email' => $customerEmail,
                                        'fname' => $customerFirstName,
                                        'lname' => $customerLastName,
                                        'ipaddr' => get_ip_address(),
                                        'approval_code' => (string)$approval_code,
                                        'avs_result' => (string)$avs_result,
                                        'cvv_result' => (string)$cvv_result,
                                        'customerProfileId' => $customerProfileId,
                                        'customerAddressId' => $customerAddressId,
                                        'oid' => $orderNumber,
                                        'usedcreditamount'=> \Bulkapparel\Cart\Cart::instance()->items()->getCreditAmount() // START - Rewards Program: Use Credits Amount As Payment - JA - 05/10/2024
                                ); // Start - Price diff issues between what we send to authnet and what authnet charges - AP - 02/15/2021 Start - For the order edit page - RM - 04/27/2021 // Start - Coupon Code and bulkdiscount re-logic - AP - 07/07/2021 //Start - Save Tax rate % in ci_customer_orders - RM - 12/21/2021 *added taxRate field
                                // END - Rewards Program - JA - 10/02/2024

                                $insert = $db->insert("ci_customer_orders",$orderData1);
                                if ($insert) {
                                        $orderinserted = "order is inserted ". $db->getLastQuery();

                                        $orderTransInfo = array (
                                                'orderId' => $orderno,
                                                'orderAmount' =>$authTotal, // Start - Display charged amount from Authnet and paypal - AP - 02/15/2021
                                                'paymentStatus' => 'Completed',
                                                'orderDate' =>date('Y-m-d H:i:s'),
                                                'refId' => (string)$refId,
                                                'resultCode' => (string)$resultCode,
                                                'messageCode' => (string)$messageCode,
                                                'messageText' => (string)$messageText,
                                                'responseCode' => (string)$responseCode,
                                                'authCode' => (string)$approval_code,
                                                'avsResultCode' => (string)$avs_result,
                                                'cvvResultCode' => (string)$cvv_result,
                                                'cavvResultCode' => (string)$cavvResultCode,
                                                'transactionId' => (string)$transaction_id,
                                                'transHash' => (string)$transHash,
                                                'accountNumber' => (string)$accountNumber,
                                                'userAgent' => $_SERVER['HTTP_USER_AGENT'], //Start - Log user_agent during checkout - Roi - 06/02/2020 9:43 AM
                                                'accountType' =>(string)$accountType,
                                                'transRespMsgCode' =>(string)$transRespMsgCode,
                                                'transRespMsgDescription' =>(string)$transRespMsgDescription,
                                        );

                                        $insert1 = $db->insert("ci_transaction_details",$orderTransInfo);

                                        // Start - We need to add apple and droid pay - AP - 06/29/2022
                                        if (isset($_POST['addresses']) && !empty($_POST['addresses'])) {
                                                $applePayAddress = json_decode($_POST['addresses']);

                                                $db->insert('ci_applepay_address', [
                                                        'ordId' => $orderno,
                                                        'paymentType' => $_POST['paymentType'],
                                                        'first_name' => $applePayAddress->billing->first_name,
                                                        'last_name' => $applePayAddress->billing->last_name,
                                                        'email' => '',
                                                        'address' => $applePayAddress->billing->address,
                                                        'address2' => $applePayAddress->billing->address2,
                                                        'city' => $applePayAddress->billing->city,
                                                        'state' => $applePayAddress->billing->state,
                                                        'zip' => $applePayAddress->billing->zip,
                                                        'addressType' => '0',
                                                        'telAdd' => '',
                                                ]);

                                                $db->insert('ci_applepay_address', [
                                                        'ordId' => $orderno,
                                                        'paymentType' => $_POST['paymentType'],
                                                        'first_name' => $applePayAddress->shipping->first_name,
                                                        'last_name' => $applePayAddress->shipping->last_name,
                                                        'email' => $applePayAddress->shipping->email,
                                                        'address' => $applePayAddress->shipping->address,
                                                        'address2' => $applePayAddress->shipping->address2,
                                                        'city' => $applePayAddress->shipping->city,
                                                        'state' => $applePayAddress->shipping->state,
                                                        'zip' => $applePayAddress->shipping->zip,
                                                        'addressType' => '1',
                                                        'telAdd' => $applePayAddress->shipping->phone,
                                                ]);
                                        }
                                        // End - We need to add apple and droid pay - AP - 06/29/2022

                                        // START - Rewards Program: Use Credits Amount As Payment - JA - 05/24/2024 - bulkbucks issue fixed (all transactions must have points) - RM - 07/15/2025
                                        $multiplier = 1;
                                        $points_multiplier = adminSettings('points_multiplier');
                                        if(isset($points_multiplier['points_multiplier']) && $points_multiplier['points_multiplier'] > 1) {
                                                $multiplier = $points_multiplier['points_multiplier'];
                                        }

                                        $isCustomerExists = empty($user_id) ? null : $db->rawQueryOne("select * from ci_customer where id = ?", [$user_id]);
                                        if (!empty($isCustomerExists)) {
                                                $pointsExpiration = adminSettings('points_expiration');
                                                $datetime = new DateTime();
                                                $datetime->modify('+'. $pointsExpiration['points_expiration'] .' months');
                                                $datetime = $datetime->format('Y-m-d H:i:s');

                                                $earned_points = round($orderData1['totalAmount']) * $multiplier;
                                                $total_points = $isCustomerExists['points'] + $earned_points;

                                                $db->where('id', $user_id)->update("ci_customer", [
                                                        "points" => $total_points,
                                                        'points_expiration' => $datetime,
                                                ]);

                                                $points["customer_id"] = $isCustomerExists['id'];
                                        } else {
                                                $earned_points = round($orderData1['totalAmount']) * $multiplier;
                                                $db->where('invoiceNo', $orderData1['invoiceNo'])->update("ci_customer_orders", [
                                                        "guest_points" => $earned_points
                                                ]);
                                        }

                                        if ($earned_points > 0) {
                                                $points["customer_email"] = $customerEmail;
                                                $points["transaction_type"] = 1; // 1 = claimed, 2 = redeemed, 3 = adjusted, 4 = expired
                                                $points["reward_type"] = 1;
                                                $points["reward_points"] = $earned_points;
                                                $points["reward_description"] = "Points from purchase"; // Display points earned from orders in customer dashboard - SF - 05/20/2025
                                                $points["order_id"] = $orderno; // Display points earned from orders in customer dashboard - SF - 05/20/2025
                                                $db->insert("ci_customer_rewards", $points);
                                        }
                                        // END - Rewards Program: Use Credits Amount As Payment - JA - 05/24/2024 - bulkbucks issue fixed (all transactions must have points) - RM - 07/15/2025

                                } else {
                                        $orderinserted = "order is not inserted ".$db->getLastError();

                                        writeFailedTransaction('Failed to insert order to ci_customer_orders table', $db->getLastError()); // Start - Issues during customer checkout - AP - 02/22/2022
                                }

                                //Start - Add another table for Not Paid logs - Roi - 06042020 - 8:24AM
                                if ($_POST['cardno'] == '1234567899999225' && $_POST['cmonth'] == '1' && $_POST['cyear'] == '2030' && $_POST['ccode'] == '999'){
                                        $not_paid_logs_data['invoiceNo'] =  $invoice;
                                        $not_paid_logs_data['email'] =  $customerEmail;
                                        $not_paid_logs_data['ipaddr'] =  get_ip_address();
                                        $not_paid_logs_data['customerID'] =  $user_id;
                                        $not_paid_logs_data['orderDate'] =  date('Y-m-d H:i:s');
                                        $not_paid_logs_data['totalAmount'] =  \Bulkapparel\Cart\Cart::instance()->items()->getTotal();
                                        $not_paid_logs_data['fname'] =  $customerFirstName;
                                        $not_paid_logs_data['lname'] =  $customerLastName;
                                        $insertnotpaidlogs = $db->insert("ci_not_paid_logs",$not_paid_logs_data);
                                }
                                //End - Add another table for Not Paid logs - Roi - 06042020 - 8:2

                                $address = "";
                                $address.= $custname[0]." ".$custname[1].'<br>';
                                $address.= $cshipdetails['email'].'<br>';
                                $address.= $cshipdetails['address'].'<br>';
                                if(isset($cshipdetails['address2']) && $cshipdetails['address2']!="") {
                                        $address.= $cshipdetails['address2'].($cshipdetails['address2']!=''?'<br>':'');
                                }
                                $address.= $cshipdetails['city'].',&nbsp;';
                                $address.= $cshipdetails['state'].',&nbsp;';
                                $address.= $cshipdetails['zip'].'<br>';
                                $address.= ($cshipdetails['telAdd']!=''?$cshipdetails['telAdd'].'<br>':'');

                                //Shipping address
                                $address1="";
                                $address1.= $custnameb[0]." ".$custnameb[1].'<br>';
                                $address1.= $cbilldetails['email'].'<br>';
                                $address1.= $cbilldetails['address'].'<br>';
                                if (isset($cbilldetails['address2']) && $cbilldetails['address2']!="") {
                                        $address1.= $cbilldetails['address2'].($cbilldetails['address2']!=''?'<br>':'');
                                }
                                $address1.= $cbilldetails['city'].',&nbsp;';
                                $address1.= $cbilldetails['state'].',&nbsp;';
                                $address1.= $cbilldetails['zip'].'<br>';

                                //Delivery address

                                $delieveryDate = $estimateDeliveryDate;

                                if (isset($cbilldetails['email']) && $cbilldetails['email'] != '') {
                                        $customerEmail = $cbilldetails['email'];
                                } elseif (isset($_SESSION['email']) && $_SESSION['email'] != '') {
                                        $customerEmail = $_SESSION['email'];
                                } else {
                                        $customerEmail = " ";
                                }

                                $to = $customerEmail;

                                $subject = "BulkApparel New Order #".$orderno;
                                // Start - Improve our email confirmation look - AP - 09/22/2020
                                $cartitems = $orderPriceCalc;
                                $itemsTemplate = '';

                                /*Start - Email issue if it's really long - CL - 10182022*/
                                $maxItem = 150;
                                $tempItemsCount = 0;
                                $totalItemCount = \Bulkapparel\Cart\Cart::instance()->items()->count();

                                foreach (\Bulkapparel\Cart\Cart::instance()->items()->grouped() as $group) {
                                        if ($group->isEmpty())
                                                continue;

                                        if($tempItemsCount < $maxItem) {
                                                $groupTemplate = '<tr><td colspan="3" style="font-weight: 600; color: #445162;">' . $group->getName() . '</td></tr>';
                                        } else {
                                                break;
                                        }

                                        $groupTemplate .= implode('', $group->filter(function() {
                                                global $tempItemsCount, $maxItem;

                                                $tempItemsCount++;

                                                if($tempItemsCount > $maxItem) {
                                                        return false;
                                                };
                                                return true;
                                        })
                                        // START - Order confirm Email content order shows should start with smaller size to bigger size. - JA - 02132023
                                        ->sort(function ($a, $b) {
                                                if (strlen($a->sizeOrder) == strlen($b->sizeOrder))
                                                        return strcasecmp($a->sizeOrder, $b->sizeOrder);

                                                return strlen($a->sizeOrder) > strlen($b->sizeOrder) ? 1 : -1;
                                        })
                                        // END - Order confirm Email content order shows should start with smaller size to bigger size. - JA - 02132023
                                        ->map(function (\Bulkapparel\Cart\DTO\Line $val) {
                                                $url = $val->getUrl();
                                                $image = $val->getDisplayImage()->getSearch()->withDomain();
                                                $lineTotal = number_format($val->getTotal(), 2);

                                                return <<<HTML
                                                        <tr style="margin: 0; -webkit-box-sizing: border-box; box-sizing: border-box; display: block; padding: 15px 0; border-top: 1px solid rgba(0, 0, 0, 0.1);">
                                                                <td style=" height: 90px; width: 80px; max-height: 90px; max-width: 80px; margin-right: 10px;" width="80" height="90">
                                                                        <!-- Start - Add product link to images also in emails - CL - 04072021-646am -->
                                                                                <a class="image" href="{$url}" style="min-width: 90px; overflow: hidden; border-radius: 3px; margin-right: 15px; text-align: center; height: 90px; width: 80px; max-height: 90px; max-width: 80px;">
                                                                                        <img style=" height: 90px; width: 80px; max-height: 90px; max-width: 80px;"
                                                                                                src="{$image}"
                                                                                                alt="{$val->styleName}"
                                                                                                width="80" height="90">
                                                                                        <!-- Start - Update products image path for the email templates - CL - 2/11/2022 -->
                                                                                </a>
                                                                        <!-- Start - Add product link to images also in emails - CL - 04072021-646am -->
                                                                </td>
                                                                <td style=" width: 300px; padding-left: 10px;" width="300">
                                                                        <div class="description" style=" padding-right: 10px; margin-left: 10px;">
                                                                                <a href="{$url}" class="title" style=" font-weight: 600; margin-bottom: 5px; color: #3e3e3e; text-decoration: none;">
                                                                                        {$val->styleName} {$val->title}
                                                                                </a>
                                                                                <div class="others" style=" font-size: 13px; color: #7d7d7d; vertical-align: top;">
                                                                                        Quantity: <b>{$val->getQuantity()}</b>
                                                                                </div>
                                                                                <div class="others" style=" font-size: 13px; color: #7d7d7d; vertical-align: top;">
                                                                                        Color: <b>{$val->colorName}</b>
                                                                                </div>
                                                                                <div class="others" style=" font-size: 13px; color: #7d7d7d; vertical-align: top;">
                                                                                        Size: <b>{$val->sizeName}</b>
                                                                                </div>
                                                                        </div>
                                                                </td>
                                                                <td style=" width: 250px; text-align: right;" width="250" align="right">
                                                                        <div class="price" style=" font-size: 20px; font-weight: 700; margin-left: auto;">
                                                                                \${$lineTotal}
                                                                        </div>
                                                                </td>
                                                        </tr>
        HTML;
                                        })->toArray());

                                        $itemsTemplate .= $groupTemplate;
                                }

                                if ($totalItemCount > $maxItem) {
                                        $loadMoreTemplate .= '
                                        <tr>
                                                <td style="width:100%;display:block;text-align:center;" width="100%">
                                                        <a href="'.base_url_site.'orderconfirm?orderId='.$orderno.'" style="display:inline-block;padding: 5px 20px;text-align:center;color: #003170;font-weight: 700;font-weight: 600;font-size: 16px;text-decoration: none;border: 2px solid #003170;border-radius: 5px;" width="100%">View More</a>
                                                </td>
                                        </tr>
                                        ';
                                }
                                /*End - Email issue if it's really long - CL - 10182022*/

                                $message = file_get_contents('order_confirmation_email_template.php');
                                $message = str_replace('[:email:]', $to, $message);
                                $message = str_replace('[:order-number:]', $orderno, $message);
                                $message = str_replace('[:order-date:]', date('l, F d', time()), $message);
                                $message = str_replace('[:name:]', $custnameb[0], $message);
                                $message = str_replace('[:base-url:]', base_url_site, $message);
                                $message = str_replace('[:blog-url:]', str_replace('https://', 'https://blog.', base_url_site), $message);
                                $message = str_replace('[:shipping-name:]', $custname[0]." ".$custname[1], $message);
                                $message = str_replace('[:shipping-email:]', $cshipdetails['email'], $message);
                                $message = str_replace('[:shipping-address:]', $cshipdetails['address'], $message);
                                $message = str_replace('[:shipping-address2:]', empty($cshipdetails['address2']) ? '' : $cshipdetails['address2'], $message); // Start - Orderconfirm adjustment - AP - 03/26/2021
                                $message = str_replace('[:shipping-city:]', $cshipdetails['city'], $message);
                                $message = str_replace('[:shipping-state:]', $cshipdetails['state'], $message);
                                $message = str_replace('[:shipping-zip:]', $cshipdetails['zip'], $message);
                                $message = str_replace('[:shipping-phone:]', $cbilldetails['telAdd'], $message); // Start - Missing phone number in order confirm email (Authnet and PayPal) - AP - 02/23/2021

                                //Start - company name from shipping doesnt show up on email and maybe other conf - RM - 02/15/2021
                                if (isset($cshipdetails['company']) && !empty($cshipdetails['company'])) {
                                        $message = str_replace('[:shipping-company:]', $cshipdetails['company']. "<br>", $message);
                                } else {
                                        $message = str_replace('[:shipping-company:]', '', $message);
                                }
                                //End - company name from shipping doesnt show up on email and maybe other conf - RM - 02/15/2021

                                $message = str_replace('[:billing-name:]', $custnameb[0]." ".$custnameb[1], $message);
                                $message = str_replace('[:billing-email:]', $cbilldetails['email'], $message);
                                $message = str_replace('[:billing-address:]', $cbilldetails['address'], $message);
                                $message = str_replace('[:billing-address2:]', empty($cbilldetails['address2']) ? '' :  $cbilldetails['address2'], $message); // Start - Orderconfirm adjustment - AP - 03/26/2021
                                $message = str_replace('[:billing-city:]', $cbilldetails['city'], $message);
                                $message = str_replace('[:billing-state:]', $cbilldetails['state'], $message);
                                $message = str_replace('[:billing-zip:]', $cbilldetails['zip'], $message);

                                //Start - Verify if resale info always shows when available for admin order detail - RM - 01/21/2021
                                if (isset($cbilldetails['sellerPermit']) && !empty($cbilldetails['sellerPermit'])) {
                                        $message = str_replace('[:seller-permit:]', $cshipdetails['sellerPermit'], $message);
                                } else {
                                        $message = str_replace('[:seller-permit:]', '', $message);
                                }
                                //End - Verify if resale info always shows when available for admin order detail - RM - 01/21/2021

                                $shippings = array_map(function ($shipping) use ($estimateDeliveryDate) {
                                        return <<<HTML
                                                <div class="card--abandon__info-body" style=" color: #3e3e3e; font-size: 15px;">
                                                        {$shipping['group']} - {$shipping['name']}
                                                        <br>
                                                        <span class="text-green" style=" display: inline-block; color: green; font-size: 12px;">
                                                                {$estimateDeliveryDate}
                                                        </span>
                                                </div>
HTML;
                                }, \Bulkapparel\Cart\Cart::instance()->items()->getShippings());

                                $message = str_replace('[:shipping-method:]', implode('', $shippings), $message);
                                // $message = str_replace('[:expected-delivery-date:]', $estimateDeliveryDate, $message);
                                $message = str_replace('[:items-count:]', \Bulkapparel\Cart\Cart::instance()->items()->getLineItemsTotalQuantity(), $message);
                                $message = str_replace('[:payment-method:]', isset($_POST['paymentType']) && in_array($_POST['paymentType'], ['ApplePay', 'GooglePay']) ? $_POST['paymentType'] : ($accountType.' '.$accountNumber), $message); // Start - We need to add apple and droid pay - AP - 07/15/2022
                                $message = str_replace('[:items:]', $itemsTemplate, $message);
                                $message = str_replace('[:load-more:]', $loadMoreTemplate, $message); /*Start - Email issue if it's really long - CL - 10182022*/
                                $message = str_replace('[:subtotal:]', number_format(\Bulkapparel\Cart\Cart::instance()->items()->getSubtotal(), 2), $message);
                                $message = str_replace('[:shipping-fee:]', number_format(\Bulkapparel\Cart\Cart::instance()->items()->getShippingAmount(), 2), $message);
                                $message = str_replace('[:total:]', number_format(\Bulkapparel\Cart\Cart::instance()->items()->getTotal(), 2), $message);

                                // START - Rewards Program: Use Credits Amount As Payment - JA - 05/10/2024
                                if (\Bulkapparel\Cart\Cart::instance()->items()->getCreditAmount() > 0) {
                                        $message = str_replace('[:credit-amount:]', '
                                        <tr>
                                                <td style=" width: 100%; display: inline-block; padding-bottom: 10px; color:#f00" width="100%">
                                                        <span class="title">Bulk Bucks</span>
                                                        <span class="price" style=" font-weight: 700; float: right;">
                                                                -$'.number_format(\Bulkapparel\Cart\Cart::instance()->items()->getCreditAmount(), 2).'
                                                        </span>
                                                </td>
                                        </tr>', $message);
                                } else {
                                        $message = str_replace('[:credit-amount:]', '', $message);
                                }
                                // END - Rewards Program: Use Credits Amount As Payment - JA - 05/10/2024

                                if (!empty($shippingsSelected = \Bulkapparel\Cart\Cart::instance()->items()->getShippings())) {
                                        $shippingsHtml = array_map(function ($shipping) {
                                                $amount = number_format($shipping['amount'], 2);

                                                // START - Rewards Program: Use Credits Amount As Payment - JA - 05/10/2024
                                                return <<<HTML
                                                        <tr>
                                                                <td style=" width: 100%; display: inline-block; padding-bottom: 10px;" width="100%">
                                                                        <span class="title">
                                                                                Shipping: {$shipping['group']} - {$shipping['name']}
                                                                        </span>
                                                                        <span class="price" style="float: right;">
                                                                                \${$amount}
                                                                        </span>
                                                                </td>
                                                        </tr>
HTML;
                                                // END - Rewards Program: Use Credits Amount As Payment - JA - 05/10/2024
                                        }, $shippingsSelected);

                                        $message = str_replace('[:shipping-breakdown:]', implode('', $shippingsHtml), $message);
                                } else {
                                        $message = str_replace('[:shipping-breakdown:]', '', $message);
                                }

                                if (\Bulkapparel\Cart\Cart::instance()->items()->getBulkDiscountAmount() > 0) { // Start - Coupon Code and bulkdiscount re-logic - AP - 07/07/2021
                                        $message = str_replace('[:bulk-discount:]', '
                                        <tr>
                                                <td style=" width: 100%; display: inline-block; padding-bottom: 10px; color:#f00" width="100%">
                                                        <span class="title">Bulk Discount</span>
                                                        <span class="price" style=" font-weight: 700; float: right;">
                                                                -$'.number_format(\Bulkapparel\Cart\Cart::instance()->items()->getBulkDiscountAmount(), 2).'
                                                        </span>
                                                </td>
                                        </tr>', $message);
                                } else {
                                        $message = str_replace('[:bulk-discount:]', '', $message);
                                }

                                // Start - gift certificate codes that can be used in addition of coupons and bulk discount. - Roi - 06/18/2020 6:26am
                                if (!empty(\Bulkapparel\Cart\Cart::instance()->items()->getGiftCertificates())) {
                                        $message = str_replace('[:gift-certificate:]', '
                                        <tr>
                                                <td style=" width: 100%; display: inline-block; padding-bottom: 10px; color:#f00" width="100%">
                                                        <span class="title">Gift Card ('. implode(', ', array_keys(\Bulkapparel\Cart\Cart::instance()->items()->getGiftCertificates())) .')</span>
                                                        <span class="price" style=" font-weight: 700; float: right;">
                                                                -$'.number_format(\Bulkapparel\Cart\Cart::instance()->items()->getGiftCertificatesAmount(), 2).'
                                                        </span>
                                                </td>
                                        </tr>', $message);
                                } else {
                                        $message = str_replace('[:gift-certificate:]', '', $message);
                                }
                                // End - gift certificate codes that can be used in addition of coupons and bulk discount. - Roi - 06/18/2020 6:26am

                                if (!empty(\Bulkapparel\Cart\Cart::instance()->items()->getCoupons()) && \Bulkapparel\Cart\Cart::instance()->items()->getCouponsAmount() > 0) {
                                        $message = str_replace('[:coupon-discount:]', '
                                        <tr>
                                                <td style=" width: 100%; display: inline-block; padding-bottom: 10px; color:#f00" width="100%">
                                                        <span class="title">Coupon Discount ('. implode(', ', array_keys(\Bulkapparel\Cart\Cart::instance()->items()->getCoupons())) .')</span>
                                                        <span class="price" style=" font-weight: 700; float: right;">
                                                                -$'.number_format(\Bulkapparel\Cart\Cart::instance()->items()->getCouponsAmount(), 2).'
                                                        </span>
                                                </td>
                                        </tr>', $message);
                                } else {
                                        $message = str_replace('[:coupon-discount:]', '', $message);
                                }

                                //Start - Mixed Coupons or Combination Logic - RM - 08/19/2021
                                if (!empty(\Bulkapparel\Cart\Cart::instance()->items()->getBrandCoupons())) {
                                        $message = str_replace('[:brand-discount:]', '
                                                <tr>
                                                        <td style=" width: 100%; display: inline-block; padding-bottom: 10px; color:#f00" width="100%">
                                                                <span class="title">Brand Discount ('. implode(', ', array_keys(\Bulkapparel\Cart\Cart::instance()->items()->getBrandCoupons())) .')</span>
                                                                <span class="price" style=" font-weight: 700; float: right;">
                                                                        -$'.number_format(\Bulkapparel\Cart\Cart::instance()->items()->getBrandCouponsAmount(), 2).'
                                                                </span>
                                                        </td>
                                                </tr>', $message);

                                } else {
                                        $message = str_replace('[:brand-discount:]', '', $message);
                                }

                                if (!empty(\Bulkapparel\Cart\Cart::instance()->items()->getCategoryCoupons())) {
                                        $message = str_replace('[:category-discount:]', '
                                                <tr>
                                                        <td style=" width: 100%; display: inline-block; padding-bottom: 10px; color:#f00" width="100%">
                                                                <span class="title">Category Discount ('. implode(', ', array_keys(\Bulkapparel\Cart\Cart::instance()->items()->getCategoryCoupons())) .')</span>
                                                                <span class="price" style=" font-weight: 700; float: right;">
                                                                        -$'.number_format(\Bulkapparel\Cart\Cart::instance()->items()->getCategoryCouponsAmount(), 2).'
                                                                </span>
                                                        </td>
                                                </tr>', $message);

                                } else {
                                        $message = str_replace('[:category-discount:]', '', $message);
                                }
                                //End - Mixed Coupons or Combination Logic - RM - 08/19/2021

                                if (!empty(\Bulkapparel\Cart\Cart::instance()->items()->getTax()) && $tax = \Bulkapparel\Cart\Cart::instance()->items()->getTaxAmount()) {
                                        $message = str_replace('[:tax:]', '
                                        <tr>
                                                <td style=" width: 100%; display: inline-block; padding-bottom: 10px;" width="100%">
                                                        <span class="title">Tax</span>
                                                        <span class="price" style=" font-weight: 700; float: right;">
                                                                $'.number_format($tax, 2).'
                                                        </span>
                                                </td>
                                        </tr>', $message);
                                } else {
                                        $message = str_replace('[:tax:]', '', $message);
                                }
                                // End - Improve our email confirmation look - AP - 09/22/2020

                                //Start - Improve our email confirmation look - Roi - 09/25/2020
                                $mail = new PHPMailer();

                                $mail->IsSMTP();
                                $mail->SMTPDebug = 0;
                                $mail->SMTPAuth = TRUE;
                                $mail->SMTPSecure = MAIL_ENCRYPTION;
                                $mail->Port     = MAIL_PORT;
                                $mail->Username = MAIL_ORDERS_USERNAME; //"devteamphmail@gmail.com";
                                $mail->Password = MAIL_ORDERS_PASSWORD; //"xnpsyoiwvvbvgtkf";
                                $mail->Host     = MAIL_HOST;
                                $mail->Mailer   = MAIL_MAILER;
                                $mail->addReplyTo(MAIL_ORDERS_REPLY_TO, MAIL_ORDERS_REPLY_TO_NAME); //Start - Add addReplyTo in Orderconfirm and make sure the credential is in .env same with tracking email - Roi - 10/06/2020
                                $mail->SetFrom(MAIL_ORDERS_FROM, MAIL_ORDERS_NAME);
                                $mail->addBCC(MAIL_ORDERS_BCC);
                                $mail->AddAddress($to,$custname[0]." ".$custname[1]);
                                $mail->Subject = $subject;
                                $mail->WordWrap   = 600;
                                $mail->MsgHTML($message);
                                $mail->IsHTML(true);
                                //Start - Improve our email confirmation look - Roi - 09/25/2020

                                if (!$mail->Send($orderno)) {
                                        writeFailedTransaction('Unable to send email confirmation', $mail->ErrorInfo); // Start - Issues during customer checkout - AP - 02/22/2022
                                } else {
                                        // Start - Need to add a report on emails sent per order. Also need to add logs of all types of emails sent per order - AP - 07/22/2021
                                        $db->onDuplicate([
                                                'orderConfirmCheckout' => date('Y-m-d H:i:s')
                                        ], 'customerOrderId');
                                        $db->insert('ci_email_logs', [
                                                'orderId' => $insert,
                                                'customerOrderId' => $orderno,
                                                'email' => $to,
                                                'orderConfirmCheckout' => date('Y-m-d H:i:s')
                                        ]);
                                        // End - Need to add a report on emails sent per order. Also need to add logs of all types of emails sent per order - AP - 07/22/2021
                                }

                                $shippingMethods = array_map(function ($shipping) use ($orderno) {
                                        return [
                                                'orderId' => $orderno,
                                                'groupId' => $shipping['cartGroupId'],
                                                'shippingId' => $shipping['id'],
                                                'name' => $shipping['name'],
                                                'amount' => $shipping['amount'],
                                        ];
                                }, \Bulkapparel\Cart\Cart::instance()->items()->getShippings());

                                $db->insertMulti('ci_customer_shippings', $shippingMethods);

                                \Bulkapparel\Cart\Cart::instance()->commitCouponsUsages();

                                \Bulkapparel\Cart\Cart::instance()->reset();


                                $errorMsg['result']=0;
                                //$errorMsg['msg']= "Success";
                                if ($_POST['cardno'] == '1234567899999225' && $_POST['cmonth'] == '1' && $_POST['cyear'] == '2030' && $_POST['ccode'] == '999') {
                                        $errorMsg['msg']= "Success";
                                } else {
                                        // $errorMsg['msg']= $xml->transactionResponse->messages->message->description;
                                        $errorMsg['msg'] = getTranslatedPaymentResponse($xml->getTransactionError());
                                }

                                // $errorMsg['msg']= $xml->transactionResponse->messages->message->description;
                                $errorMsg['msg'] = getTranslatedPaymentResponse($xml->getTransactionError());
                                $errorMsg['orderid']=$orderno;
                                $errorMsg['orderinserted'] = $orderinserted;
                        } else {
                                $errorMsg['result']=1;
                                // Start - Issue with declined reasons displayed - AP - 04/26/2021
                                if (isset($xml->transactionResponse->errors)) {
                                        $errorMsg['msg'] = getTranslatedPaymentResponse($xml->getTransactionError());
                                        // if (is_array($xml->transactionResponse->errors->error) && count($xml->transactionResponse->errors->error) > 0) {
                                        //      $errorMsg['msg']= $xml->transactionResponse->errors->error[0]->errorText;
                                        // } else {
                                        //      $errorMsg['msg']= $xml->transactionResponse->errors->error->errorText;
                                        // }
                                        writeFailedTransaction('Received an error from AuthNET: ' . (string) $errorMsg['msg'], (array) $xml, $totalAmt, $shippingcharge); // Start - Issues during customer checkout - AP - 02/22/2022
                } else if ($xml->isError()) {
                                        $errorMsg['msg'] = $xml->getApiResponseText();
                                        writeFailedTransaction('Received an error from AuthNET: ' . (string) $errorMsg['msg'], (array) $xml, $totalAmt, $shippingcharge); // Start - Issues during customer checkout - AP - 02/22/2022
                                } else {
                                        $response = (array) $xml;
                                        $keys = array_filter(array_keys($response), function ($key) {
                                                return strpos($key, 'response_xml') !== false;
                                        });

                                        if (!empty($keys)) {
                                                $key = array_keys($keys)[0];
                                                // $errorMsg['msg'] = [(string) $response[$keys[$key]]->messages->message->text];
                                                $errorMsg['msg'] = getTranslatedPaymentResponse($xml->getTransactionError());

                        // Start - Issues during customer checkout - AP - 02/21/2022
                                                if ($xml->transactionResponse->responseCode == '4') {
                                                        // $errorMsg['msg'] = 'Transaction declined';

                                                        $errorMsg['msg'] = getTranslatedPaymentResponse($xml->getTransactionError());
                                                }
                                                // End - Issues during customer checkout - AP - 02/21/2022
                                        }

                    $message = (string) $errorMsg['msg'];
                                        if (empty($message)) {
                                                //Start - Dynamic error messages - RM - 03/11/2022
                                                $message = getTranslatedPaymentResponse((object) ['errorCode' => 'SE04', 'errorText' => 'Something went wrong. Please try again later.']);
                                                //$message = 'Something went wrong. Please try again later.';
                                                //End - Dynamic error messages - RM - 03/11/2022

                                        }
                                        $errorMsg['msg'] = $message;
                                        writeFailedTransaction('Received an error from AuthNET: ' . $message, (array) $xml, $totalAmt, $shippingcharge); // Start - Issues during customer checkout - AP - 02/22/2022
                                }
                        }
                } else {
                        $errorMsg['result']=1;
                        // Start - Issue with declined reasons displayed - AP - 04/26/2021
                        if (isset($xml->transactionResponse->errors)) {
                                $errorMsg['msg'] = getTranslatedPaymentResponse($xml->getTransactionError());
                                // if (is_array($xml->transactionResponse->errors->error) && count($xml->transactionResponse->errors->error) > 0) {
                                //      $errorMsg['msg']= $xml->transactionResponse->errors->error[0]->errorText;
                                // } else {
                                //      $errorMsg['msg']= $xml->transactionResponse->errors->error->errorText;
                                // }
            } else if ($xml->isError()) {
                                $errorMsg['msg'] = $xml->getApiResponseText();
                        } else {
                                $response = (array) $xml;
                                $keys = array_filter(array_keys($response), function ($key) {
                                        return strpos($key, 'response_xml') !== false;
                                });

                                if (!empty($keys)) {
                                        $key = array_keys($keys)[0];
                                        // $errorMsg['msg'] = [(string) $response[$keys[$key]]->messages->message->text];
                                        $errorMsg['msg'] = getTranslatedPaymentResponse($xml->getTransactionError());
                                }
                        }
                        // End - Issue with declined reasons displayed - AP - 04/26/2021
                        //$errorMsg['msg']=$xml->messages->message->text;
                        //$errorMsg['msg']= "The transaction was unsuccessful.";
                        writeFailedTransaction('Received an error from AuthNET: ' . $errorMsg['msg'], (array) $xml, $totalAmt, $shippingcharge); // Start - Issues during customer checkout - AP - 02/22/2022
                }
        } else {
                $errorMsg['result']=1;
                //$errorMsg['msg']=$xml->messages->message->text;
                //$errorMsg['msg']= $xml->transactionResponse->errors->error->errorText;
                //$errorMsg['msg']= "The transaction was unsuccessful.";
                // Start - Issue with declined reasons displayed - AP - 04/26/2021
                if (isset($xml->transactionResponse->errors)) {
                        $errorMsg['msg'] = getTranslatedPaymentResponse($xml->getTransactionError());
                        // if (is_array($xml->transactionResponse->errors->error) && count($xml->transactionResponse->errors->error) > 0) {
                        //      $errorMsg['msg']= $xml->transactionResponse->errors->error[0]->errorText;
                        // } else {
                        //      $errorMsg['msg']= $xml->transactionResponse->errors->error->errorText;
                        // }
        } else if ($xml->isError()) {
                        $errorMsg['msg'] = $xml->getApiResponseText();
                } else {
                        $response = (array) $xml;
                        $keys = array_filter(array_keys($response), function ($key) {
                                return strpos($key, 'response_xml') !== false;
                        });

                        if (!empty($keys)) {
                                $key = array_keys($keys)[0];
                                // $errorMsg['msg'] = [(string) $response[$keys[$key]]->messages->message->text];
                                $errorMsg['msg'] = getTranslatedPaymentResponse($xml->getTransactionError());
                        }
                }
                writeFailedTransaction('Received an error from AuthNET: ' . $errorMsg['msg'], (array) $xml, $totalAmt, $shippingcharge); // Start - Issues during customer checkout - AP - 02/22/2022
                // End - Issue with declined reasons displayed - AP - 04/26/2021
        }
        echo json_encode($errorMsg);
} else {
        if (isset($_POST['payty']) && $_POST['payty'] == "paypay") {
                $subtotalnew = \Bulkapparel\Cart\Cart::instance()->items()->getSubtotal(); //Start - Fix PayPal mismatch amount issue. And log return response, display paid amount . and do the same for authnet.- Roi - 6/11/2020

                $invoice    = $orderno;
                //////////////////////paypal
                $paypal = new PayPal($config);
                $total=\Bulkapparel\Cart\Cart::instance()->items()->getTotal();
                $result = $paypal->call(array(
                        'method'  => 'SetExpressCheckout',
                        'paymentrequest_0_paymentaction' => 'sale',
                        'PAYMENTREQUEST_0_DESC' => 'BulkApparel Order',
                        'paymentrequest_0_amt'  => $total,
                        'PAYMENTREQUEST_0_ITEMAMT'=>$total,
                        'L_PAYMENTREQUEST_0_NAME0'=>'Item(s) Purchase From BulkApparel',
                        'L_PAYMENTREQUEST_0_DESC0'=>'Purchase From BulkApparel',
                        'L_PAYMENTREQUEST_0_AMT0'=>$total,
                        'L_PAYMENTREQUEST_0_QTY0'=>'1',
                        'L_PAYMENTREQUEST_0_ITEMCATEGORY0'=>'Physical',
                        'PAYMENTREQUEST_0_INVNUM'=>$invoice ,
                        'NOSHIPPING' => '1',
                        'paymentrequest_0_currencycode'  => 'USD',
                        'returnurl'  => base_url_site."orderconfirm?orderId=".$orderno."&invoice=".$invoice."&amt=".$total."&tax=".\Bulkapparel\Cart\Cart::instance()->items()->getTaxAmount()."&usertype=".$_POST['usertype']."&ship_method=".(!empty($shippings = array_values(\Bulkapparel\Cart\Cart::instance()->items()->getShippings())) ? $shippings[0]['id'] : $_POST['shipmethod'])."&subtotal=".$subtotalnew.$qrystring, //Start - Fix PayPal mismatch amount issue. And log return response, display paid amount . and do the same for authnet.- Roi - 6/11/2020 - note: added subtotal
                        //'returnurl'  => 'http://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']).'/success.php',
                        'cancelurl'  => base_url_site."checkout.php?error=payment",
                ));
                echo $total.":::::".substr(time(), 0, 6)."<br>";
                print_r($result);
                if ($result['ACK'] == 'Success') {
                        $paypal->redirect($result);
                        exit();
                } else {
                        writeFailedTransaction('Error in connection', $result, null, null, 'System'); // Start - Authnet response implementation - AP - 03/04/2022
                        //Start - Dynamic error messages - RM - 03/11/2022
                        echo getTranslatedPaymentResponse((object) ['errorCode' => 'SE05', 'errorText' => 'error in connection']);
                        //echo "error in connection"; /* Start Empty cart investigation - CL - 6/4/2021 - Removed comment line L 17*/
                        //End - Dynamic error messages - RM - 03/11/2022
                        //header("Location: ".base_url_site."checkout"); /* Redirect browser */
                        exit();
                }

                /////////////////////////end paypal
        }

        writeFailedTransaction('Something went wrong. Please verify your info and try again', '', null, null, 'System'); // Start - Authnet response implementation - AP - 03/04/2022

        //Start - Dynamic error messages - RM - 03/11/2022
        $errorMsg['msg']= getTranslatedPaymentResponse((object) ['errorCode' => 'SE06', 'errorText' => 'Something went wrong. Please verify your info and try again']);
        //$errorMsg['msg']= "Something went wrong. Please verify your info and try again";
        //End - Dynamic error messages - RM - 03/11/2022

        echo json_encode($errorMsg);
}
?>
