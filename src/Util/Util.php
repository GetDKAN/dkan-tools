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

    public static function getDktlDirectory()
    {
        return getenv("DKTL_DIRECTORY");
    }

    public static function getProjectDirectory() {
        $directory = exec("pwd");

        $argv = $_SERVER['argv'];

        if (isset($argv[1]) && $argv[1] == "init") {
            return $directory;
        }

        return getenv("DKTL_PROJECT_DIRECTORY");
    }

    public static function getProjectDocroot() {
        return self::getProjectDirectory() . "/docroot";
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
        $subs = self::getAllSubdirectories($path);
        foreach ($subs as $sub) {
            $files = self::getFilesWithExtension($sub, $ext);
            $files_with_extension = array_merge($files_with_extension, $files);
        }
        return $files_with_extension;
    }
}
