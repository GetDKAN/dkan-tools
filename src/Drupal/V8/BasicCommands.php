<?php

namespace DkanTools\Drupal\V8;

use DkanTools\Util\Util;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class BasicCommands extends \Robo\Tasks
{
    /**
     * Get DKAN and Drupal.
     */
    public function get()
    {
        $dktlRoot = Util::getDktlDirectory();

        $assets = ["composer.json"];

        foreach ($assets as $asset) {
            $f = "./{$asset}";
            $result = $this->taskWriteToFile($f)
                ->textFromFile("$dktlRoot/assets/d8/{$asset}")
                ->run();
            Util::directoryAndFileCreationCheck($result, $f, $this->io());
        }

        $this->_exec("dktl composer install");
    }
}
