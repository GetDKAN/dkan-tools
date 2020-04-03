<?php


namespace DkanTools;

use DkanTools\Util\Util;
use Symfony\Component\Filesystem\Filesystem;

  /**
   * This is project's console commands configuration for Robo task runner.
   *
   * @see http://robo.li/
   */
class BasicCommands extends \Robo\Tasks
{

    const DRUPAL_MIN_VERSION = "8.8";
    const DRUPAL_FOLDER_NAME = "docroot";

  /**
   * Get drupal/recommended-project's composer files.
   *
   * We get both Drupal and DKAN on the make step, using composer.
   *
   * @param string $drupalVersion
   *   Drupal semantic version, i.e. 8.8.4 or 9.0.0-beta1
   */
    public function get(string $drupalVersion)
    {
        $this->io()->section("Running dktl get");

        // Validate version is semantic and at least DRUPAL_MIN_VERSION.
        $this->validateVersion($drupalVersion);
        Util::prepareTmp();

        // Composer's create-project requires an empty folder, so run it in
        // Util::Tmp, then move the 2 composer files back into project root.
        $this->composerDrupalOutsideProjectRoot($drupalVersion);
        $this->moveComposerFilesToProjectRoot();

        // Modify project's scaffold and installation paths to `docroot`, then
        // install Drupal in it.
        $this->modifyComposerPaths();
        $this->taskComposerInstall()->run();

        Util::cleanupTmp();
        $this->io()->success("dktl get completed.");
    }

    private function validateVersion(string $version)
    {
        // Verify against semver.org's regex here:
        // https://semver.org/#is-there-a-suggested-regular-expression-regex-to-check-a-semver-string
        $semVerRegex = "^(?P<major>0|[1-9]\d*)\.(?P<minor>0|[1-9]\d*)\.(?P" .
        "<patch>0|[1-9]\d*)(?:-(?P<prerelease>(?:0|[1-9]\d*|\d*[a-zA-Z-]" .
        "[0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))" .
        "?(?:\+(?P<buildmetadata>[0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$";

        if (!preg_match("#{$semVerRegex}#", $version, $matches)) {
            $this->io()->error("version format not semantic.");
            exit;
        }
        if (version_compare($version, self::DRUPAL_MIN_VERSION, "<")) {
            $this->io()->error("drupal version below minimal required.");
            exit;
        }
        $this->io()->success(
            "semantic version validated and >= " . self::DRUPAL_MIN_VERSION . "."
        );
    }

    private function composerDrupalOutsideProjectRoot(string $version)
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

    private function moveComposerFilesToProjectRoot()
    {
        $moveFiles = $this->taskFilesystemStack()
        ->rename(
            Util::TMP_DIR . "/composer.json",
            Util::getProjectDirectory() . "/composer.json",
            true
        )
        ->rename(
            Util::TMP_DIR . "/composer.lock",
            Util::getProjectDirectory() . "/composer.lock",
            true
        )
        ->run();
        if ($moveFiles->getExitCode() != 0) {
            $this->io()->error('could not move composer files.');
            exit;
        }
        $this->io()->success('composer.json and composer.lock moved to project root.');
    }

    private function modifyComposerPaths()
    {
        $regexps = "s#web/#" . self::DRUPAL_FOLDER_NAME . "/#g";
        $installationPaths = $this
        ->taskExec("sed -i -E '{$regexps}' composer.json")
        ->run();
        if ($installationPaths->getExitCode() != 0) {
            $this->io()->error('could not Unable to modifying composer.json paths.');
            exit;
        }
        $this->io()->success('composer installation paths modified.');
    }
}
