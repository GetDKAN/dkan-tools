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
2. Add _/bin/dktl_ to your path somehow. This is often accomplished by adding a symbolic link to a folder already in your path, like _~/bin_. For instance: 

```bash
ln -s /my/dktl/location/bin/dktl ~/bin/dktl
```

## Starting a project
To start a project with `dktl` simply create a directory.

```bash
mkdir my_project && cd my_project
```

Inside the project directory, initialize your project.

```bash
dktl init
```

After initialization, we want to get DKAN ready.

```bash
dktl dkan:get <version_number>
```

Versions of DKAN look like this: ``7.x-1.15.3``. You can see all of [DKAN's releases](https://github.com/getDkan/dkan/releases) in Github.

Now run the "make" command:

```bash
dktl make
```

The `make` command will get all of DKAN's dependencies _including_ Drupal core. It will also create all the symlinks necesarry to create a working Drupal site under _/docroot_.

Finally, let's install DKAN.

```bash
dktl dkan:install
```

You can find your local site URL by typing `dktl docker:surl`.

## Existing DKAN Site to DKTL

One of the many reasons for using DKTL is to create a clear separation between **our** application, and other software that we are simply using. To accomplish this, we want as much of what makes our application unique to live in the ``src`` directory.

To get started we find what version of DKAN our current application is using, and run the following commands:

```bash
mkdir my_project && cd my_project
dktl dc up -d && dktl init
dktl dkan:get <version_number>
```

Before we finish getting DKAN ready, we want to figure out if our current site has any patched versions of DKAN dependencies. DKAN uses **Drush Make** to define its dependency. DKTL takes advantage of Drush Make to be able to apply patches to DKAN without having to modify anything owned by DKAN itself.

Any patches that we want to apply to dkan can be placed in the _/src/make/dkan.make_ file.

For reference on how to use make files with Drush Make look at the [Drush Make documentation](http://docs.drush.org/en/7.x/make/).

DKAN comes with a suggested version of Drupal core. This version can be found in _/dkan/drupal-org-core.make_. If we want to build the site using a different version (for instance, if you need a security update but aren't ready to move to the newest DKAN version), we add the right version to _/src/make/drupal.make_:

```yaml
api: 2
core: 7.x
projects:
  drupal:
    type: core
    version: '7.50'
```

In _/src/make/drupal.make_ we can also define the contributed modules, themes, and libraries that our site uses. For example if our site uses the deploy module we can add this to _/src/make/drupal.make_ under the `projects` section:

```yaml
projects:
  deploy:
    version: '3.1'
```

Most of all other configuration in Drupal/DKAN sites is placed in the _/sites/default_ directory inside of Drupal.

To keep the separation between our code/configuration and what is Drupal's, DKTL provides _/src/site_

The _/src/site_ folder will replace _docroot/sites/default_ once Drupal is installed. _/src/site_ should then contain all of the configuration that will be in _/docroot/sites/default_. DKTL should have already provided some things in _/src/site_: _settings.php_ contains some generalized code that is meant to load any other setting files present, as long as they follow the _settings._\<something\>_.php_ pattern. All of the special settings that you previously had in _settings.php_ or other drupal configuration files should live in _settings.custom.php_ or a similarly-named file in _/src/site_.

After all of our contributed dependencies and other custom configuration have been properly captured in _drupal.make_, we can get and set up the dependencies:

```
dktl make
```

Finally, if all our configuration is in files, we can now install dkan and enble any other modules that control the configuration of our site.

## Restoring a database dump or site files

DKAN Tools' `restore` commands can restore from a local or remote dump of the database, as well as restore a files archive. This simplest way to do this is:

```bash
dktl dkan:restore --db_url=<path_to_db> --files_url=<path_to_files>
```

Additional documentation coming soon...

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
dktl docker:compose up -d
```

(There is also an alias, `dc`, to make docker-compose commands easier to type. So try `dktl dc up -d`.)

You should see some standard docker output and a message that your containers are running. Type `dktl` (with no arguments) to see a list of the commands now available to you.

### Configuring DKTL commands

You will probably want to set up some default arguments for certain commands, especially the urls for your `restore` command. This is what the dkan.yml file is for. You can provide options for any DKTL command in dkan.yml. For instance:

```yaml
command:
  restore:
    options:
      db_url: "s3://my-backups-bucket/my-db.sql.gz"
      files_url: "s3://my-backups-bucket/my-files.tar.gz"
```

If you include this in your dktl.yml file, typing `dktl restore` without any arguments will load these two options.

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

The mode in which DKTL runs is controlled by an environment variable: `DKTL_MODE`. To run DKLT without docker set the environment variable to `HOST`:

```bash
export DKTL_MODE="HOST"
```

To go back to running in docker mode, set the variable to `DOCKER`.

## Troubleshooting

<dl>
  <dt>PHP Warning:  is_file(): Unable to find the wrapper "s3"</dt>
  <dd>Delete the vendor directory in your local dkan-tools and run <code>dktl</code> in your project directory</dd>
  <dt>Changing ownership of new files to host user ... chown: ...: illegal group name</dt>
  <dd>Disable the chown behavior <code>export DKTL_CHOWN="FALSE"</code></dd>
</dl>
