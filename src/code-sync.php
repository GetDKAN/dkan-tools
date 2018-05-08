<?php
require_once 'util.php';

$output = [];
exec("diff -r ./docroot ./docroot-legacy", $output);

$plan = [];

foreach ($output as $line) {
  if (substr_count($line, "Only in ./docroot-legacy") > 0) {
    $l = $line;
    $l = str_replace("Only in ", "", $l);
    $pieces = explode(":", $l);
    $path = $pieces[0];
    $path = trim($path);
    $file = $pieces[1];
    $file = trim($file);

    $src_dir = $path;

    $p = $path;
    $p = str_replace("docroot-legacy", "custom/docroot", $p);

    $dest_dir = $p;

    if (!empty($file) && $file != "'") {
      $plan[] = ['src_dir' => $src_dir, 'dest_dir' => $dest_dir, 'file' => $file];
      echoe("COPY FROM {$src_dir}/{$file} TO {$dest_dir}/{$file}");
    }
  }
}

$ask_user = TRUE;
$execute_plan = FALSE;
do {
  echoe("Would you like to execute this plan? (y/n)");
  $execute_plan_string = $input = trim(fgets(STDIN));

  if ($execute_plan_string == "y") {
    $execute_plan = TRUE;
    $ask_user = FALSE;
  }
  elseif ($execute_plan_string == "n") {
    $execute_plan = FALSE;
    $ask_user = FALSE;
  }
  else {
    echoe("Incorrect option: {$execute_plan_string}");
  }
} while($ask_user);

if ($execute_plan == TRUE) {
  foreach ($plan as $key => $info) {

    if (!file_exists("{$info['dest_dir']}/{$info['file']}")) {
      `mkdir -p "{$info['dest_dir']}" && cp -r {$info['src_dir']}/{$info['file']}  "{$info['dest_dir']}"`;
    }
  }
}