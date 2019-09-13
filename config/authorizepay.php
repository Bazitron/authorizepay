<?php

if (!defined('AUTHORIZENET_LOG_FILE')) {
    define('AUTHORIZENET_LOG_FILE', env('AUTHORIZENET_LOG_FILE'));
}

return [
    'MERCHANT_LOGIN_ID'        => env('MERCHANT_LOGIN_ID'),
    'MERCHANT_TRANSACTION_KEY' => env('MERCHANT_TRANSACTION_KEY'),
    'test'                     => 'works!',
];
