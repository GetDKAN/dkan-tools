<?php
if (getenv("VIRTUAL_HOST") == "dkan.local") {
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
