<?php
if (getenv("VIRTUAL_HOST") == "dkan.docker") {
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
