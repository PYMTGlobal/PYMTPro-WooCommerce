<?php

class PYMTPro_Rpc
{
    private $_requestor;
    private $authentication;

    public function __construct($requestor, $authentication)
    {
        $this->_requestor = $requestor;
        $this->_authentication = $authentication;
    }

    public function request($method, $url, $params)
    {
        $auth = $this->_authentication->getData();
        // Create query string
        $queryString = http_build_query($params);
        $url = PYMTPro::API_BASE . $url;
        $codeit = "api_token=".$auth->apiKey."&api_secret=".$auth->apiKeySecret;

        // Initialize CURL
        $curl = curl_init();
        $curlOpts = array();

        // HTTP method
        $method = strtolower($method);
        if ($method == 'get') {
            $curlOpts[CURLOPT_HTTPGET] = 1;
            if ($queryString) {
                $url .= "?" . $queryString . "&" . $codeit;
            }
        } else if ($method == 'post') {
			$headers[] = "Content-Type: application/json";
			$headers[] = 'Content-Length: ' . strlen($params);
            $curlOpts[CURLOPT_POST] = 1;
			$url .= "?" . $codeit;
            $curlOpts[CURLOPT_POSTFIELDS] = json_encode($params);
        } else if ($method == 'delete') {
            $curlOpts[CURLOPT_CUSTOMREQUEST] = "DELETE";
            if ($queryString) {
                $url .= "?" . $queryString . "&" . $codeit;
            }
        } else if ($method == 'put') {
			$headers[] = "Content-Type: application/json";
			$headers[] = 'Content-Length: ' . strlen($params);
			$url .= "?" . $codeit;
            $curlOpts[CURLOPT_CUSTOMREQUEST] = "PUT";
            $curlOpts[CURLOPT_POSTFIELDS] = json_encode($params);
        }

        // Headers
        $headers = array('User-Agent: PYMTPro/v2');

        // Get the authentication class and parse its payload into the HTTP header.
        $authenticationClass = get_class($this->_authentication);
        switch ($authenticationClass) {
            case 'PYMTPro_OAuthAuthentication':
                // Use OAuth
                if(time() > $auth->tokens["expire_time"]) {
                    throw new PYMTPro_TokensExpiredException("The OAuth tokens are expired. Use refreshTokens to refresh them");
                }
                $headers[] = 'Authorization: Bearer ' . $auth->tokens["access_token"];
                break;

            case 'PYMTPro_ApiKeyAuthentication':
                // Use HMAC API key
                $microseconds = sprintf('%0.0f',round(microtime(true) * 1000000));

                $dataToHash =  $microseconds . $url;
                if (array_key_exists(CURLOPT_POSTFIELDS, $curlOpts)) {
                    $dataToHash .= $curlOpts[CURLOPT_POSTFIELDS];
                }
                $signature = hash_hmac("sha256", $dataToHash, $auth->apiKeySecret);
                $secret    = md5($auth->apiKeySecret);

                $headers[] = "ACCESS_KEY: {$auth->apiKey}";
                $headers[] = "ACCESS_SECRET: {$secret}";
                $headers[] = "ACCESS_SIGNATURE: $signature";
                $headers[] = "ACCESS_NONCE: $microseconds";
                break;

            default:
                throw new PYMTPro_ApiException("Invalid authentication mechanism");
                break;
        }
        // CURL options
        $curlOpts[CURLOPT_URL] = $url;
        $curlOpts[CURLOPT_HTTPHEADER] = $headers;
        $curlOpts[CURLOPT_CAINFO] = dirname(__FILE__) . '/cacert.pem';
        $curlOpts[CURLOPT_RETURNTRANSFER] = true;
		$curlOpts[CURLOPT_SSL_VERIFYPEER] = false;

        // Do request
        curl_setopt_array($curl, $curlOpts);
        $response = $this->_requestor->doCurlRequest($curl);

        //$json = json_decode($response['body']);
        // Decode response
		try {
            $json = json_decode($response['body']);
        } catch (Exception $e) {
            throw new PYMTPro_ConnectionException("Invalid response body a", $response['statusCode'], $response['body']);
        }
        if($json === null) {
            throw new PYMTPro_ApiException("Invalid response body - ".$response['statusCode']." | ".$response['body']." | ".$url, $response['statusCode'], $response['body']);
        }
        if(isset($json->error)) {
            throw new PYMTPro_ApiException($json->error, $response['statusCode'], $response['body']);
        } else if(isset($json->errors)) {
            throw new PYMTPro_ApiException(implode($json->errors, ', '), $response['statusCode'], $response['body']);
        }

        return $json;
    }
}
