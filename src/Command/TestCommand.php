<?php
namespace DkanTools\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TestCommand extends Command
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
    echo "yay!";
  }
}
