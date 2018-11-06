# DKAN Tools (DKTL)

This CLI application provides tools for implementing and developing [DKAN](https://getdkan.org/), the Drupal-based open data portal.

## Requirements

This tool currently only supports [Docker](https://www.docker.com/)-based local development environments. In the future it will be expanded to support a local webserver and database setup. Current requirements are simply:

* Bash-like shell that can execute .sh files (Linux or OS X terminals should all work)
* [Docker](https://www.docker.com/get-docker)
* [Docker Compose](https://docs.docker.com/compose/)

That's it! All other dependencies are included in the Docker containers that dkan-tools will create.

## Installation

1. Download or clone this repository into any location on your development machine.
2. Create a symbolic link anywhere in your [PATH](http://www.linfo.org/path_env_var.html) (type `echo $PATH` to see what paths are available) `bin\dktl.sh`, and name the link `dktl`. For instance, if you have a bin directory in your home directory that is in your PATH, try  
```bash
ln -s /my/dktl/location/bin/dktl.sh ~/bin/dktl
```

## Starting a project
To start a project with `dktl` simply create a directory.

```
mkdir my_project && cd my_project
```

Inside the project directory, initialize your project.

```
dktl init
```

After initialization, we want to get DKAN ready.

```
dktl dkan:get <version_number> && dktl dkan:make
```

Versions of DKAN look like this: ``7.x-1.15.3``. You can see all of [DKAN's releases](https://github.com/getDkan/dkan/releases) in Github.

The last prepartion step is to get Drupal.

```
dktl drupal:make
```

Finally, let's install DKAN.

```
dktl dkan:install
```

To access your site use `dktl drush:uli`

## Existing DKAN Site to DKTL

One of the many reasons for using DKTL is to create a clear separation between **our** application, and other software that we are simply using. To accomplish this, we want as much of what makes our application unique to live in the ``src`` directory.

To get started we find what version of DKAN our current application is using, and run the following commands:

```
mkdir my_project && cd my_project
dktl dc up -d && dktl init
dktl dkan:get <version_number>
```

Before we finish getting DKAN ready, we want to figure out if our current site has any patched versions of DKAN dependencies. DKAN uses **Drush Make** to define its dependency. DKTL takes advantage of Drush Make to be able to apply patches to DKAN without having to modify anything owned by DKAN itself.

Any patches that we want to apply to dkan can be placed in the ``src/make/dkan.make`` file.

For reference on how to use make files with Drush Make look at the [Drush Make documentation](http://docs.drush.org/en/7.x/make/).

After setting up our ``dkan.make`` file, we can finish getting DKAN ready.

``dktl dkan:make``

Now we can get Drupal ready. DKAN comes with a suggested version of Drupal. This version can be found in ``dkan/drupal-org-core.make``. If our site is using a different version, we can modify this by adding the right version to ``src/make/drupal.make``.

Part of you file should look like this:

```
projects:
  drupal:
    type: core
    version: '7.50'

```

In ``src/make/drupal.make`` we can also define the contributed modules, themes, and libraries that our site uses. For example if our site uses the deploy module we can add this to ``drupal.make`` under the ``projects`` section:

```
deploy:
  version: '3.1'
```

Most of all other configuration in Drupal/DKAN sites is placed in the ``sites/default`` directory inside of Drupal.

To keep the separation between our code/configuration and what is Drupal's, DKTL provides the ``src/site``

``src/site`` will replace ``sites/default`` once Drupal is installed. ``src/site`` should then contain all of the configuration that will be in ``sites/default``. DKTL already provided some things in ``src/site``. ``settings.php`` contains some generalized code that is meant to load any other setting files present there as long as they follow the ``settings.<something>.php`` pattern. All of the special settings that you previously had in ``settings.php`` or other drupal configuration files should live in your custom ``settings.<something>.php`` file in ``src/site``.

After all of our contributed dependencies and other custom configuration have been properly captured in ``drupal.make``, we can get and setup the code:

```
dktl drupal:make
```

Finally, if all our configuration is in file we can now install dkan and enble any other modules that control the configuration of our site.

If not all our configuration is in code, then we usually need to get a version of an existing database and a set of files. DKTL provides the ``dktl dkan:restore`` command to accomplish that.

```
dktl dkan:restore --db_url=<path_to_db> --files_url=<path_to_files>
```

After our database and files have been installed. We can access our site with `dktl drush:uli.``

## Usage

The `dktl` script assumes that it is being run from inside a DKAN project. A DKAN project will ultimately have the following contents:

* `dkan/` directory: The DKAN code, cloned or downloaded from https://github.com/GetDKAN/dkan
* `docroot/`: The full Drupal codebase. The `dkan/` directory will be symlinked into `docroot/profiles/`.
* `src/`: All project-specific configuration and customizations.
* `dktl.yml`: Project configuration for DKAN Tools

Typing `dktl` alone will show you all the commands available to you from anywhere within your project. Typing `dktl help <command>` will provide extended information on specific commands.

### Starting docker

To run any other `dktl` commands, you must first bring up the project's Docker containers. From the root directory of the project, type:
```
dktl docker-compose up
```
You should see some standard docker output and a message that you containers are running. Type `dktl` (with no arguments) to see a list of the commands now available to you.

### Configuring DKTL commands

You will probably want to set up some default arguments for certain commands, especially the urls for your `dkan:deploy` command. This is what the dkan.yml file is for. You can provide options and arguments for any DKTL command in dkan.yml. For instance:

```yaml
"dkan:restore":
    db_url: "s3://my-backups-bucket/my-db.sql.gz"
    files_url: "s3://my-backups-bucket/my-files.tar.gz"
```

If you include this in your dktl.yml file, typing `dktl dkan:restore` without any arguments will load these two options.

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

## Disabling `chown`

DKTL, by default, performs most of its tasks inside of a docker container. The result is that any files created by scripts running inside the container will appear to be owned by "root" on the host machine, which often leads to permission issues when trying to use these files. To avoid this DKTL attempts to give ownership of all project files to the user running DKTL when it detects that files have changed, using the `chown` command via `sudo`. In some circumstances, such as environments where `sudo` is not available, you may not want this behavior. This can be controlled by setting a true/false environment variable, `DKTL_CHOWN`.

To disable the `chown` behavior, create the environment variable with this command:

```bash
export DKTL_CHOWN="FALSE"
```

## Running without Docker

One of the greatest strenghts of DKTL is the ease in which a proper environment can be ready to run a DKAN project and the tooling, with the only dependency being docker and docker-compose.

If for some reason you would like to use some of DKTL without docker, there is a mechanism to accomplish this.

First of all, make sure that you have all of the software DKTL needs:
1) PHP
2) Composer
3) Drush

The mode in which DKTL runs is controlled by an environment variable: ``DKTL_MODE``. To run DKLT without docker set the environment variable to ``HOST``:

```export DKTL_MODE="HOST"```

To go back to running in docker mode, set the variable to ``DOCKER``.
