<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Gateway Node Role
    |--------------------------------------------------------------------------
    |
    | Defines the operational role of this node in the multi-tenant architecture.
    | Options: 'spoke' (tenant), 'hub' (gateway), 'standalone' (both hub & spoke)
    |
    */

    'role' => env('CMS_NODE_ROLE', 'spoke'),

    /*
    |--------------------------------------------------------------------------
    | Hub Node Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Meta WhatsApp Business Cloud API used by the Hub node.
    |
    */

    'hub' => [
        'admin' => [
            'username' => env('HUB_ADMIN_USERNAME', 'admin'),
            'password' => env('HUB_ADMIN_PASSWORD', 'password'),
        ],
        'whatsapp' => [
            'token' => env('WHATSAPP_CLOUD_API_TOKEN', ''),
            'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID', ''),
            'verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN', ''),
        ],
    ],

];
