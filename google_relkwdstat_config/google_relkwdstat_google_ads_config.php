<?php

// Load .env file
require_once __DIR__ . '/env_loader.php';
EnvLoader::load();

return [
    'developer_token' => EnvLoader::get('GOOGLE_ADS_DEVELOPER_TOKEN'),
    'client_id' => EnvLoader::get('GOOGLE_ADS_CLIENT_ID'),
    'client_secret' => EnvLoader::get('GOOGLE_ADS_CLIENT_SECRET'),
    'refresh_token' => EnvLoader::get('GOOGLE_ADS_REFRESH_TOKEN'),
    'customer_id' => EnvLoader::get('GOOGLE_ADS_CUSTOMER_ID'),
    'api_key' => EnvLoader::get('GOOGLE_ADS_API_KEY'),
    'login_customer_id' => EnvLoader::get('GOOGLE_ADS_LOGIN_CUSTOMER_ID')
];

