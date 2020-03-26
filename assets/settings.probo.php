<?php

if (!empty(getenv("PROBO_ENVIRONMENT"))) {
    $databases = array(
    'default' => array(
      'default' => array(
        'database' => 'dkan',
        'username' => 'root',
        'password' => 'strongpassword',
        'host' => 'localhost',
        'driver' => 'mysql',
      ),
    ),
    );
}
