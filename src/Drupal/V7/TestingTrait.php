<?php
namespace DkanTools\Drupal\V7;
use DkanTools\Util\Util;

/**
 * Test implementation helpers
 */

trait TestingTrait
{
    /**
     * Initialize test folders and install dependencies in directory.
     */
    protected function testingInstallDependencies($dir) {
        if (!file_exists($dir . '/vendor')) {
            $this->io()->section('Installing test dependencies in ' . $dir);
            $this->taskExec('composer install --prefer-source --no-interaction')
                ->dir($dir)
                ->run();
        }
    }

    /**
     * Initialize test subdirectories
     */
    protected function testingInitDirs($dir) {
        if (!file_exists($dir . '/assets')) {
            $this->io()->section('Creating test subdirectories in ' . $dir);
            $this->_mkdir($dir . '/assets/junit');
        }
    }

    /**
     * Establish links from a test environment to an environment with installed
     * test dependencies.
     */
    protected function testingLinkEnv($src_dir, $dest_dir) {
        $this->io()->section('Linking test environment ' . $dest_dir . ' to ' . $src_dir);
        $this->_mkdir($dest_dir . '/bin');
        $this->_symlink('../../../' . $src_dir . '/bin/behat', $dest_dir . '/bin/behat');
        $this->_symlink('../../../' . $src_dir . '/bin/phpunit', $dest_dir . '/bin/phpunit');
        $this->_symlink('../../' . $src_dir . '/vendor', $dest_dir . '/vendor');
    }
    

    /**
     * Helper function to run Behat tests in a particular directory.
     * 
     * @param string $dir test directory
     * @param string $suite name of the test suite to run
     * @param array $args additional arguments to pass to behat.
     */
    protected function testingBehat($dir, $suite, array $args)
    {
        $files = array($dir . '/behat.yml', $dir . '/behat.docker.yml');
        Util::ensureFilesExist($files, 'Behat config file');
        $this->testInit();
        $behatExec = $this->taskExec('bin/behat')
            ->dir($dir)
            ->arg('--colors')
            ->arg('--suite=' . $suite)
            ->arg('--format=pretty')
            ->arg('--out=std')
            ->arg('--format=junit')
            ->arg('--out=assets/junit')
            ->arg('--config=behat.docker.yml');

        foreach ($args as $arg) {
            $behatExec->arg($arg);
        }
        return $behatExec->run();
    }

    /**
     * Helper function to run PHPUnit tests in a particular directory.
     * 
     * @param string $dir test directory
     * @param array $args additional arguments to pass to PHPUnit.
     */
    protected function testingPhpunit($dir, array $args)
    {
        $files = array($dir . '/phpunit/phpunit.xml');
        Util::ensureFilesExist($files, 'PhpUnit config file');
        $this->testInit();
        $phpunitExec = $this->taskExec('bin/phpunit --verbose')
            ->dir($dir)
            ->arg('--configuration=phpunit');

        foreach ($args as $arg) {
            $phpunitExec->arg($arg);
        }
        return $phpunitExec->run();
    }
}
