<?php
require_once "util.php";

$contrib_location = "docroot/profiles/dkan/modules/contrib";

if (!file_exists("docroot/profiles/dkan")) {
  throw new \Exception("DKAN is not present in Drupal at docroot/profiles/dkan");
}

if (file_exists($contrib_location)) {
  throw new \Exception("DKAN is already made.");
}

$cache = TMP_DIR . "/dkan_contrib";
if (file_exists($cache)) {
  echoe("Using the cache: {$cache}");
  `cp -r {$cache} {$contrib_location}`;
}
else {
  `drush --root=docroot -y make --no-core --contrib-destination=./ docroot/profiles/dkan/drupal-org.make --no-recursion --no-cache --verbose docroot/profiles/dkan`;
  `cp -r {$contrib_location} {$cache}`;
}
