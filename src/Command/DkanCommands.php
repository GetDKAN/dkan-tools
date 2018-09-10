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

    /**
     * DKAN restore.
     *
     * A command that creates a DKAN site from a db dump and files.
     *
     * @param string $db_url
     *   A url to a file with sql commands to recreate a database. sql and sql.gz files are supported.
     * @param string $files_url
     *   A url to an archive with all the files to the site. zip, gz, and tar.gz files are supported.
     */
    public function dkanRestore($db_url, $files_url)
    {
        $this->restoreDb($db_url);
        $this->restoreFiles($files_url);
    }

    private function restoreDb($db_url)
    {
        $info = pathinfo($db_url);
        $filename = $info['basename'];
        $ext = $info['extension'];

        $c = $this->collectionBuilder();
        $tmp_path = $c->tmpDir();

        $c->addTask($this->taskExec("wget -O {$tmp_path}/{$filename} {$db_url}"));

        $drupal_root = Util::getProjectDocroot();

        $c->addTask($this->taskExec('drush -y sql-drop')->dir($drupal_root));

        if ($ext == "gz") {
            $c->addTask($this->taskExec("zcat {$tmp_path}/{$filename} | drush sqlc")->dir($drupal_root));
        }
        else {
            $c->addTask($this->taskExec('drush sqlc <')->arg("{$tmp_path}/{$filename}")->dir($drupal_root));
        }

        $result = $c->run();

        if ($result->getExitCode() == 0) {
            $this->io()->success('Database restored.');
        }
        else {
            $this->io()->error('Issues restoring the database.');
        }

        return $result;
    }

    private function restoreFiles($files_url)
    {
        $project_directory = Util::getProjectDirectory();
        $info = pathinfo($files_url);

        $full_file_name = $info['basename'];
        $extension = $info['extension'];

        $c = $this->collectionBuilder();
        $tmp_path = $c->tmpDir();

        $c->addTask($this->taskExec("wget -O {$tmp_path}/{$full_file_name} {$files_url}"));

        if($extension == "zip") {
            $c->addTask($this->taskExec("unzip {$tmp_path}/{$full_file_name} -d {$tmp_path}"));
        }
        else if($extension == "gz") {
            if (substr_count($full_file_name, ".tar") > 0) {
                $c->addTask($this->taskExec("tar -xvzf {$full_file_name}")->dir($tmp_path));
            }
            else {
                $c->addTask($this->taskExec("gunzip {$tmp_path}/{$full_file_name}"));
            }
        }

        $c->addTask($this->taskFlattenDir(["{$tmp_path}/*" => "{$tmp_path}/files"]));
        $c->addTask($this->taskCopyDir(["{$tmp_path}/files" => "{$project_directory}/src/site/files"]));

        $result = $c->run();

        if ($result->getExitCode() == 0) {
            $this->io()->success('Files Restored.');
        }
        else {
            $this->io()->error('Issues Restoring.');
        }

        return $result;
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

    public function dkanDeploy($target_environment)
    {
        $project = Util::getProjectDirectory();
        $script = "{$project}/src/script/deploy.sh";
        $docroot = Util::getProjectDocroot();

        if (file_exists($script)) {
            $command = "{$script} {$docroot} {$target_environment}";
            $this->_exec($command);
        }
    }
}
