# DKAN Tools

This CLI application provides tools for implementing, developing, and maintaining [DKAN](https://github.com/GetDKAN/dkan), the Drupal-based open data catalog. For Drupal 7.x projects use the 1.x branch.

## Requirements

DKAN Tools was designed with a [Docker](https://www.docker.com/)-based local development environment in mind. Current requirements are simply:

* Bash-like shell that can execute .sh files (Linux or OS X terminals should all work)
* [Docker](https://www.docker.com/get-docker)
* [Docker Compose](https://docs.docker.com/compose/)

That's it! All other dependencies are included in the Docker containers that dkan-tools will create.

---

:warning: **IMPORTANT**

It is also possible to run most DKAN Tools commands using a local webserver, database and PHP setup, but this practice is **less supported**.

To use the `dktl` commands outside of the docker evironments you will need to run `export DKTL_MODE="HOST"`

---

## Installation

1. Download or clone this repository into any location on your development machine.
2. Add _bin/dktl_ to your `$PATH` somehow. This is often accomplished by adding a symbolic link to a folder already in your path, like _~/bin_. For instance, if DKAN Tools is located in _/myworkspace_:

```bash
ln -s  /myworkspace/dkan-tools/bin/dktl ~/bin/dktl
```

Alternatively, you could add _/myworkspace/dkan-tools/bin_ directly to your `$PATH`. Enter this in your terminal or add it to your session permanently by adding a line in _.bashrc_ or _.bash_profile_:

```bash
export PATH=$PATH:/myworkspace/dkan-tools/bin
```

#### DKTL proxy:
dkan-tools leverages traefik to route traffic based on a per-environment domain
name. traefik is ran as singleton service named `dktl-proxy`.

dktl-proxy will server your website from a constructed domain in the form of
"{{dktl-slug}}.localtest.me", where dktl-slug is the per project string
identifing the current enviroment. If your project directory is dkan, the
project will be served at `//dkan.localtest.me`

## DKAN Quick-Start Demo

1. Create a project directory, initialize the project and run the demo script.

```bash
mkdir my_project && cd my_project
dktl init
dktl demo
```

## Starting a new project, step-by-step

The `demo` command above is a wrapper for the following commands. To get a better
idea of how DKAN and DKAN-tools work, you may want to follow these more detailed
steps:

1. To start a project with `dktl`, create a project directory.

```bash
mkdir my_project && cd my_project
```

2. Inside the project directory, initialize your project.

```bash
dktl init
```

3. Make a full Drupal/DKAN codebase, primarily using composer.

```bash
dktl make
```
`make` options (passed directly to `composer install`, see [documentation](https://getcomposer.org/doc/03-cli.md#install-i)):

  * `--prefer-source`
  * `--prefer-dist`
  * `--no-dev`
  * `--optimize-autoloader`


5. Install DKAN. Creates a database, installs Drupal, enables DKAN.

```bash
dktl install
```
`install` options:
      
  * `--existing-config` Add this option to preserve existing configuration.

6. Access the site: If the proxy was set up and configured, and you installed the frontend, your site should be accessible at http://dkan.

```bash
dktl drush uli 
```

7. Stop the docker-compose project, removing all containers and networks.

```bash
dktl down
```

This will keep files downloaded during the make phase, as well as any changes
made to them. But any databose will be removed and all content lost.

## Adding DKAN to an existing Drupal Site

```bash
composer require 'getdkan/dkan'
dktl drush en dkan
dktl install:sample
dktl frontend:install
dktl frontend:build
```

## Basic usage

Once you are working in an initialized project folder, you can type `dktl` at any time to see a list of all available commands.

## File structure of a DKAN-Tools-based project

One of the many reasons for using DKTL is to create a clear separation between the
code specific to a particular DKAN site (i.e. "custom code") and the dependencies
we pull in from other sources (primarily, DKAN core and Drupal core). Keep all of
your custom code in the _src_ directory and symlink the overrides to the appropriate
directory inside docroot. This will make maintaining your DKAN site much easier.
DKAN Tools will set up the symlinks for you.

To accomplish this separation, DKAN Tools projects will have the following basic
directory structure, created when we run `dktl init`.

    ├── backups           # Optional for local development, see the backups section below
    ├── docroot           # Drupal core
    |   └── modules
    |       └── contrib
    |           └── dkan # The upstream DKAN core codebase
    |
    ├── src               # Site-specific configuration, code and files.
    │   ├── modules       # Symlinked to docroot/modules/custom
    │   ├── script        # Deployment script and other misc utilities
    |   └── site          # Symlinked to docroot/sites/default
    │   │   └── files     # The main site files
    │   ├── test          # Custom tests
    |   └── themes        # Symlinked to docroot/themes/custom
    └── dktl.yml          # DKAN Tools configuration


If it is necessary or expedient to overwrite files in DKAN or Drupal core, it is recommended that you create a _/src/patches_ directory where you can store local [patch](https://ariejan.net/2009/10/26/how-to-create-and-apply-a-patch-with-git/)
files with the changes. A patch will make it possible to re-apply these changes once a
newer version of DKAN or Drupal is applied to your project.

### The src/site folder

Most configuration in Drupal sites is placed in the _/sites/default_ directory.

The _/src/site_ folder will replace _/docroot/sites/default_ once Drupal is installed. _/src/site_ should then contain all of the configuration that will be in _/docroot/sites/default_.

DKTL should have already provided some things in _/src/site_: _settings.php_ contains some generalized code that is meant to load any other setting files present, as long as they follow the _settings._\<something\>_.php_ pattern. All of the special settings that you previously had in _settings.php_ or other drupal configuration files should live in _settings.custom.php_ or a similarly-named file in _/src/site_.

### The src/test folder (custom tests)

DKAN Tools supports custom [Cypress](https://www.cypress.io/) tests found in the _src/test/cypress_ directory.

To run custom tests:

```bash
dktl test:cypress
```

## Restoring a database dump or site files

DKAN Tools' `restore` commands can restore from a local or remote dump of the database, as well as restore a files archive. This simplest way to do this is:

```bash
dktl dkan:restore --db_url=<path_to_db> --files_url=<path_to_files>
```

As described below, these options can be stored in a configuration file so that you can type simply `dktl restore`.

You may also restore from a local database backup, as long as it is placed in a folder under the project root called _/backups_. Type `dktl db:restore` with no argument, and the backup in _/backups_ will be restored if there is only one, or you will be allowed to select from a list if there are several.

## Create and grab a database dump excluding tables

You can create a database dump excluding tables related to cache, devel, webform submissions and DKAN datastore. Running the command `dktl site:grab-database @alias` will create the database backup for the drush alias passed as argument.

This command needs to be run with DKTL_MODE set to "HOST". So you'll need to run `export DKTL_MODE="HOST"` and after the command finishes, you should set it back to its old value or just unset the variable by running `unset DKTL_MODE`.

If you want to import this dump into your local development site, then you can move the file _excluded\_tables.sql_ into the directory _backups_ in the root of your project, then you'll be able to import it by running `dktl restore:db excluded_tables.sql`.

## Primary Maintenance Tasks

A DKAN site does not differ substantially from [maintaining other Drupal
sites](https://www.drupal.org/docs/8/configuration-management).

By "maintenance" we mean three specific tasks

-  **Upgrading** DKAN to receive new features and bug-fixes
-  **Adding** additional modules or features
-  **Overriding** current modules or functionally

## Getting DKAN Updates

DKAN uses a slightly modified [semantic](https://www.drupal.org/docs/8/understanding-drupal-version-numbers) versioning system.

**Major.Minor.Patch**

- *Major* indicates compatibility
- *Minor* indicates backwards compatilble new features or upgrades
- *Patch* indicates a release for security updates and bug fixes

Please note *you can not use* ``drush up`` *with DKAN*. This is because
DKAN is not packaged on Drupal.org.

### Basic Upgrades

If you are maintaining your site with [DKAN Tools](https://github.com/getdkan/dkan-tools), upgrading is as simple as running

`dktl make --tag=<tag version>`

Or update the version number in your compser.json file and run `composer update`:

```
"require": {
    "getdkan/dkan": "2.0.0"
}
```

### Upgrading DKAN from 7.x to 8.x

The easiest method will be to stand up a fresh Drupal 8 DKAN site and harvest the datasets from your Drupal 7 DKAN site.

## Configuring DKTL commands

You will probably want to set up some default arguments for certain commands, especially the urls for the `restore` command. This is what the dkan.yml file is for. You can provide options for any DKTL command in dkan.yml. For instance:

```yaml
command:
  restore:
    options:
      db_url: "s3://my-backups-bucket/my-db.sql.gz"
      files_url: "s3://my-backups-bucket/my-files.tar.gz"
```

If you include this in your dktl.yml file, typing `dktl restore` without any arguments will load these two options.

## Custom Commands

Projects to can define their own commands. To create a custom command, add a file named `CustomCommands.php` and add it to `src/command/`, create a new class in the file with a similar structure to this one:

```php
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

Everything else (class names, function names) is flexible, and each public function inside of the class will show up as an available `dktl` command.


## Advanced configuration

### Disabling `chown`

DKTL, by default, performs most of its tasks inside of a docker container. The result is that any files created by scripts running inside the container will appear to be owned by "root" on the host machine, which often leads to permission issues when trying to use these files. To avoid this DKTL attempts to give ownership of all project files to the user running DKTL when it detects that files have changed, using the `chown` command via `sudo`. In some circumstances, such as environments where `sudo` is not available, you may not want this behavior. This can be controlled by setting a true/false environment variable, `DKTL_CHOWN`.

To disable the `chown` behavior, create the environment variable with this command:

```bash
export DKTL_CHOWN="FALSE"
```

### Running without Docker

If for some reason you would like to use some of DKTL without docker, there is a mechanism to accomplish this.

First of all, make sure that you have all of the software DKTL needs:

1) PHP
2) Composer
3) Drush

The mode in which DKTL runs is controlled by an environment variable: `DKTL_MODE`. To run DKLT without docker set the environment variable to `HOST`:

```bash
export DKTL_MODE="HOST"
```

To go back to running in docker mode, set the variable to `DOCKER` (or just delete it).

### Using Xdebug

When using the standard docker-compose environment, [Xdebug](https://xdebug.org/) can be enabled on both the web and CLI containers as needed. Running it creates a significant performance hit, so it is disabled by default. To enable, simply run `dktl xdebug:start`. A new file will be added to _/src/docker/etc/php_, and the corresponding containers will restart. In most situations, this file should be excluded from version control with .gitignore.

### Configuration Brought into the Containers from the Host Machine.

Unless you are running in "HOST" mode, DKTL runs inside of docker containers. Some configuration from your host machine can be useful inside of the containers: ssh configuration, external services authentication tokens, etc.

DKTL recognized this and by default makes some configurations available to the containers by default. If any of these directories exist in your current user's profile, they will be available in the container:
* .ssh
* .aws
* .composer

## Troubleshooting

<dl>
  <dt>PHP Warning:  is_file(): Unable to find the wrapper "s3"</dt>
  <dd>Delete the vendor directory in your local dkan-tools and run <code>dktl</code> in your project directory</dd>
  <dt>Changing ownership of new files to host user ... chown: ...: illegal group name</dt>
  <dd>Disable the chown behavior <code>export DKTL_CHOWN="FALSE"</code></dd>
</dl>
