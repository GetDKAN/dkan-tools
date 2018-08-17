# DKAN Tools

This CLI application provides tools for implementing and developping [DKAN](https://getdkan.org/), the Drupal-based open data portal.

## Requirements

This tool currently only supports [Docker](https://www.docker.com/)-based local development environments. In the future it will be expanded to support a local webserver and database setup. Current requirements are simply:

* Bash-like shell that can execute .sh files (Linux or OS X terminals should all work)
* [Docker](https://www.docker.com/get-docker)
* [Docker Compose](https://docs.docker.com/compose/)
* PHP 7.0 and Composer. (This requirement soon to be optional.)

That's it! All other dependencies are included in the Docker containers that dkan-tools will create.

## Installation

1. Download or clone this repository into any location on your development machine.
2. Create a symbolic link anywhere in your [PATH](http://www.linfo.org/path_env_var.html) (type `echo $PATH` to see what paths are available) to `bin\dktl.sh`, and name the link `dktl`. For instance, if you have a bin directory in your home directory that is in your PATH, try  
```bash
ln -s /my/dktl/location/bin/dktl.sh ~/bin/dktl
```
3. Go to the directory where DKAN-tools was downloaded to (it should contain a composer.json file) and run `composer install`. 

## Usage

The `dktl` script assumes that it is being run from inside a DKAN project. A DKAN project will ultimately have the following contents:

* `dkan/` dir: The DKAN code, cloned or downloaded from https://github.com/GetDKAN/dkan
* `docroot/`: The full Drupal codebase. The `dkan/` dir will be symlinked into `docroot/profiles/`.
* `config/`: All project-specific configuration and customizations.
* `dktl.yml`: Project configuration for DKAN Tools

### Starting docker

To run any other `dktl` commands, you must first bring up the project's Docker containers. From the root directory of the project, type:
```
dktl docker-compose up
```
You should see some standard docker output and a message that you containers are running. Type `dktl` (with no arguments) to see a list of the commands now available to you.

## DKAN Tools Custom Commands

DKAN Tools (DKTL) is build on top of the Robo framework: https://robo.li/

DKTL allows projects to define their own commands.

To create a custom command create a new class inside of this project with a similar structure to the this one:

```
<?php
namespace DkanTools\Custom;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class CustomCommands extends \Robo\Tasks
{
    /**
     * Sample.
     */
    public function customSample()
    {
        $this->io()->comment("Hello World!!!");
    }
}
```

The critical parts of the example are:
1) The namespace
1) The extension of \Robo\Tasks
1) The name of the file for the class should match the class name. In this case the file name should be CustomCommands.php

Everything else is flexible: 
1) The class name
1) The function names
1) etc

Each function inside of the class will show up as an available DKTL command.

_More documentation coming soon!_