<?php


namespace DkanTools\Command;

use DkanTools\Util\Util;
use Robo\Tasks;
use Symfony\Component\Console\Input\InputOption;

class FrontendCommands extends Tasks
{

    const FRONTEND_DIR = 'src/frontend';
    const FRONTEND_VCS_URL = 'https://github.com/GetDKAN/data-catalog-app/';
    const FRONTEND_VCS_REF = 'master';
    const FRONTEND_THEME = 'getdkan/dkan_js_frontend_bartik';

    /**
    * Download the DKAN frontend app to src/frontend.
    *
    * If no url or ref are provided, DKAN Tools will probe DKAN's composer.json
    * file, looking for the following configuration:
    *
    * "extra": {
    *     "dkan-frontend": {
    *       "type": "vcs",
    *       "url": "https://github.com/GetDKAN/data-catalog-app",
    *       "ref": "1.0.0"
    *     }
    * }
    *
    * If not found, the defaults set in self::FRONTEND_VCS_URL and
    * self::FRONTEND_VCS_REF will be used.
    *
    * @option string type
    *   The type of frontend package. "vcs" currently the only type supported.
    * @option string url
    *   URL for the frontend package. Currently only github URLs supported.
    * @option string ref
    *   Reference (tag, branch or commit) from the vcs system to use.
    *
    */
    public function frontendGet($opts = ['type' => 'vcs', 'url' => null, 'ref' => null])
    {
        if (file_exists(self::FRONTEND_DIR)) {
            throw new \Exception(self::FRONTEND_DIR . ' already exists.');
        }
        if ($opts['type'] != 'vcs') {
            throw new \Exception('Only vcs is currently supported for type');
        }
        $this->frontendGetPopulateDefaults($opts);

        $this->io()->section('Downloading frontend application');

        $archiveUrl = $this->getVcsArchiveUrl($opts['url'], $opts['ref']);
        $filename = pathinfo(parse_url($archiveUrl)['path'])['basename'];
        $this->io()->text("Downloading frontend app from $archiveUrl");

        Util::prepareTmp();

        $result = $this->taskExec("wget $archiveUrl")->dir(Util::TMP_DIR)->run();
        if ($result->getExitCode() != 0) {
            throw new \Exception('Could not download front-end app.');
        }
        $this->taskExtract(Util::TMP_DIR . "/$filename")->to(self::FRONTEND_DIR)->run();
        Util::cleanupTmp();
    }

    /**
     * Populate the $opts array from frontend:get with url and ref from DKAN
     * or else from defaults
     *
     * @param array $opts
     *   An $opts array from the frontend:get command. Modified directly as
     *   reference.
     */
    private function frontendGetPopulateDefaults(&$opts)
    {
        if ($opts['url'] && $opts['ref']) {
            return;
        }
        $defaults = ['url' => self::FRONTEND_VCS_URL, 'ref' => self::FRONTEND_VCS_REF ];
        $note = "Frontend config not found in DKAN composer.json. Reverting to "
            . "defaults from DKAN Tools";

        $result = $this->taskComposerConfig()
            ->arg('extra.dkan-frontend')
            ->dir('docroot/modules/contrib/dkan')
            ->printOutput(false)
            ->run();

        if (is_object(json_decode($result->getMessage()))) {
            $dkanFrontend = json_decode($result->getMessage());
            $defaults = ['url' => $dkanFrontend->url, 'ref' => $dkanFrontend->ref];
            $note = "Using DKAN composer.json settings for frontend repo. "
                . "Ref $dkanFrontend->ref from $dkanFrontend->url.";
        }

        $this->io()->note($note);
        $opts['url'] = $opts['url'] ? $opts['url'] : $defaults['url'];
        $opts['ref'] = $opts['ref'] ? $opts['ref'] : $defaults['ref'];
    }

