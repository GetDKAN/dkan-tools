<?php

namespace DkanTools\Test;

class DktlTest extends \PHPUnit\Framework\TestCase
{

    protected function setUp() : void
    {
        parent::setUp();
        // @Todo: Try to remove.
        putenv("DRUPAL_VERSION=V8");
        `mkdir sandbox`;
    }

    public function testUninitialized()
    {
        $output = [];
        exec("cd sandbox && dktl", $output);
        $this->assertEquals(
            "DKTL is running outside of a DKTL project. Run dktl init in the project directory first.",
            $output[0]
        );
    }

    public function testDktlGetWithBadParameter()
    {
        $output = [];
        `cd sandbox && dktl init`;
        exec("cd sandbox && dktl get foobar", $output);
        $this->assertContains(
            " [ERROR] Parameter invalid: requires semantic version.",
            $output
        );
    }

    public function testDktlGetDrupalVersionLessThanMinimum()
    {
        $output = [];
        `cd sandbox && dktl init`;
        exec("cd sandbox && dktl get 8.7.11", $output);
        $this->assertContains(
            " [ERROR] Drupal version below minimal required.",
            $output
        );
    }

    public function testDktlGetNonExistentDrupalVersion()
    {
        $output = [];
        `cd sandbox && dktl init`;
        exec("cd sandbox && dktl get 77.7.7", $output);
        $this->assertContains(
            '  Could not find package drupal/recommended-project with version 77.7.7.',
            $output
        );
        $this->assertContains(
            ' [ERROR] Error running composer create-project.',
            $output
        );
    }

    public function testDktlGetSuccess()
    {
      $output = [];
      `cd sandbox && dktl init`;
      exec("cd sandbox && dktl get 9.0.0-beta2", $output);
      $this->assertContains(
        ' [OK] Created composer project.',
        $output
      );
    }

    public function testFromInitToSite()
    {
        // Initialize project
        $output = [];
        exec("cd sandbox && dktl init");
        exec("ls sandbox", $output);
        $this->assertEquals("dktl.yml", $output[0]);
        $this->assertEquals("src", $output[1]);

        // Get DKAN.
        $output = [];
        exec("cd sandbox && dktl get 8.6.13");
        exec("ls sandbox", $output);
        $this->assertEquals("docroot", $output[1]);

        // Make the project.
        exec("cd sandbox && dktl make");
        $output = [];
        exec("ls sandbox/docroot/profiles/contrib", $output);
        $this->assertEquals("dkan2", $output[0]);

        // Install Drupal.
        exec("cd sandbox && dktl install");
        $output = [];
        exec("cd sandbox && dktl drush updb", $output);

        $this->assertContains("No database updates required", $output[0]);
    }

    protected function tearDown() : void
    {
        parent::tearDown();
        `cd sandbox && dktl dc kill`;
        `cd sandbox && dktl dc rm --force`;
        `sudo rm -rf sandbox`;
    }
}
