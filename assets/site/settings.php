<?php

foreach (scandir(__DIR__) as $file) {
  if (preg_match("/settings\..*\.php/", $file) == TRUE) {
    include __DIR__ . "/{$file}";
  }
}
