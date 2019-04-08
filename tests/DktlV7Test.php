<?php



class DktlV7Test extends \PHPUnit\Framework\TestCase
{

  protected function setUp()
  {
      parent::setUp();
      putenv("DRUPAL_VERSION=V7");
      `mkdir sandbox`;
  }

    public function testUninitialized() {
      $output = [];
      exec("cd sandbox && dktl", $output);
      $this->assertEquals("DKTL is running outside of a DKTL project. Run dktl init in the project directory first.",
          $output[0]);
  }

  public function testFromInitToSite() {
      // Initialize project
      $output = [];
      exec("cd sandbox && dktl init");
      exec("ls sandbox", $output);
      $this->assertEquals("dktl.yml", $output[0]);
      $this->assertEquals("src", $output[1]);

      // Get DKAN.
      $output = [];
      exec("cd sandbox && dktl dkan:get 7.x-1.16.8");
      exec("ls sandbox", $output);
      $this->assertEquals("dkan", $output[0]);

      // Make the project.
      exec("cd sandbox && dktl make");
      $output = [];
      exec("ls sandbox", $output);
      $this->assertEquals("docroot", $output[2]);
      $output = [];
      exec("ls sandbox/dkan/modules", $output);
      $this->assertEquals("contrib", $output[0]);

      // Install Drupal.
      exec("cd sandbox && dktl install");
      $output = [];
      exec("cd sandbox && dktl drush updb", $output);

      $this->assertContains("No database updates required", $output[0]);
  }

  protected function tearDown()
  {
      parent::tearDown();
      `cd sandbox && dktl dc kill`;
      `cd sandbox && dktl dc rm --force`;
      `rm -rf sandbox`;
  }

}