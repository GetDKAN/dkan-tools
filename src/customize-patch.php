<?php
require_once "util.php";

if (file_exists("./custom/diffs")) {
  $directories[] = "./custom/diffs";

  do {
    $directory = array_shift($directories);
    patch_directory($directory);
    $directories = array_merge($directories, get_subdirectories($directory));
  }while (!empty($directories));

}

function patch_directory($dir) {
  $files = get_files($dir);

  $current_directory = trim(shell_exec("echo $(pwd)"));

  foreach ($files as $file) {
    $patch_full_path = $current_directory . substr($file,1);

    $patch_file = basename($file);
    $patch_directory = dirname($file);
    $file_to_patch = str_replace(".diff", "", $patch_file);
    $file_to_patch_directory = str_replace("custom/diffs", "docroot", $patch_directory);
    $command = "patch {$file_to_patch_directory}/{$file_to_patch} < {$patch_full_path}";
    echoe($command);
    passthru($command);
  }
}