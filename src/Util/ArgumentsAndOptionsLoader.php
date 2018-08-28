<?php

namespace DkanTools\Util;

use Symfony\Component\Yaml\Yaml;

class ArgumentsAndOptionsLoader
{
    private $argv;

    public function __construct($argv) {
        $this->argv = $argv;

    }

    public function getCommand() {
        $command = "";
        if (count($this->argv) >= 2) {
            print_r($this->argv);
            $command = $this->argv[1];
        }
        return $command;
    }

    public function enhancedArgv() {
        $argv = $this->argv;
        $command = $this->getCommand();

        if (!empty($command) && $this->onlyCommandGiven()) {
            $yamld_command = str_replace(":", "|", $command);
            $config = Yaml::parse(file_get_contents("/var/www/dktl.yml"));
            print_r($config);
            if (isset($config[$yamld_command])) {
                $argv = array_merge($argv, array_values($config[$yamld_command]));
            }
        }
        return $argv;
    }

    private function onlyCommandGiven() {
        return count($this->argv) == 2;
    }

}