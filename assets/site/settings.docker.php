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

  $conf['environment_indicator_overwrite'] = true;
  $conf['environment_indicator_overwritten_name'] = 'Local';
  $conf['environment_indicator_overwritten_color'] = '#ff0000';
  $conf['environment_indicator_overwritten_text_color'] = '#ffffff';
  $conf['environment_indicator_overwritten_position'] = 'top';
  $conf['environment_indicator_overwritten_fixed'] = true;
}
