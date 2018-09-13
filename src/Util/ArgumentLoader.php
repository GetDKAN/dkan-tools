<?php

namespace DkanTools\Util;

use Symfony\Component\Yaml\Yaml;

class ArgumentLoader
{
    private $argv;

    public function __construct($argv) {
        $this->argv = $argv;

    }

    public function getCommand() {
        $command = "";
        if (count($this->argv) >= 2) {
            $command = $this->argv[1];
        }
        return $command;
    }

    public function getAlteredArgv() {
        $project_directory = Util::getProjectDirectory();
        $argv = $this->argv;
        $command = $this->getCommand();

        if (!empty($command) && $this->onlyCommandGiven() && file_exists("{$project_directory}/dktl.yml")) {
            $yamld_command = $command;
            $config = Yaml::parse(file_get_contents("{$project_directory}/dktl.yml"));

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