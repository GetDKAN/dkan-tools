# DKAN Tools

This CLI application provides tools for implementing and developping [DKAN](https://getdkan.org/), the Drupal-based open data portal.

## Requirements

This tool currently only supports [Docker](https://www.docker.com/)-based local development environments. In the future it will be expanded to support a local webserver and database setup. Current requirements are simply:

* Bash-like shell that can execute .sh files (Linux or OS X terminals should all work)
* [Docker](https://www.docker.com/get-docker)
* [Docker Compose](https://docs.docker.com/compose/)

That's it! All other dependencies are included in the Docker containers that dkan-tools will create.

## Installation

1. Download or clone this repository into any location on your development machine.
2. Create a symbolic link anywhere in your [PATH](http://www.linfo.org/path_env_var.html) (type `echo $PATH` to see what paths are available) to `bin\dktl.sh`, and name the link `dktl`. For instance, if you have a bin directory in your home directory that is in your PATH, try  
```bash
ln -s /my/dktl/location/bin/dktl.sh ~/bin/dktl
```

_Composer-based installation coming soon!_

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

_More documentation coming soon!_
