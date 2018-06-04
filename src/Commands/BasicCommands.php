<?php

namespace DkanTools\Commands;

use DkanTools\Util\Util;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class BasicCommands extends \Robo\Tasks
{
    /**
     * Test some things.
     */
    function test()
    {
        $this->say(Util::getDktlRoot());
    }

    /**
     * Initialize DKAN project directory.
     */
    function init()
    {
        $dktlRoot = Util::getDktlRoot();
        if (file_exists('dktl.yml') && file_exists('config')) {
            throw new \Exception("This project has already been initialized.");
            exit;
        }
        if (file_exists('dktl.yml')) {
            $this->io()->warning('The dktl.yml file already exists in this directory; skipping.');
        }
        else {
            // Create dktl.yml file
            $result = $this->taskWriteToFile('dktl.yml')
            ->textFromFile("$dktlRoot/assets/dktl.yml")
            ->run();
            if (file_exists('dktl.yml')) {
                $this->io()->success("dktl.yml file successfully initialized.");
            }
        }
        if (file_exists('config')) {
            $this->io()->warning('The config directory already exists in this directory; skipping.');
        }
        else {
            // Create makefile overrides
            $this->_mkdir('config');
            $result = $this->taskWriteToFile('config/drupal-override.make')
                ->textFromFile("$dktlRoot/assets/drush/template.make.yml")
                ->run();
            $result = $this->taskWriteToFile('config/dkan-override.make')
                ->textFromFile("$dktlRoot/assets/drush/template.make.yml")
                ->run();
            $result = $this->taskWriteToFile('config/contrib.make')
                ->textFromFile("$dktlRoot/assets/drush/template.make.yml")
                ->run();
            $this->_mkdir('config/modules/custom');
            if (file_exists('config')) {
                $this->io()->success("Config directory successfully initialized.");
            }
        }
    }
}
