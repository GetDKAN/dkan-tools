<?php
namespace DkanTools\Command;

use DkanTools\Util\Util;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class DkanCommands extends \Robo\Tasks
{
    const DKAN_TMP_DIR = Util::TMP_DIR . "/dkan";

    /**
     * Run the DKAN make file and apply any overrides from /config.
     */
    public function dkanMake()
    {
        $this->_deleteDir(['dkan/modules/contrib', 'dkan/themes/contrib', 'dkan/libraries']);

        $this->taskExec('drush -y make dkan/drupal-org.make')
            ->arg('--contrib-destination=./')
            ->arg('--no-core')
            ->arg('--root=docroot')
            ->arg('--no-recursion')
            ->arg('--no-cache')
            ->arg('--verbose')
            ->arg('--overrides=../src/make/dkan.make')
            ->arg('--concurrency=' . Util::drushConcurrency())
            ->arg('dkan')
            ->run();
    }

    /**
     * Run DKAN DB installation. Pass --backup (-b) to restore from last backup.
     *
     * @todo Use Robo config system to load options, allow overrides.
     */
    public function dkanInstall($opts = ['backup|b' => false, 'account-pass' => 'admin', 'site-name' => 'DKAN'])
    {
        if ($opts['backup']) {
            $result = $this->restoreDbFromBackup('last_install.sql');
            return $result;
        }
        if (!file_exists('docroot/modules') || !file_exists('dkan/modules/contrib')) {
            throw new \Exception('Codebase not fully built, install could not procede.');
        }
        $result = $this->taskExec('drush -y si dkan')
            ->dir('docroot')
            ->arg('--verbose')
            ->arg("--account-pass={$opts['account-pass']}")
            ->arg("--site-name={$opts['site-name']}")
            ->rawArg('install_configure_form.update_status_module=\'array(FALSE,FALSE)\'')
            ->run();
        if ($result->getExitCode() != 0) {
            $this->io()->error('Installation command failed.');
            return $result;
        }
        $this->io()->success('Installation completed successfully.');

        if (!file_exists('backups')) {
            $this->_mkdir('backups');
        }
        $result = $this->taskExec('drush sql-dump > ../backups/last_install.sql')
            ->dir('docroot')
            ->run();
        if ($result->getExitCode() != 0) {
            $this->io()->success('Backup created in "backups" folder.');
        }
    }

    private function restoreDbFromBackup($file)
    {
        if (!file_exists('backups') || !file_exists("backups/{$file}")) {
            throw new \Exception("{$file} backup could not be found.");
        }
        $result = $this->taskExec('drush -y sql-drop')->dir('docroot')->run();
        if ($result->getExitCode() != 0) {
            return $result;
        }
        $this->say("Removed tables, restoring DB");
        $result = $this->taskExec('drush sqlc <')
            ->arg("../backups/{$file}")
            ->dir('docroot')
            ->run();
        if ($result->getExitCode() != 0) {
            $this->io()->success('Database backup restored.');
            return $result;
        }
    }

    public function dkanGet(string $version = null, $opts = ['source' => false])
    {
        if (file_exists(self::DKAN_TMP_DIR)) {
            $this->_deleteDir(self::DKAN_TMP_DIR);
        }
        if ($opts['source']) {
            $this->getDkanGit($version);
        } else {
            $archive = $this->getDkanArchive($version);
            $this->taskExtract($archive)
                ->to(self::DKAN_TMP_DIR)
                ->run();
        }

        // At this point we should have the unbuilt DKAN folder in tmp.
        $this->dkanTempReplace();
    }

    public function getDkanArchive($version)
    {
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

        $source = null;
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

    public function dkanRestore($opts = ['db' => '', 'files' => ''])
    {
        $this->restoreDb($opts['db']);
        $this->restoreFiles($opts['files']);
    }

    private function restoreFiles($files)
    {
        if (file_exists('tmp/files.zip')) {
            $this->extractZipFiles();
            $this->restoreFilesFromBackup();
        } elseif (!empty($files)) {
            $this->taskExec("wget -O ./tmp/files.zip {$files}")->run();
            $this->restoreFilesFromBackup();
        } else {
            $this->io()->error("tmp/files.zip should exist, or the files option should be set.");
        }
    }

    private function extractZipFiles()
    {
        if (file_exists('tmp/files')) {
            $this->io()->success("Files archive has been extracted.");
        } else {
            $this->_exec("unzip tmp/files.zip -d tmp");
        }
    }

    private function restoreFilesFromBackup()
    {
        if (file_exists('tmp/files')) {
            $this->_exec("rsync -r tmp/files/ src/site/files/");
        } else {
            $this->io()->error("No files folder in tmp after extraction.");
        }
    }

    private function restoreDb($db)
    {
        if (!file_exists('backups')) {
            $this->_mkdir('backups');
        }

        if (file_exists('backups/db.sql')) {
            $this->restoreDbFromBackup("db.sql");
        } elseif (!empty($db)) {
            $this->taskExec("wget -O ./backups/db.sql {$db}")->run();
            $this->restoreDbFromBackup("db.sql");
        } else {
            $this->io()->error("backups/db.sql should exist, or the db option should be set.");
        }
    }

    private function dkanTempReplace()
    {
        $dkanPermanent = getcwd() . '/dkan';
        $replaced = false;
        if (file_exists($dkanPermanent)) {
            if ($this->io()->confirm("Are you sure you want to replace your current DKAN profile directory?")) {
                $this->_deleteDir($dkanPermanent);
                $replaced = true;
            } else {
                $this->say('Canceled.');
                return;
            }
        }
        $this->_exec('mv ' . self::DKAN_TMP_DIR . ' ' . getcwd());
        $verb = $replaced ? 'replaced' : 'created';
        $this->say("DKAN profile directory $verb.");
    }
}
