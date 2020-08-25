<?php

if (getenv("DKTL_DOCKER") == "1") {
    $databases = [
        'default' => [
            'default' => [
                'database' => 'drupal',
                'username' => 'drupal',
                'password' => '123',
                'host' => 'db',
                'port' => '',
                'driver' => 'mysql',
                'prefix' => '',
            ],
        ],
    ];

    $url = getenv('DKTL_PROJECT_URL');
    $settings['file_public_base_url'] = $url . "/sites/default/files";
}