    /**
     * Given a github (sorry no other vcs service supported for now), produce a
     * URL for an archive.
     *
     * @param string $url
     *   A github URL.
     * @param string $ref
     *   A commit, tag or branch.
     *
     * @return string
     */
    private function getVcsArchiveUrl($url, $ref)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \Exception('Invalid URL');
        }

        $urlparts = parse_url($url);
        $host = $urlparts['host'];
        $scheme = $urlparts['scheme'];

        $pathparts = explode('/', substr($urlparts['path'], 1));
        $user = $pathparts[0];
        $repo = basename($pathparts[1], '.git');

        return "$scheme://$host/$user/$repo/archive/$ref.zip";
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
    * The URL and branch/tag for the frontend app should be specified in the
    * "extra" section of DKAN's composer.json. If you want to specify a
    * different tag or branch, or different repo entirely, run "dktl
    * frontend:get" first and specify the --ref and/or --url options.
    *
    * @param array $opts
    *   Options array.
    * @option theme
    *   Composer requirement for theme to download and enable.
    *   Pass --no-theme to skip theme installation. Defaults to
    *   "getdkan/dkan_js_frontend_bartik".
    */
    public function frontendInstall($opts = ['theme' => true])
    {
        if (!file_exists(self::FRONTEND_DIR)) {
            $this->frontendGet();
        }
        if (!file_exists("docroot/frontend")) {
            $this->frontendLink();
        }
        $result = $this->taskExec("npm install")
            ->dir("src/frontend")
            ->run();
        if ($result->getExitCode() != 0) {
            $this->io()->error('Could not install front-end node modules');
            return $result;
        }
        $this->io()->success('Front-end dependencies installed.');
        $result = $this->taskExec("drush en -y dkan_js_frontend")->dir("docroot")->run();
        if ($result->getExitCode() != 0) {
            $this->io()->error('Could not install front-end node module');
            return $result;
        }
        
        $this->installTheme($opts['theme']);
        
        $this->taskExec("drush config-set system.site page.front \"/home\" -y")->run();
        $this->io()->success('Set front page.');
    }

    private function installTheme($theme)
    {
        if ($theme === true) {
            $theme = self::FRONTEND_THEME;
        }
        if (!$theme) {
            $this->io()->success("Skipping theme installation.");
            return true;
        }
        $dependency = explode(":", $theme);
        $result = $this->taskComposerRequire()
            ->dependency($dependency[0], ($themeParts[1] ?? null))
            ->run();
        if ($result->getExitCode() != 0) {
            $this->io()->error("Failed to download theme $theme.");
            return $result;
        }

        $themeNameParts = explode("/", $dependency);
        $themeName = $themeNameParts[1] ?? $themeNameParts[0];

        $result = $this->taskExec("drush theme:enable $themeName")->run();
        if ($result->getExitCode() != 0) {
            $this->io()->error("Could not enable theme $themeName.");
            return $result;
        }

        $this->taskExec("drush config-set system.theme default $theme -y")->run();
        $this->io()->success("Successfully installed and enabled front-end theme $themeName.");
        return $result;
    }

    /**
     * Build frontend app.
     *
     * Dataset content must exist before the dataset pages can be built.
     */
    public function frontendBuild()
    {
        // Override GATSBY_API_URL with our own proxied domain.
        $task = $this
            ->taskExec('npm run build')
            ->dir("src/frontend");
        $result = $task->run();
        if ($result->getExitCode() != 0) {
            $this->io()->error('Could not build the front-end.');
            return $result;
        }
        $this->io()->success('Frontend build successful.');
    }

    private function frontendModulePresent()
    {
        return false;
    }

    /**
     * Run cypress tests on the frontend app.
     */
    public function frontendTest()
    {
        $this->taskExec("npm install cypress")
        ->dir("docroot/frontend")
        ->run();

        return $this->taskExec('CYPRESS_baseUrl="http://$DKTL_PROXY_DOMAIN" npx cypress run')
            ->dir("docroot/frontend")
            ->run();
    }
}
