<?php
require_once "util.php";

$contrib_modules_location = "docroot/sites/all/modules/contrib";
$contrib_libraries_location = "docroot/sites/all/libraries";


if (!file_exists("docroot")) {
  throw new \Exception("Drupal is not present at docroot");
}

if (file_exists($contrib_modules_location)) {
  throw new \Exception("Drupal contrib has already been made.");
}

$cache_modules = TMP_DIR . "/drupal_contrib";
$cache_libs = TMP_DIR . "/drupal_libs";

if (file_exists($cache_modules)) {
  echoe("Using the cache: {$cache_modules}");
  `cp -r {$cache_modules} {$contrib_modules_location}`;
  `cp -r {$cache_libs}/* {$contrib_libraries_location}/`;

}
else {
  `drush --root=docroot -y make --no-core --contrib-destination=docroot/sites/all custom/custom.make --no-recursion --no-cache --verbose`;
  `cp -r {$contrib_modules_location} {$cache_modules}`;
  `cp -r {$contrib_libraries_location} {$cache_libs}`;
}
