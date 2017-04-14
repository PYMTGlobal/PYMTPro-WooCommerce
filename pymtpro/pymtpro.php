<?php

if(!function_exists('curl_init')) {
    throw new Exception('The PYMTPro client library requires the CURL PHP extension.');
}

require_once(dirname(__FILE__) . '/core/Exception.php');
require_once(dirname(__FILE__) . '/core/ApiException.php');
require_once(dirname(__FILE__) . '/core/ConnectionException.php');
require_once(dirname(__FILE__) . '/core/PYMTPro.php');
require_once(dirname(__FILE__) . '/core/Requestor.php');
require_once(dirname(__FILE__) . '/core/Rpc.php');
require_once(dirname(__FILE__) . '/core/OAuth.php');
require_once(dirname(__FILE__) . '/core/TokensExpiredException.php');
require_once(dirname(__FILE__) . '/core/Authentication.php');
require_once(dirname(__FILE__) . '/core/SimpleApiKeyAuthentication.php');
require_once(dirname(__FILE__) . '/core/OAuthAuthentication.php');
require_once(dirname(__FILE__) . '/core/ApiKeyAuthentication.php');
