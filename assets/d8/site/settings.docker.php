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
}
