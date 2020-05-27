<?php

namespace DkanTools\Command;

use Robo\Result;
use DkanTools\Util\Util;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class ProxyCommands extends \Robo\Tasks
{
    /**
     * Run drush command on current site.
     *
     * Run drush command on current site. For instance, to clear caches, run
     * "dktl drush cc all".
     *
     * @param array $cmd Array of arguments to create a full Drush command.
     *
     * @aliases drush
     */
    public function proxyDrush(array $cmd)
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
     *
     * @aliases composer
     */
    public function proxyComposer(array $cmd)
    {
        $exec = $this->taskExec('composer');
        foreach ($cmd as $arg) {
            $exec->arg($arg);
        }
        return $exec->run();
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

    /**
     * Check for existence of XDEBUG_DKTL environment variable.
     */
    private function xdebugCheck()
    {
        $xdebugDktl = getenv("XDEBUG_DKTL");
        if (!$xdebugDktl) {
            throw new \Exception("XDEBUG_DKTL environment variable must be "
            . " set to use this command.");
        }
    }

    /**
     * Start xdebug on CLI and web containers.
     *
     * This command adds a .ini file to your src/docker/etc/php directory and
     * restarts the CLI and web containers. It checks for compatibility by
     * looking for a DKTL_XDEBUG environment variable. This is set by default in
     * DKAN Tools' containers, but you may need to set it again if using your
     * own.
     *
     * Adding /src/docker/etc to your project .gitignore is reccomended.
     */
    public function xdebugStart()
    {
        $this->xdebugCheck();
        
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

    /**
     * Stop xdebug on CLI and web containers.
     *
     * Removes the xdebug.ini file and restarts CLI and web containers. See
     * xdebug:start for more information.
     */
    public function xdebugStop()
    {
        $this->xdebugCheck();

        $f = 'src/docker/etc/php/xdebug.ini';
        $result = unlink($f);
        if ($result) {
            $this->io()->success("Removed xdebug.ini; restarting.");
            return $result;
        } else {
            throw new \Exception("Failed, xdebug.ini not found.");
        }
    }

  /**
   * Create a database dump excluding devel and datastore tables.
   *
   * Run drush command on $alias to create a database dump excluding tables
   * related to devel and datastore.
   *
   * @param String $alias Drush alias of the site we want the db from.
   */
    public function siteGrabDatabase($alias)
    {
      // Tables for which we want the structure and not the data.
        $structure_tables_common = array(
        'accesslog', 'batch', 'cache', 'cache_*', '*_cache', 'ctools_views_cache',
        'ctools_object_cache', 'flood', 'history', 'queue', 'search_*',
        'semaphore', 'sessions', 'watchdog'
        );
        $structure_tables_devel = array('devel_queries', 'devel_times');
        $structure_tables_webform = array('webform_submitted_data');
        $structure_tables = array_merge(
            $structure_tables_common,
            $structure_tables_devel,
            $structure_tables_webform
        );
        $structure_tables_list = implode(', ', $structure_tables);
      // Tables to be completely skipped.
        $skip_tables = array('dkan_datastore_*');
        $skip_tables_list = implode(', ', $skip_tables);

        return $this->taskExec("drush $alias sql-dump")
            ->option('structure-tables-list', $structure_tables_list)
            ->option('skip-tables-list', $skip_tables_list)
            ->rawArg('> excluded_tables.sql')
            ->run();
    }
}
