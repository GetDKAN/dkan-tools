<?php

namespace DkanTools\Drupal\V7;

use DkanTools\Util\Util;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class DkanCommands extends \Robo\Tasks
{


    /**
     * Get DKAN.
     *
     * @param string $version
     *   The DKAN version to get (ex. 7.x-1.15.3).
     * @param array $opts
     * @option $source
     *   Use git and preserve git directories to get DKAN (use if developing on
     *   DKAN itself and you expect to push changes back.)
     * @option $release
     *   Redundant to the $verion argument. Provided for historical reasons.
     */
    public function dkanGet(string $version = null, $opts = ['source' => false, 'release' => null])
    {
        if (!$version && $opts['release']) {
            $version = $opts['release'];
        }
        if (!$version) {
            throw new \Exception('You must specify a version.');
        }
        Util::prepareTmp();
        if ($opts['source']) {
            $this->getDkanGit($version);
        } else {
            $archive = $this->getDkanArchive($version);
            $task = $this->taskExec("tar -xvzf {$archive}")->dir(Util::TMP_DIR);
            $task->run();
        }

        // At this point we should have the unbuilt DKAN folder in tmp.
        $this->dkanTempReplace(str_replace(".tar.gz", "", $archive));
        Util::cleanupTmp();
    }

    private function getDkanArchive($version)
    {
        $fileName = "{$version}.tar.gz";
        $archive = Util::TMP_DIR . "/dkan-{$fileName}";
        if (file_exists($archive)) {
            $this->io()->warning(
                "DKAN archive $fileName.tar.gz already exists; skipping download, will attempt extraction."
            );
            return $archive;
        }

        $sources = [
          "https://github.com/GetDKAN/dkan/releases/download/{$version}/{$fileName}",
          "https://github.com/GetDKAN/dkan/archive/{$fileName}",
        ];
        $source = null;
        foreach ($sources as $s) {
            if (Util::urlExists($s)) {
                $source = $s;
                break;
            }
        }

        if (!isset($source)) {
            throw new \Exception("No archive available for DKAN $version.");
            return false;
        }

        $this->io()->section("Getting DKAN from {$source}");
        $this->taskExec("wget -O {$archive} {$source}")->run();
        return $archive;
    }


    private function dkanTempReplace($tmp_dkan)
    {
        $dkan_permanent = Util::getProjectDirectory() . '/dkan';
        $replaced = false;
        if (file_exists($dkan_permanent)) {
            if ($this->io()->confirm("Are you sure you want to replace your current DKAN profile directory?")) {
                $this->_deleteDir($dkan_permanent);
                $replaced = true;
            } else {
                $this->say('Canceled.');
                return;
            }
        }
        $this->_exec('mv ' . $tmp_dkan . ' ' . $dkan_permanent);
        $verb = $replaced ? 'replaced' : 'created';
        $this->say("DKAN profile directory $verb.");
    }
}
