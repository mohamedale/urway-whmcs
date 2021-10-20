<?php

/**
 * WHMCS Sample Payment Gateway Module
 *
 * Payment Gateway modules allow you to integrate payment solutions with the
 * WHMCS platform.
 *
 * This sample file demonstrates how a payment gateway module for WHMCS should
 * be structured and all supported functionality it can contain.
 *
 * Within the module itself, all functions must be prefixed with the module
 * filename, followed by an underscore, and then the function name. For this
 * example file, the filename is "gatewaymodule" and therefore all functions
 * begin "urway_".
 *
 * If your module or third party API does not support a given function, you
 * should not define that function within your module. Only the _config
 * function is required.
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/payment-gateways/
 *
 * @copyright Copyright (c) WHMCS Limited 2017
 * @license http://www.whmcs.com/license/ WHMCS Eula
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Define module related meta data.
 *
 * Values returned here are used to determine module related capabilities and
 * settings.
 *
 * @see https://developers.whmcs.com/payment-gateways/meta-data-params/
 *
 * @return array
 */
function urway_MetaData() {
    return array(
        'DisplayName'                => 'UrWay',
        'APIVersion'                 => '2.0', // Use API Version 1.1
        'DisableLocalCredtCardInput' => true,
        'TokenisedStorage'           => false,
    );
}

/**
 * Define gateway configuration options.
 *
 * The fields you define here determine the configuration options that are
 * presented to administrator users when activating and configuring your
 * payment gateway module for use.
 *
 * Supported field types include:
 * * text
 * * password
 * * yesno
 * * dropdown
 * * radio
 * * textarea
 *
 * Examples of each field type and their possible configuration parameters are
 * provided in the sample function below.
 *
 * @see https://developers.whmcs.com/payment-gateways/configuration/
 *
 * @return array
 */
function urway_config()
{
    return array(
        // the friendly display name for a payment gateway should be
        // defined here for backwards compatibility
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'UrWay',
        ),
        // a text field type allows for single line text input
        'terminalId' => array(
            'FriendlyName' => 'Terminal ID',
            'Type' => 'text',
            'Default' => '',
            'Description' => 'Enter your Terminal Id here',
        ),
        // a password field type allows for masked text input
        'secretKey' => array(
            'FriendlyName' => 'Secret Key',
            'Type' => 'password',
            'Default' => '',
            'Description' => 'Enter secret key here',
        ),
        // a password field type allows for masked text input
        'password' => array(
            'FriendlyName' => 'password',
            'Type' => 'password',
            'Default' => '',
            'Description' => 'Enter password here',
        ),
        // the yesno field type displays a single checkbox option
        'testMode' => array(
            'FriendlyName' => 'Test Mode',
            'Type' => 'yesno',
            'Description' => 'Tick to enable test mode',
        ),

    );
}

/**
 * Payment link.
 *
 * Required by third party payment gateway modules only.
 *
 * Defines the HTML output displayed on an invoice. Typically consists of an
 * HTML form that will take the user to the payment gateway endpoint.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/third-party-gateway/
 *
 * @return string
 */
