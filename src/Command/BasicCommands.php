<?php

namespace DkanTools\Command;

use Robo\Result;
use DkanTools\Util\Util;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class BasicCommands extends \Robo\Tasks
{
    /**
     * Run drush command on current site.
     *
     * Run drush command on current site. For instance, to clear caches, run
     * "dktl drush cc all".
     *
     * @param array $cmd Array of arguments to create a full Drush command.
     */
    public function drush(array $cmd)
    {
        $drupal_root = Util::getProjectDocroot();
        $drushExec = $this->taskExec('drush')->dir($drupal_root);
        foreach ($cmd as $arg) {
            $drushExec->arg($arg);
        }
        return $drushExec->run();
    }

    /**
     * Proxy to composer.
     */
    public function composer(array $cmd)
    {
        $exec = $this->taskExec('composer');
        foreach ($cmd as $arg) {
            $exec->arg($arg);
        }
        return $exec->run();
    }

    /**
     * Run DKAN DB installation.
     *
     * @option @account-pass
     *   Password to assign to admin user. Defaults to "admin".
     * @option $site-name
     *   Site name for your new Drupal site. Defaults to "DKAN".
     */
    public function install($opts = ['account-pass' => 'admin', 'site-name' => 'DKAN'])
    {
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
        $result = $this->taskExec('drush sql-dump | gzip >')
            ->arg('../backups/last_install.sql.gz')
            ->dir('docroot')
            ->run();
        if ($result->getExitCode() != 0) {
            $this->io()->success('Backup created in "backups" folder.');
        }
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
    public function deploy($target_environment)
    {
        $project = Util::getProjectDirectory();
        $script = "{$project}/src/script/deploy.sh";
        $docroot = Util::getProjectDocroot();

        if (file_exists($script)) {
            $command = "{$script} {$docroot} {$target_environment}";
            $this->_exec($command);
        }
    }

    public function xdebugStart() 
    {
        $platform = getenv("PLATFORM");
        $sourceFile = ($platform == 'Darwin') ? 'xdebug-macos.ini' : 'xdebug-linux.ini';
        $dktlRoot = Util::getDktlDirectory();
        $this->io()->text("Creating new xdebug.ini file for {$platform} platform.");
        
        $f = 'src/docker/etc/php/xdebug.ini';
        if (file_exists($f)) {
            throw new \Exception("File {$f} already exists.");
        } 

        $result = $this->taskWriteToFile($f)
            ->textFromFile("$dktlRoot/assets/docker/etc/php/$sourceFile")
            ->run();

        Util::directoryAndFileCreationCheck($result, $f, $this->io());
    }

    public function xdebugStop()
    {
        $f = 'src/docker/etc/php/xdebug.ini';
        $result = unlink($f);
        if ($result) {
            $this->io()->success("Removed xdebug.ini; restarting.");
            return $result;
        }
        else {
            throw new \Exception("Failed, xdebug.ini not found.");
        }
    }
}
