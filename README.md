# DKAN Tools

This CLI application provides tools for implementing and developing [DKAN](https://github.com/GetDKAN/dkan), the Drupal-based open data portal. For Drupal 7.x projects use the 1.x branch.

## Requirements

DKAN Tools was designed with a [Docker](https://www.docker.com/)-based local development environment in mind. Current requirements are simply:

* Bash-like shell that can execute .sh files (Linux or OS X terminals should all work)
* [Docker](https://www.docker.com/get-docker)
* [Docker Compose](https://docs.docker.com/compose/)

That's it! All other dependencies are included in the Docker containers that dkan-tools will create.

It is also possible to run most DKAN Tools commands using a local webserver, database and PHP setup, but this practice is less supported.

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

Once you are working in a valid project folder (see next section) you can type `dktl` at any time to see a list of available commands.

## Local development
The React front end uses the DKAN API to build pages, so we will want to mimic a production environment. The environment variables are [defined in `.env` files](https://github.com/GetDKAN/data-catalog-frontend/blob/master/.env.production#L1), the default value is _"dkan"_, you can adjust these files as necessary.

Setup and start the proxy:

- Add `dkan` to `/etc/hosts`
- Start the proxy: `docker run -d -p 80:80 -v /var/run/docker.sock:/tmp/docker.sock:ro jwilder/nginx-proxy`

## Starting a new project
1. To start a project with `dktl`, create a project directory.

```bash
mkdir my_project && cd my_project
```

2. Inside the project directory, initialize your project.

```bash
dktl init
```

This will automatically start up the Docker containers, which can also be started manually with `dktl docker:compose up -d`. Any other docker-compose commands can be run via `dktl docker:compose <args>` or simply `dktl dc <args>`.

3. Get Drupal, only versions 8.8 or above are supported:

```bash
dktl get <drupal-version>
```

4. Get Drupal dependencies and DKAN modules. This will create the symlinks necessary to create a working Drupal site under _/docroot_.

```bash
dktl make
```

  - Make options:
      * `--prefer-source` If you are working directly on the DKAN project or one of its libraries and want to be able to commit changes and submit pull requests. This option will be passed directly to Composer; see the [Composer CLI documentation](https://getcomposer.org/doc/03-cli.md#command-line-interface-commands) for more details.
      * `--frontend` To **download** the React frontend application to _src/frontend_ and symlink the files to _docroot/data-catalog-frontend_.
      * `--tag=<tag>` To build a site using a specific DKAN tag rather than from master.
      * `--branch=<branch-name>` Similarly, you can build a specific branch of DKAN by using this option.

5. Install DKAN. Creates a database, installs Drupal, enables DKAN.

```bash
dktl install
```
  - Install options:
      * `--frontend` Add this option again to **enable** the dkan_frontend module. This module provides the routes that connect Drupal to the decoupled front end. Be sure to follow the [frontend](https://github.com/GetDKAN/data-catalog-frontend#using-the-app) instructions for building the React application and updating pages after adding your own content.
      * `--demo` Use this option to have the frontend enabled, example content created, and the React pages built.


6. Access the site: `dktl drush uli --uri=dkan`, or you can find the local site URL by typing `dktl url`.


## Structure of a DKAN-Tools-based project

One of the many reasons for using DKTL is to create a clear separation between the code specific to a particular DKAN site (i.e. "custom code") and the dependencies we pull in from other sources (primarily, DKAN core and Drupal core). Keep all of your custom code in the _src_ directory.

To accomplish this, DKAN Tools projects will have the following basic directory structure, created when we run `dktl init`.

    ├── backups           # Optional for local development, see the backups section below
    ├── docroot           # Drupal core
    |   └── modules
    |       └── contrib
    |           └── dkan2 # The upstream DKAN core codebase
    |
    ├── src               # Site-specific configuration, code and files.
    │   ├── make          # Overrides for DKAN and Drupal makefiles
    │   ├── modules       # Symlinked to docroot/modules/custom
    │   ├── script        # Deployment script and other misc utilities
    |   └── site          # Symlinked to docroot/sites/default
    │   │   └── files     # The main site files
    │   └── test          # Custom tests
    └── dktl.yml          # DKAN Tools configuration

We may wish to create two additional folders in the root of your project later on: _/src/patches_, where we can store local patches to be applied via the make files in _/src/make_; and _/backups_, where database dumps can be stored.

### The /src/make folder

DKAN uses [Drush Make](https://docs.drush.org/en/8.x/make/) to define its dependencies. DKAN Tools also uses Drush Make to apply overrides patches to DKAN in a managed way, without having to hack either the Drupal or DKAN core.

In _/src/make/composer.json_ we can define the contributed modules, themes, and libraries that our site uses. For example if our site uses the [Deploy](https://www.drupal.org/project/deploy) module we can add this to _/src/make/drupal.make_ under the `require` section:

```json
  "require": {
    "getdkan/dkan2": "dev-master",
    "drupal/deploy": "3.1"
  }
```

If our site requires a custom patch to the deploy module, we add it to _/src/patches_. For remote patches (usually from [Drupal.org](https://www.drupal.org)) we just need the url to the patch:

```json
  "extra": {
    "patches": {
      "drupal/deploy": {
        "3005415": "https://www.drupal.org/files/issues/2018-10-09/use_plain_text_format-3005415.patch"
      }
    }
  }
```

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
