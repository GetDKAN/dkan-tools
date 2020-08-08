<?php


namespace DkanTools\Command;

use Robo\Tasks;

class FrontendCommands extends Tasks
{
  /**
   * Get frontend app.
   */
    public function frontendGet($repo = 'https://github.com/GetDKAN/data-catalog-react.git', $branch = 'master')
    {
        $this->io()->section('Adding frontend application');

        $a = explode('/', $repo);
        $name = str_replace('.git', '', end($a));

        $result = $this->taskExec('git clone')
            ->option('depth', '1')
            ->option('-b', $branch)
            ->arg($repo)
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
                'Successfully downloaded ' . $name . ' to /src/frontend'
            );
        }

        $this->frontendLink();
    }

    /**
     * Create symlink for src/frontend.
     */
    public function frontendLink()
    {
        $result = $this->taskExec('ln -s ../src/frontend frontend')
          ->dir("docroot")
          ->run();
        if ($result && $result->getExitCode() === 0) {
            $this->io()->success(
                'Successfully symlinked /src/frontend to docroot/frontend'
            );
        }

        $this->io()->note(
            'In order for the frontend to find the correct routes to work correctly,' .
            'you will need to enable the dkan frontend module. ' .
            'Do this by running "dktl install" with the "--frontend" or "--demo" option as well, ' .
            'or else run "drush en frontend" after installation.'
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
        // Override GATSBY_API_URL with our own proxed domain.
        $task = $this
            ->taskExec('DYNAMIC_API_URL="/api/1" GATSBY_API_URL="http://$DKTL_PROXY_DOMAIN/api/1" npm run build')
            ->dir("src/frontend");
        $result = $task->run();
        if ($result->getExitCode() != 0) {
            $this->io()->error('Could not build the front-end.');
            return $result;
        }
        $this->io()->success('front-end build.');
    }
}
