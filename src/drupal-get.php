<?php
require_once "util.php";
require_once "vendor/autoload.php";

use \DkanTools\Configuration;

echoe("Running drupal-get");

if (isset($argv[1])) {
  $drupal_version = $argv[1];
}
else {
  $config = new Configuration();
  $drupal_version = $config->getDrupalVersion();
}

get_drupal_archive($drupal_version);

decompress_drupal_archive($drupal_version);

copy_drupal_as_docroot($drupal_version);

function get_drupal_archive($drupal_version) {
  prepare_tmp();

  $file_name = "drupal-{$drupal_version}.tar.gz";

  $archive = TMP_DIR . "/{$file_name}";
  $got_drupal = file_exists($archive);
  if ($got_drupal) {
    echoe("Got Drupal .tar.gz: " . bool_to_str($got_drupal));
    return;
  }

  $source = "https://ftp.drupal.org/files/projects/{$file_name}";

  if (url_exists($source)) {
    echoe("Getting Drupal from {$source}");
    `wget -O {$archive} {$source}`;
  }
  else {
    throw new \Exception("Could not get Drupal at {$source}");
  }
}

function decompress_drupal_archive($drupal_version) {
  $file_name = "drupal-{$drupal_version}.tar.gz";
  $archive = TMP_DIR . "/{$file_name}";
  $decompressed = TMP_DIR . "/drupal-{$drupal_version}";

  if (file_exists($decompressed)) {
    echoe("Got {$decompressed}");
    return;
  }

  if (file_exists($archive)) {
    $tmp = TMP_DIR;
    `tar -xzvf {$archive} -C {$tmp}`;
  }
  else {
    throw new \Exception("The Drupal archive {$archive} does not exist.");
  }
}

function copy_drupal_as_docroot($drupal_version) {
  $drupal = "docroot";
  $decompressed = TMP_DIR . "/drupal-{$drupal_version}";

  if (file_exists($drupal)) {
    echoe("Got {$drupal}");
    return;
  }

  if (file_exists($decompressed)) {
    `cp -r {$decompressed} {$drupal}`;
  }
  else {
    throw new \Exception("Drupal not found at {$decompressed}");
  }
}
