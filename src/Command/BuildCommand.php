<?php

namespace DkanTools\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class BuildCommand extends Command
{
  // protected static $defaultName = 'app:test';

  protected function configure()
  {
    $this
      ->setName('test')
      ->setDescription('Tests the app.')
      ->setHelp('This command is just a test')
    ;
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
      if (file_exists("docroot")) {
        $output->writeln("Removing docroot");
        `rm -rf docroot`;
      }
      // passthru("php /dktl/drupal-get.php");
      // passthru("php /dktl/dkan-get.php");
      // passthru("php /dktl/drupal-dkan-connect.php");
      // passthru("php /dktl/dkan-make.php");
      // passthru("php /dktl/drupal-contrib-make.php");
      // passthru("php /dktl/customize-copy.php");
      // passthru("php /dktl/customize-patch.php");

  }
}
