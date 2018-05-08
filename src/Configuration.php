<?php

namespace DkanTools;

use Symfony\Component\Yaml\Yaml;

class Configuration {
  const FILE_NAME = "dktl.yaml";

  private $config;

  public function __construct() {
    if (file_exists(self::FILE_NAME)) {
      $this->config = Yaml::parse(file_get_contents(self::FILE_NAME));
    }
    else {
      $file_name = self::FILE_NAME;
      throw new \Exception("No Configuration file ({$file_name}) was found.");
    }
  }

  public function getDrupalVersion() {
    if (isset($this->config['Drupal Version'])) {
      return $this->config['Drupal Version'];
    }
    $file_name = self::FILE_NAME;
    throw new \Exception("Drupal Version is not set in {$file_name}.");
  }

  public function getDkanVersion() {
    if (isset($this->config['DKAN Version'])) {
      return $this->config['DKAN Version'];
    }
    $file_name = self::FILE_NAME;
    throw new \Exception("DKAN Version is not set in {$file_name}.");
  }

}
