<?php

namespace DkanTools\Test;

class DktlTest extends \PHPUnit\Framework\TestCase
{

    protected function setUp() : void
    {
        parent::setUp();
        `mkdir sandbox`;
    }

    public function testUninitialized()
    {
        `mkdir /tmp/sandbox`;
        $output = [];
        exec("cd /tmp/sandbox && dktl", $output);
        $this->assertContains(
            "DKTL is running outside of a DKTL project. Run dktl init in the project directory first.",
            $output
        );
    }

    public function testDktlGetWithBadParameter()
    {
        `cd sandbox && dktl init`;
        $output = [];
        exec("cd sandbox && dktl get foobar", $output);
        $this->assertContains(" [ERROR] version format not semantic.", $output);
    }

    public function testDktlGetDrupalVersionLessThanMinimum()
    {
        `cd sandbox && dktl init`;
        $output = [];
        exec("cd sandbox && dktl get 8.7.11", $output);
        $this->assertContains(" [ERROR] drupal version below minimal required.", $output);
    }

    public function testDktlGetNonExistentDrupalVersion()
    {
        `cd sandbox && dktl init`;
        $output = [];
        exec("cd sandbox && dktl get 77.7.7", $output);
        $this->assertContains('  Could not find package drupal/recommended-project with version 77.7.7.', $output);
        $this->assertContains(' [ERROR] could not run composer create-project.', $output);
    }

    public function testDktlGetSuccess()
    {
        `cd sandbox && dktl init`;
        $output = [];
        exec("cd sandbox && dktl get 9.0.0-beta2", $output);
        $this->assertContains(' [OK] composer project created.', $output);
    }

    public function testFromInitToSite()
    {
        $this->init();
        $this->get();
        $this->make();
        $this->install();
    }

    private function init()
    {
        `cd sandbox && dktl init`;
        $output = [];
        exec("ls sandbox", $output);
        $this->assertContains("dktl.yml", $output);
        $this->assertContains("src", $output);
        $this->assertNotContains("composer.json", $output);
        $this->assertNotContains("composer.lock", $output);
        $this->assertNotContains("docroot", $output);
        $this->assertNotContains("vendor", $output);
        $output = [];
        exec("ls sandbox/src", $output);
        $this->assertContains("command", $output);
        $this->assertContains("docker", $output);
        $this->assertContains("make", $output);
        $this->assertContains("modules", $output);
        $this->assertContains("script", $output);
        $this->assertContains("site", $output);
        $this->assertContains("tests", $output);
        $this->assertContains("themes", $output);
    }

    private function get()
    {
        `cd sandbox && dktl get 8.8.4`;
        $output = [];
        exec("ls sandbox", $output);
        $this->assertContains("composer.json", $output);
        $this->assertContains("composer.lock", $output);
        $this->assertContains("docroot", $output);
        $this->assertContains("vendor", $output);
        $output = [];
        exec("ls sandbox/docroot", $output);
        $this->assertContains("core", $output);
        $this->assertContains("modules", $output);
        $this->assertContains("modules", $output);
        $this->assertContains("sites", $output);
        $this->assertContains("themes", $output);
    }

    private function make()
    {
        `cd sandbox && dktl make`;
        $output = [];
        exec("ls sandbox/docroot/modules/contrib", $output);
        $this->assertContains("dkan2", $output);
    }

    private function install()
    {
        `cd sandbox && dktl install`;
        $output = [];
        exec("cd sandbox && dktl drush updb", $output);
        $this->assertContains(" [success] No pending updates.", $output);
    }

    protected function tearDown() : void
    {
        parent::tearDown();
        `cd sandbox && dktl dc kill`;
        `cd sandbox && dktl dc rm --force`;
        `rm -rf sandbox`;
    }
}
