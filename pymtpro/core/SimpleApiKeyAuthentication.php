<?php

class PYMTPro_SimpleApiKeyAuthentication extends PYMTPro_Authentication
{
    private $_apiKey;

    public function __construct($apiKey)
    {
        $this->_apiKey = $apiKey;
    }

    public function getData()
    {
        $data = new stdClass();
        $data->apiKey = $this->_apiKey;
        return $data;
    }
}