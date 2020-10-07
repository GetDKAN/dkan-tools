<?php

namespace DkanTools;

use DkanTools\Util\Util;

/**
 * Methods and variables for Drupal project setup.
 */
trait DrupalProjectTrait
{
    /**
     * Define min acceptable Drupal version.
     *
     * @var string
     */
    private static $drupalMinVersion = '8.8';


    /**
     * Define docroot (public) dir.
     *
     * @var string
     */
    private static $drupalDocroot = 'docroot';

    private function drupalProjectCreate(string $version)
    {
        $createFiles = $this->taskComposerCreateProject()
            ->source("drupal/recommended-project:{$version}")
            ->target(Util::TMP_DIR)
            ->noInstall()
            ->run();
        if ($createFiles->getExitCode() != 0) {
            $this->io()->error('could not run composer create-project.');
            exit;
        }
        $this->io()->success('composer project created.');
    }

    /**
     * Move composer.json and .lock back to project dir.
     */
    private function drupalProjectMoveComposerFiles()
    {
        if (file_exists(Util::getProjectDirectory() . "/composer.json")) {
            $override = $this->confirm('composer.json already exists, replace?');
            if (!$override) {
                $this->io()->warning('Skipping composer.json');
                return;
            }
        }

        $moveFiles = $this->taskFilesystemStack()
            ->rename(
                Util::TMP_DIR . "/composer.json",
                Util::getProjectDirectory() . "/composer.json",
                true
            )
            ->run();
        if ($moveFiles->getExitCode() != 0) {
            $this->io()->error('could not move composer files.');
            exit;
        }
        $this->io()->success('composer.json and composer.lock moved to project root.');
    }

    /**
     * Rewrite composer.json with correct docroot.
     */
    private function drupalProjectSetDocrootPath()
    {
        $regexps = "s#web/#" . self::$drupalDocroot . "/#g";
        $installationPaths = $this->taskExec("sed -i -E '{$regexps}'")
            ->arg('composer.json')
            ->run();
        if ($installationPaths->getExitCode() != 0) {
            $this->io()->error('could not Unable to modifying composer.json paths.');
            exit;
        }
        $this->io()->success('composer installation paths modified.');
    }

    /**
     * Validate the Drupal version provided.
     *
     * @param string $version
     *   Drupal semantic version (e.g. "8.9.2", "9")
     * @return bool
     *
     * @todo Use some more standard composer validation here.
     */
    private function drupalProjectValidateVersion(string $version)
    {
        // Verify against semver.org's regex here:
        // https://semver.org/#is-there-a-suggested-regular-expression-regex-to-check-a-semver-string
        $semVerRegex = "^(?P<major>0|[1-9]\d*)\.(?P<minor>0|[1-9]\d*)\.(?P" .
        "<patch>0|[1-9]\d*)(?:-(?P<prerelease>(?:0|[1-9]\d*|\d*[a-zA-Z-]" .
        "[0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))" .
        "?(?:\+(?P<buildmetadata>[0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$";

        if (!preg_match("#{$semVerRegex}#", $version, $matches)) {
            $this->io()->error("version format not semantic.");
            return false;
        }
        if (version_compare($version, self::$drupalMinVersion, "<")) {
            $this->io()->error("drupal version below minimal required.");
            return false;
        }
        $this->io()->success(
            "semantic version validated and >= " . self::$drupalMinVersion . "."
        );
        return true;
    }
}
