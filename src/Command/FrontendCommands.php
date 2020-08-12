<?php


namespace DkanTools\Command;

use DkanTools\Util\Util;
use Robo\Tasks;
use Symfony\Component\Console\Input\InputOption;

class FrontendCommands extends Tasks
{

    const FRONTEND_DIR = 'src/frontend';
    const FRONTEND_VCS_URL = 'https://github.com/GetDKAN/data-catalog-react/';
    const FRONTEND_VCS_REF = 'master';

    /**
    * Download the DKAN frontend app to src/frontend.
    *
    * If no url or ref are provided, DKAN Tools will probe DKAN's composer.json
    * file, looking for the following configuration:
    *
    * "extra": {
    *     "dkan-frontend": {
    *         "@civicactions/data-catalog-react":"0.2.0"
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

    private function frontendGetPopulateDefaults(&$opts)
    {
        if ($opts['url'] && $opts['ref']) {
            return;
        }
        $result = $this->taskComposerConfig()
            ->arg('extra.dkan-frontend')
            ->dir('docroot/modules/contrib/dkan')
            ->printOutput(false)
            ->run();
        if (!$result->getMessage() || !is_object(json_decode($result->getMessage()))) {
            $this->io()->note("Frontend info not found in DKAN, using DKAN-tools defaults.");
            $defaults = ['url' => self::FRONTEND_VCS_URL, 'ref' => self::FRONTEND_VCS_REF ];
        } else {
            $dkanFrontend = json_decode($result->getMessage());
            $defaults = ['url' => $dkanFrontend->url, 'ref' => $dkanFrontend->ref];
        }
        foreach (['url', 'ref'] as $opt) {
            if (!$opts[$opt]) {
                $opts[$opt] = $defaults[$opt];
            }
        }
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
    */
    public function frontendInstall()
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
            ->taskExec('npm run build')
            ->dir("src/frontend");
        $result = $task->run();
        if ($result->getExitCode() != 0) {
            $this->io()->error('Could not build the front-end.');
            return $result;
        }
        $this->io()->success('front-end build.');
    }

    private function frontendModulePresent()
    {
        return false;
    }

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
