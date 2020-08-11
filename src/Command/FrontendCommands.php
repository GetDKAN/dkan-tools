<?php


namespace DkanTools\Command;

use DkanTools\Util\Util;
use Robo\Tasks;

class FrontendCommands extends Tasks
{

    const FRONTEND_DIR = 'src/frontend';

    /**
    * Download the DKAN frontend app to src/frontend.
    * See https://www.npmjs.com/package/@civicactions/data-catalog-react
    *
    * @param mixed $version
    *   Version of frontend catalog to download. Defaults to "latest".
    */
    public function frontendGet($version = null)
    {
        if (file_exists(self::FRONTEND_DIR)) {
            throw new \Exception(self::FRONTEND_DIR . ' already exists.');
        }

        if (!$version) {
            $version = $this->getFrontendVersion();
        }

        $this->io()->section('Downloading frontend application');

        Util::prepareTmp();

        $result = $this->taskExec("npm pack")
            ->arg("@civicactions/data-catalog-react@$version")
            ->dir(Util::TMP_DIR)
            ->printOutput(false)
            ->run();

        if ($result->getExitCode() != 0) {
            throw new \Exception('Could not download front-end app.');
        }

        $frontendArchive = $result->getMessage();

        $this->taskExtract(Util::TMP_DIR . '/' . $frontendArchive)
            ->to(self::FRONTEND_DIR)
            ->run();
        $this->frontendLink();
        Util::cleanupTmp();
    }

    /**
     * Determine frontend version based on DKAN.
     *
     * Starting with DKAN 2.3.x, the DKAN composer.json file specifies a
     * version for the decoupled frontend app, using the format:
     *
     * "extra": {
     *     "dkan-frontend": {
     *         "data-catalog-react":"0.2.0"
     *     }
     * }
     */
    private function getFrontendVersion()
    {
        if (!file_exists('docroot') && !file_exists('docroot/core')) {
            throw new \Exception("You must have a drupal codebase in docroot.");
        }

        $result = $this->taskComposerConfig()
            ->arg('extra.dkan-frontend.data-catalog-react')
            ->dir('docroot/modules/contrib/dkan')
            ->printOutput(false)
            ->run();
        if (!$result->getMessage()) {
            $this->io()->note("Frontend version not found; defaulting to latest.");
            return "latest";
        }
        return $result->getMessage();
    }


    /**
    * Create symlink for src/frontend.
    */
    private function frontendLink()
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
    * Download frontend app if not present, and run npm install.
    *
    * Get frontend dependencies.
    */
    public function frontendInstall($version = null)
    {
        if (!file_exists(self::FRONTEND_DIR)) {
            $this->frontendGet($version);
        }
        $result = $this->taskExec("npm install")
            ->dir("src/frontend")
            ->run();
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
        if (!$this->frontendModulePresent()) {
            $result = $this->taskExec("../vendor/bin/drush en -y frontend")->dir("docroot")->run();
            if ($result->getExitCode() != 0) {
                $this->io()->error('Could not install front-end node module');
                return $result;
            }
            $this->taskExec("../vendor/bin/drush config-set system.site page.front \"/home\" -y")
                ->dir("docroot")
                ->run();
            $this->io()->success('Enabled DKAN frontend module.');
        }

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

    private function frontendModulePresent() {
        return false;
    }
}
