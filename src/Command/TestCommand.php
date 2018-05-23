<?php
namespace DkanTools\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class TestCommand extends Command
{
  // protected static $defaultName = 'app:test';

  protected function configure()
  {
    $this
      ->setName('test')
      ->setDescription('Tests the app.')
      ->setHelp('This command is just a test')
      ->addArgument('thing')
    ;
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $things = $input->getArgument('thing');
    // $thingstring = implode(' ', $things);
    $output->writeln("Yay $things");
  }
}
