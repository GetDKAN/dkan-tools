<?php
require_once "util.php";

if (!file_exists("docroot/sites/default/settings.php")) {
  `cp ./docroot/sites/default/default.settings.php ./docroot/sites/default/settings.php`;
  if (file_exists("docroot/sites/default/settings.php")) {

    $txt =
    "// Docker Database Settings\n
    \$databases['default']['default'] = array(\n  
    'database' => 'drupal',\n  
    'username' => 'drupal',\n  
    'password' => '123',\n  
    'host' => 'db',\n  
    'port' => '',\n  
    'driver' => 'mysql',\n  
    'prefix' => '',\n
    );\n
    \n
    \$custom_settings = __DIR__ . \"/settings.custom.php\";\n
    if (file_exists(\$custom_settings)) {\n
      require \$custom_settings;\n
    }\n
    ";

    file_put_contents('docroot/sites/default/settings.php', $txt . PHP_EOL , FILE_APPEND | LOCK_EX);
  }
  else {
    echoe("ISSUES CREATING docroot/sites/default/settings.php.");
  }
}
else {
  echoe("Got docroot/sites/default/settings.php.");
}

