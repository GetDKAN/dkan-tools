<?php
if (getenv("DKTL_DOCKER") == "1") {
    $databases = array(
    'default' => array(
      'default' => array(
        'database' => 'drupal',
        'username' => 'drupal',
        'password' => '123',
        'host' => 'db',
        'port' => '',
        'driver' => 'mysql',
        'prefix' => '',
      ),
    ),
    );
    $settings['file_public_base_url'] = "http://dkan/sites/default/files";
}
