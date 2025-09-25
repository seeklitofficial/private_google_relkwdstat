<?php

// Load .env file
require_once __DIR__ . '/google_relkwdstat_env_loader.php';
EnvLoader::load();

return [
    'google_ads_developer_token' => EnvLoader::get('GOOGLE_ADS_DEVELOPER_TOKEN'),
    'google_ads_client_id' => EnvLoader::get('GOOGLE_ADS_CLIENT_ID'),
    'google_ads_client_secret' => EnvLoader::get('GOOGLE_ADS_CLIENT_SECRET'),
    'google_ads_refresh_token' => EnvLoader::get('GOOGLE_ADS_REFRESH_TOKEN'),
    'google_ads_customer_id' => EnvLoader::get('GOOGLE_ADS_CUSTOMER_ID'),
    'google_ads_api_key' => EnvLoader::get('GOOGLE_ADS_API_KEY'),
    'google_ads_login_customer_id' => EnvLoader::get('GOOGLE_ADS_LOGIN_CUSTOMER_ID')
];

