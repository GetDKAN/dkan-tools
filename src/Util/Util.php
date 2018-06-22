<?php
namespace DkanTools\Util;

/**
 * Misc utilities used throughout the application.
 *
 * @todo Refactor this to follow Robo standards (tasks? base command class?)
 */
class Util
{
    const TMP_DIR = "./tmp";

    public static function getDktlRoot()
    {
        return dirname(__DIR__, 2);
    }

    public static function drushConcurrency()
    {
        if (`uname` == "Darwin") {
            $concurrency = trim(`sysctl -n hw.ncpu`);
        } else {
            $concurrency = trim(`grep -c ^processor /proc/cpuinfo`);
        }
        return is_numeric($concurrency) ? $concurrency : '';
    }

    public static function prepareTmp()
    {
        $tmp_dir = self::TMP_DIR;
        if (!file_exists($tmp_dir)) {
            mkdir($tmp_dir);
        }
    }

    public static function urlExists($url)
    {
        $headers = @get_headers($url);
        return (count(preg_grep('/^HTTP.*404/', $headers)) > 0) ? false : true;
    }

    public static function getAllFilesWithExtension($path, $ext)
    {
        $files_with_extension = [];
        $subs = get_all_subdirectories($path);
        foreach ($subs as $sub) {
            $files = get_files_with_extension($sub, $ext);
            $files_with_extension = array_merge($files_with_extension, $files);
        }
        return $files_with_extension;
    }

    public static function getFilesWithExtension($path, $ext)
    {
        $files_with_extension = [];
        $files = get_files($path);
        foreach ($files as $file) {
            $e = pathinfo($file, PATHINFO_EXTENSION);
            if ($ext == $e) {
                $files_with_extension[] = $file;
            }
        }
        return $files_with_extension;
    }

    public static function getAllSubdirectories($path)
    {
        $all_subs = [];
        $stack = [$path];
        while (!empty($stack)) {
            $sub = array_shift($stack);
            $all_subs[] = $sub;
            $subs = get_subdirectories($sub);
            $stack = array_merge($stack, $subs);
        }
        return $all_subs;
    }

    public static function getSubdirectories($path)
    {
        $directories_info = shell_table_to_array(`ls {$path} -lha | grep '^dr'`);
        $subs = [];
        foreach ($directories_info as $di) {
            if (isset($di[8])) {
                $dir = trim($di[8]);
                if ($dir != "." && $dir != "..") {
                    $subs[] = "{$path}/{$dir}";
                }
            }
        }
        return $subs;
    }

    public function getFiles($path)
    {
        $files_info = shell_table_to_array(`ls {$path} -lha | grep -v '^dr'`);
        $files = [];
        foreach ($files_info as $fi) {
            if (isset($fi[8])) {
                $file = trim($fi[8]);
                $files[] = "{$path}/{$file}";
            }
        }
        return $files;
    }

    public static function shellTableToArray($shellTable)
    {
        $final = [];
        $lines = explode(PHP_EOL, $shellTable);

        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', $line);
            if (!empty($parts)) {
                $final[] = $parts;
            }
        }

        return $final;
    }
}
