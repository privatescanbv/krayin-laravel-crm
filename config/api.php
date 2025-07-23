<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Keys
    |--------------------------------------------------------------------------
    |
    | This array contains the valid API keys that can be used to access
    | the API endpoints. You can add multiple keys for different clients
    | or purposes. Keys should be set in your .env file.
    |
    */
    'keys' => array_filter([
        env('API_KEY_1'),
        env('API_KEY_2'),
        env('API_KEY_3'),
        // Add more keys as needed
    ]),
];