function urway_link($params)
{
    // Gateway Configuration Parameters
    $terminalId = $params['terminalId'];
    $secretKey = $params['secretKey'];
    $password = $params['password'];
    $testMode = $params['testMode'] === 'on';

    // Invoice Parameters
    $invoiceId = $params['invoiceid'];
    $amount = $params['amount'];
    $currencyCode = $params['currency'];

    // Client Parameters
    $country = $params['clientdetails']['country'];
    $email = $params['clientdetails']['email'];

    // System Parameters
    $systemUrl = $params['systemurl'];
    $langPayNow = $params['langpaynow'];
    $moduleName = $params['paymentmethod'];

    // Make a new transaction
    $txn_details = "$invoiceId|$terminalId|$password|$secretKey|$amount|$currencyCode";
    $hash = hash('sha256', $txn_details);
    if ($testMode) {
        $url = 'https://payments-dev.urway-tech.com/URWAYPGService/transaction/jsonProcess/JSONrequest';
    } else {
        $url = 'https://payments.urway-tech.com/URWAYPGService/transaction/jsonProcess/JSONrequest';
    }
    $postData = [
        'trackid' => $invoiceId,
        'terminalId' => $terminalId,
        'action' => '1',
        'customerEmail' => $email,
        'merchantIp' => $_SERVER['SERVER_ADDR'] ?? '127.0.0.1', // Because merchantIp is required
        'password' => $password,
        'currency' => $currencyCode,
        'amount' => $amount,
        'requestHash' => $hash,
        'country' => $country,
        'udf2' => $systemUrl . '/modules/gateways/callback/' . $moduleName . '.php'
    ];
    $postData = json_encode($postData);

    // initialization curl
    $curl = curl_init();
    // set curl options array
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_CUSTOMREQUEST => 'POST',
    ));
    // set curl option
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    // set response
    $response = curl_exec($curl);
    // set error
    $err = curl_error($curl);
    // close curl session
    curl_close($curl);
    // make response as a array
    $responseArr = json_decode($response, true);
    // build request url
    $requestUrl = $responseArr['targetUrl'] . '?paymentid=' . $responseArr['payid'];

    // returned Html
    $htmlOutput = '<form method="post" action="' . $requestUrl . '">';
    $htmlOutput .= '<input class="btn btn-success" type="submit" value="' . $langPayNow . '" />';
    $htmlOutput .= '</form>';

    return $htmlOutput;
}


/**
 * Refund transaction.
 *
 * Called when a refund is requested for a previously successful transaction.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/refunds/
 *
 * @return array Transaction response status
 */
function urway_refund($params)
{
    // Gateway Configuration Parameters
    $terminalId = $params['terminalId'];
    $secretKey = $params['secretKey'];
    $password = $params['password'];
    $testMode = $params['testMode'] === 'on';

    // Transaction Parameters
    $transactionIdToRefund = $params['transid'];
    $refundAmount = $params['amount'];
    $currencyCode = $params['currency'];

    // Invoice Parameters
    $invoiceId = $params['invoiceid'];

    // Client Parameters
    $country = $params['clientdetails']['country'];
    $email = $params['clientdetails']['email'];

    // perform API call to initiate refund and interpret result
    $txn_details = "$invoiceId|$terminalId|$password|$secretKey|$refundAmount|$currencyCode";
    $hash = hash('sha256', $txn_details);
    if ($testMode) {
        $url = 'https://payments-dev.urway-tech.com/URWAYPGService/transaction/jsonProcess/JSONrequest';
    } else {
        $url = 'https://payments.urway-tech.com/URWAYPGService/transaction/jsonProcess/JSONrequest';
    }
    $postData = [
        'transid' => $transactionIdToRefund,
        'trackid' => $invoiceId,
        'terminalId' => $terminalId,
        'action' => '2',
        'customerEmail' => $email,
        'merchantIp' => $_SERVER['SERVER_ADDR'] ?? '127.0.0.1', // Because merchantIp is required
        'password' => $password,
        'currency' => $currencyCode,
        'amount' => $refundAmount,
        'requestHash' => $hash,
        'country' => $country,
    ];
    $postData = json_encode($postData);

    // initialization curl
    $curl = curl_init();
    // set curl options array
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_CUSTOMREQUEST => 'POST',
    ));
    // set curl option
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    // set response
    $response = curl_exec($curl);
    // set error
    $err = curl_error($curl);
    // close curl session
    curl_close($curl);
    // make response as a array
    $responseArr = json_decode($response, true);

    return array(
        // 'success' if successful, otherwise 'declined', 'error' for failure
        'status' => $responseArr['result'] === 'Successful' ? 'success' : 'error',
        // Data to be recorded in the gateway log - can be a string or array
        'rawdata' => $responseArr,
        // Unique Transaction ID for the refund transaction
        'transid' => $responseArr['tranid'],
        // Optional fee amount for the fee value refunded
        'fee' => /*$feeAmount*/ 0.00,
    );
}