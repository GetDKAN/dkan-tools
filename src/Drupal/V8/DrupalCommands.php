<?php
namespace DkanTools\Drupal\V8;

use DkanTools\Util\Util;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class DrupalCommands extends \Robo\Tasks
{

    /**
     * Get the mysql connection string.
     *
     * @todo Stop hardcoding and get from env or make dynamic.
     */
    public function getDbUrl()
    {
        return 'mysql://drupal:123@db/drupal';
    }

    /**
     * Remove all gitignores from docroot.
     */
    public function drupalRemoveGitIgnores() {
        $gitignores = [];
        exec("find docroot -type f -name '.gitignore'", $gitignores);

        foreach ($gitignores as $gitignore) {
            `rm {$gitignore}`;
            $this->io()->note("Removing: {$gitignore}");
        }

        $gits = [];
        exec("find docroot -type d -name '.git'", $gits);

        foreach ($gits as $git) {
            `rm -R {$git}`;
            $this->io()->note("Removing: {$git}");
        }
    }
}
