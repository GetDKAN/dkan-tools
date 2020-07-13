<?php

namespace DkanTools\Util;

/**
 * Test implementation helpers
 */

trait TestingTrait
{
    /**
     * Initialize test folders and install dependencies in directory.
     */
    protected function testingInstallDependencies($dir)
    {
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
    protected function testingInitDirs($dir)
    {
        if (!file_exists($dir . '/assets')) {
            $this->io()->section('Creating test subdirectories in ' . $dir);
            $this->_mkdir($dir . '/assets/junit');
        }
    }

    /**
     * Establish links from a test environment to an environment with installed
     * test dependencies.
     */
    protected function testingLinkEnv($src_dir, $dest_dir)
    {
        if (!file_exists($dest_dir . '/bin')) {
            $this->io()->section('Linking test environment ' . $dest_dir . ' to ' . $src_dir);
            $this->_mkdir($dest_dir . '/bin');
            $this->_symlink('../../../' . $src_dir . '/bin/phpunit', $dest_dir . '/bin/phpunit');
            $this->_symlink('../../' . $src_dir . '/vendor', $dest_dir . '/vendor');
            $this->_symlink('../../' . $src_dir . '/dkanextension', $dest_dir . '/dkanextension');
        }
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
        $phpunitExec = $this->taskExec('bin/phpunit --verbose')
            ->dir($dir)
            ->arg('--configuration=phpunit');

        foreach ($args as $arg) {
            $phpunitExec->arg($arg);
        }
        return $phpunitExec->run();
    }
}
