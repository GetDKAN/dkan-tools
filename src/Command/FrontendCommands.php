<?php


namespace DkanTools\Command;

use Robo\Tasks;

class FrontendCommands extends Tasks
{
  /**
   * Install frontend app.
   *
   * @todo Should this be called 'make' instead of 'install'. Generally we are using make to mean 'get dependencies'.
   */
    public function frontendInstall()
    {
        $task = $this->taskExec("npm install")->dir("src/frontend");
        $result = $task->run();
        if ($result->getExitCode() != 0) {
            $this->io()->error('Could not install front-end node modules');
            return $result;
        }
        $this->io()->success('front-end installed.');
    }

  /**
   * Build frontend app.
   */
    public function frontendBuild()
    {
        $task = $this->taskExec("npm run build")->dir("src/frontend");
        $result = $task->run();
        if ($result->getExitCode() != 0) {
            $this->io()->error('could not build the front-end.');
            return $result;
        }
        $this->io()->success('front-end build.');
    }
}
