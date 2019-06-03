<?php

namespace DkanTools\Drupal\V8;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class UpdateCommands extends \Robo\Tasks
{

  /**
   * Update drush in the cli container to drush 9.
   */
  public function updateDrush9() {
    $this->_exec("rm -rf /root/.composer/vendor");
    $this->_exec("rm -rf /root/.composer/composer.lock");
    $this->_exec("rm -rf /root/.composer/composer.json");
    $this->_exec("composer global require drush/drush:9.5.2");
  }
}
