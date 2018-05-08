<?php
require_once "util.php";

$arguments = $argv;
unset($arguments[0]);
$args = implode(" ", $arguments);
passthru("drush --root=docroot {$args}");