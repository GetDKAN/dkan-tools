<?php

namespace DkanTools\Command;

use DkanTools\SymlinksTrait;
use DkanTools\Util\Util;
use Robo\Tasks;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class MakeCommands extends Tasks
{
    use SymlinksTrait;

    const DRUPAL_FOLDER_NAME = "docroot";

    /**
     * Get all necessary dependencies and "make" a working codebase.
     *
     * Running `dktl make` will:
     *   1. Run composer install.
     *   2. Rebuild symlinks between the src and docroot dirs.
     *   3. If in docker mode, connect the proxy to enable local domain.
     *   4. If a frontend is present, install and build it.
     *
     * @option prefer-dist
     *   Prefer dist for packages. See composer docs.
     * @option prefer-source
     *   Prefer dist for packages. See composer docs.
     * @option no-dev
     *   Skip installing packages listed in require-dev.
     * @option optimize-autoloader
     *   Convert PSR-0/4 autoloading to classmap to get a faster autoloader.
     */
    public function make($opts = [
        'prefer-source' => false,
        'prefer-dist' => false,
        'no-dev' => false,
        'optimize-autoloader' => false,
    ])
    {
        $this->io()->section("Running dktl make");

        // Run composer install while passing the options.
        $composerInstall = $this->taskComposerInstall();
        $composerOptions = ['prefer-source', 'prefer-dist', 'no-dev', 'optimize-autoloader'];
        foreach ($composerOptions as $opt) {
            if ($opts[$opt]) {
                $composerInstall->option($opt);
            }
        }
        $composerInstall->run();

        // Symlink dirs from src into docroot.
        $this->addSymlinksToDrupalRoot();

        $this->io()->success("dktl make completed.");
    }
}
