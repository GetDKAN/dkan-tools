<?php


namespace DkanTools\Command;

use Robo\Tasks;

class FrontendCommands extends Tasks
{
  /**
   * Get frontend app.
   */
    public function frontendGet($branch = 'master')
    {
        $this->io()->section('Adding frontend application');

        $result = $this->taskExec('git clone')
            ->option('depth', '1')
            ->option('-b', $branch)
            ->arg('https://github.com/GetDKAN/data-catalog-frontend.git')
            ->arg('frontend')
            ->dir('src')
            ->run();
        if ($result->getExitCode() != 0) {
            $this->io()->error('Could not download front-end app.');
            if (file_exists('src/frontend')) {
                $this->io()->warning('src/frontend already exists.');
            }
            return $result;
        }

        if ($result && $result->getExitCode() === 0) {
            $this->io()->note(
                'Successfully downloaded data-catalog-frontend to /src/frontend'
            );
        }

        $this->frontendLink();
    }

    /**
     * Create symlink for src/frontend.
     */
    public function frontendLink()
    {
        $result = $this->taskExec('ln -s ../src/frontend data-catalog-frontend')
          ->dir("docroot")
          ->run();
        if ($result && $result->getExitCode() === 0) {
            $this->io()->success(
                'Successfully symlinked /src/frontend to docroot/data-catalog-frontend'
            );
        }

        $this->io()->note(
            'In order for the frontend to find the correct routes to work correctly,' .
            'you will need to enable the dkan_frontend module. ' .
            'Do this by running "dktl install" with the "--frontend" or "--demo" option as well, ' .
            'or else run "drush en dkan_frontend" after installation.'
        );

        $this->io()->note(
            'To save your customizations and make the frontend code part of your project, ' .
            'remove the "src/frontend/.git" file.'
        );
    }

  /**
   * Make frontend app.
   *
   * Get frontend dependencies.
   */
    public function frontendInstall()
    {
        $task = $this->taskExec("npm install")->dir("src/frontend");
        $result = $task->run();
        if ($result->getExitCode() != 0) {
            $this->io()->error('Could not install front-end node modules');
            return $result;
        }
        $this->io()->success('Front-end dependencies installed.');
    }

  /**
   * Build frontend app.
   *
   * Dataset content must exist before the dataset pages can be built.
   */
    public function frontendBuild()
    {
        $task = $this->taskExec("npm run build")->dir("src/frontend");
        $result = $task->run();
        if ($result->getExitCode() != 0) {
            $this->io()->error('Could not build the front-end.');
            return $result;
        }
        $this->io()->success('front-end build.');
    }
}
