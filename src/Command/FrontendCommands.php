<?php


namespace DkanTools\Command;

use DkanTools\Util\Util;
use Robo\Tasks;

class FrontendCommands extends Tasks
{

    const FRONTEND_DIR = 'src/frontend';

    /**
    * Get frontend app.
    */
    public function frontendGet($version = 'master')
    {
        if (file_exists(self::FRONTEND_DIR)) {
            throw new \Exception(self::FRONTEND_DIR . ' already exists.');
        }

        $archiveUrl = 'https://github.com/GetDKAN/data-catalog-react';
        $archiveUrl .= "/archive/{$version}.zip";

        $this->io()->section('Downloading frontend application');

        Util::prepareTmp();
        $dest = Util::TMP_DIR . "/frontend.zip";

        $result = $this->taskExec("wget -O $dest $archiveUrl")
            ->run();
        if ($result->getExitCode() != 0) {
            throw new \Exception('Could not download front-end app.');
        }
        $this->taskExtract(Util::TMP_DIR . '/frontend.zip')
            ->to(self::FRONTEND_DIR)
            ->run();
        $this->frontendLink();
        Util::cleanupTmp();
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
