<?php
require_once "util.php";

$output = [];
exec("diff -r ./docroot ./docroot-legacy", $output);

$current_directory = trim(shell_exec("echo $(pwd)"));

$diffs_directory = "{$current_directory}/custom/diffs";

if (!file_exists($diffs_directory)) {
  echoe("Making diffs directory: {$diffs_directory}");
  passthru("mkdir -p $diffs_directory");
}

foreach ($output as $line) {
  if (substr_count($line,"diff -r") > 0) {
    $l = str_replace("diff -r ", "", $line);
    $pieces = explode(" ", $l);

    $old_file = trim($pieces[0]);
    $new_file = trim($pieces[1]);
    $patch = create_patch($old_file, $new_file);

    $filename = basename($old_file);
    $directory = dirname($old_file);

    $destination = str_replace("./docroot", $diffs_directory, $directory);

    if (!file_exists($destination)) {
      passthru("mkdir -p {$destination}");
    }

    $patch_file_path = "{$destination}/{$filename}.diff";

    if (!file_exists($patch_file_path)) {
      echoe("Creating patch: {$patch_file_path}");
      file_put_contents($patch_file_path, $patch);
    }
  }
}

function create_patch($old, $new) {
  $command = "diff -uNr {$old} {$new}";
  return shell_exec($command);
}