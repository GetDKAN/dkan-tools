<?php

foreach (scandir(__DIR__) as $file) {
    if (preg_match("/settings\..*\.php/", $file) == true) {
        include __DIR__ . "/{$file}";
    }
}
