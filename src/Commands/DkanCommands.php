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

    function dkanMake()
    {
        // if (!file_exists('docroot')) {
        //   throw new \Exception("A Drupal docroot must be present before running DKAN make.");
        // }
        // Discover proper concurrency setting for system.

        $this->_deleteDir(['dkan/modules/contrib', 'dkan/themes/contrib', 'dkan/libraries']);

        $this->taskExec('drush -y make dkan/drupal-org.make')
            ->arg('--contrib-destination=./')
            ->arg('--no-core')
            ->arg('--root=docroot')
            ->arg('--no-recursion')
            ->arg('--no-cache')
            ->arg('--verbose')
            ->arg('--concurrency=' . Utils::drushConcurrency())
            ->arg('dkan')
            ->run();
    }

    /**
     * Run DKAN DB installation. Pass --backup (-b) to restore from last backup.
     *
     * @todo Use Robo config system to load options, allow overrides.
     */
    function dkanInstall($opts = ['backup|b' => false, 'account-pass' => 'admin', 'site-name' => 'dkan'])
    {
        if ($opts['backup']) {
            $this->restoreFromBackup();
            exit;
        }
        $result = $this->taskExec('drush -y si dkan')
            ->dir('docroot')
            ->arg('--verbose')
            ->arg("account-pass={$opts['account-pass']}")
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

    private function restoreFromBackup()
    {
        if (!file_exists('backups') || !file_exists('backups/last_install.sql')) {
            throw new \Exception('Last DB backup could not be found.');
        }
        $result = $this->taskExec('drush -y sql-drop')->dir('docroot')->run();
        if ($result->getExitCode() != 0) {
            return $result;
        }
        $this->say("Removed tables, restoring DB");
        $result = $this->taskExec('drush sqlc <')
            ->arg('../backups/last_install.sql')
            ->dir('docroot')
            ->run();
        if ($result->getExitCode() != 0) {
            $this->io()->success('Database backup restored.');
            return $result;
        }
    }

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
            if ($this->io()->confirm("Are you sure you want to replace your current DKAN profile directory?")) {
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
