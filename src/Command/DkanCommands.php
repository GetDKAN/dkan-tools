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

    /**
     * Get DKAN.
     *
     * @param string $version
     *   The DKAN version to get (ex. 7.x-1.15.3).
     */
    public function dkanGet(string $version, $opts = ['source' => false])
    {
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

    public function getDkanArchive($version)
    {
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
    public function dkanRestore($db_url = NULL, $files_url = NULL)
    {
        if ($db_url) {
          $this->restoreDb($db_url);
        }
        if ($files_url) {
          $this->restoreFiles($files_url);
        }
    }

    /**
     * Restore a database dump to the db container.
     *
     * @param string $db_url URL to DB dump to restore.
     * @see self::dkanRestore() For full documentation on URL params.
     */
    private function restoreDb($db_url)
    {
        Util::prepareTmp();
        $file_path = $this->getFile($db_url);
        $info = pathinfo($file_path);
        $ext = $info['extension'];
        $drupal_root = Util::getProjectDocroot();

        $c = $this->collectionBuilder();
        $c->addTask($this->taskExec('drush -y sql-drop')->dir($drupal_root));

        if ($ext == "gz") {
            $c->addTask($this->taskExec("zcat $file_path | drush sqlc")->dir($drupal_root));
        }
        else {
            $c->addTask($this->taskExec('drush sqlc <')->arg($file_path)->dir($drupal_root));
        }

        $result = $c->run();

        if ($result->getExitCode() == 0) {
            $this->io()->success('Database restored.');
        }
        else {
            $this->io()->error('Issues restoring the database.');
        }
        Util::cleanupTmp();

        return $result;
    }

    /**
     * Restore a files archive to appropriate site directories.
     *
     * @param string $files_url Files URL to restore.
     * @see self::dkanRestore() For full documentation on URL params.
     */
    private function restoreFiles(string $files_url)
    {
        Util::prepareTmp();
        $filePath = $this->getFile($files_url);
        $projectDirectory = Util::getProjectDirectory();

        $parentDir = $this->restoreFilesExtract($filePath);

        if (is_dir("{$parentDir}/files")) {
            $this->restoreFilesCopy("{$parentDir}/files", "{$projectDirectory}/src/site/files");
        }
        if (is_dir("{$parentDir}/private")) {
            $this->restoreFilesCopy("{$parentDir}/private", "{$projectDirectory}/private");
        }
        if (!is_dir("{$parentDir}/files") && !is_dir("{$parentDir}/private")) {
          $this->io->warning('No files found');
          return FALSE;
        }

        Util::cleanupTmp();
    }

    /**
     * Extract a zip or tar.gz archive in the tmp dir.
     *
     * @param string $filePath  The path to the archive.
     *
     * @return string The directory to which the archive was extracted.
     */
    private function restoreFilesExtract(string $filePath)
    {
        $tmpPath = Util::TMP_DIR;
        $info = pathinfo($filePath);
        $extension = $info['extension'];

        if($extension == "zip") {
            $taskUnzip = $this->taskExec("unzip $filePath -d {$tmpPath}");
            $parentDir = substr($filepath, 0, -4);
        }
        else if($extension == "gz") {
            if (substr_count($filePath, ".tar") > 0) {
                $taskUnzip = $this->taskExec("tar -xvzf {$filePath}")->dir($tmpPath);
                $parentDir = substr($filePath, 0, -7);
            }
            else {
                $taskUnzip = $this->taskExec("gunzip {$filePath}");
                $parentDir = substr($filepath, 0, -3);
            }
        }
        else {
          throw new \Exception('Could not extract file.');
        }
        $result = $taskUnzip->run();
        if (is_dir($parentDir) && $result->getExitCode() == 0) {
          return $parentDir;
        }
        throw new \Exception('Extraction failed.');
    }

    private function restoreFilesCopy(string $source, string $destination)
    {
        if (is_dir($source)) {
            $this->say('Copying files');
            $result = $this->taskCopyDir([$source => $destination])->run();
            if ($result->getExitCode() == 0) {
                $this->io()->success("Files restored to $destination.");
            }
            else {
                throw new \Exception("Failed restoring files to $destination.");
            }
            return $result;
        }
        throw new \Exception("Source dir $source not found.");
    }

    private function getFile($url) {
        $tmp_dir_path = Util::TMP_DIR;

        if (substr_count($url, "http://") > 0 || substr_count($url, "https://")) {
            $info = pathinfo($url);
            $filename = $info['basename'];
            $approach = "wget -O {$tmp_dir_path}/{$filename} {$url}";
        }
        elseif (substr_count($url, "s3://")) {
            $parser = new \Aws\S3\S3UriParser();
            $info = $parser->parse($url);
            $filename = $info['key'];
            $approach = "aws s3 cp {$url} {$tmp_dir_path}";
        }
        else {
            $this->io()->error("Unsupported file protocol.");
            return;
        }

        $result = $this->taskExec($approach)->run();

        if ($result->getExitCode() == 0) {
            $this->io()->success("Got the file from {$url}.");
        }
        else {
            $this->io()->error("Issues getting the file from {$url}.");
        }

        return "$tmp_dir_path/$filename";
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

    /**
     * Performs common tasks when switching databases or code bases.
     *
     * Operations like running rr and updb. It also runs environment
     * switching which is provided by the environment module.
     *
     * @param string $target_environment
     *   One of the site environments. DKTL provides 4 by default: local,
     *   development, test, and production.
     */
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
