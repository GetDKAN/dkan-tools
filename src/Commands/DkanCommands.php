<?php
namespace DkanTools\Commands;

use DkanTools\Util\Util;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class DkanCommands extends \Robo\Tasks
{
    const DKAN_TMP_DIR = Util::TMP_DIR . "/dkan";

    function dkanGet(string $version = NULL, $opts = ['source' => FALSE])
    {

        if (file_exists(self::DKAN_TMP_DIR)) {
            $this->_deleteDir(self::DKAN_TMP_DIR);
        }
        if ($opts['source']) {
            $this->getDkanGit($version);
        }
        else {
            $archive = $this->getDkanArchive($version);
            $this->taskExtract($archive)
                ->to(self::DKAN_TMP_DIR)
                ->run();
        }

        // At this point we should have the unbuilt DKAN folder in tmp.
        $this->dkanTempReplace();
        // $this->dkanLink();

    }

    function getDkanArchive($version) {
        Util::prepareTmp();

        $fileName = "{$version}.tar.gz";
        $archive = Util::TMP_DIR . "/dkan-{$fileName}";
        if (file_exists($archive)) {
            $this->io()->warning("DKAN archive $fileName.tar.gz already exists; skipping download, will attempt extraction.");
            return $archive;
        }

        $sources = [
          "https://github.com/GetDKAN/dkan/releases/download/{$version}/{$fileName}",
          "https://github.com/GetDKAN/dkan/archive/{$fileName}",
        ];

        $source = NULL;
        foreach ($sources as $s) {
          if (Util::urlExists($s)) {
            $source = $s;
            break;
          }
        }

        if (!isset($source)) {
            $this->io()->error("No archive available for DKAN $version.");
            return;
        }

        $this->io()->section("Getting DKAN from {$source}");
        $this->taskExec("wget -O {$archive} {$source}")->run();
        return $archive;
    }

    private function dkanTempReplace() {
        $dkanPermanent = getcwd() . '/dkan';
        $replaced = FALSE;
        if (file_exists($dkanPermanent)) {
            if ($this->confirm("Are you sure you want to replace your current DKAN profile directory?")) {
                $this->_deleteDir($dkanPermanent);
                $replaced = TRUE;
            }
            else {
                $this->say('Canceled.');
                return;
            }
        }
        $this->_exec('mv ' . self::DKAN_TMP_DIR . ' ' . getcwd());
        $verb = $replaced ? 'replaced' : 'created';
        $this->say("DKAN profile directory $verb.");
    }
}
