# DKAN Tools

This CLI application provides tools for implementing and developing [DKAN](https://getdkan.org/), the Drupal-based open data portal.

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

## Starting a project
To start a project with `dktl`, create a project directory.

```bash
mkdir my_project && cd my_project
```

Inside the project directory, initialize your project.

```bash
dktl init
```

This will automatically start up the Docker containers, which can also be started manually with `dktl docker:compose up -d`. Any other docker-compose commands can be run via `dktl docker:compose <args>` or simply `dktl dc <args>`.

After initialization, we want to get DKAN ready. We can use `git clone` (recommended if you are working directly on DKAN core and will want to commit and push changes to the DKAN project) or download a tarball of the DKAN source from [GitHub](https://github.com/GetDKAN/dkan), but the easiest method is using this command:

```bash
dktl dkan:get <version_number>
```

Versions of DKAN look like this: ``7.x-1.15.3``. We can see all of [DKAN's releases](https://github.com/getDkan/dkan/releases) in Github.

Now run the "make" command:

```bash
dktl make
```

The `make` command will get all of DKAN's dependencies _including_ Drupal core. It will also create all the symlinks necesarry to create a working Drupal site under _/docroot_.

Finally, let's install DKAN.

```bash
dktl install
```

You can find the local site URL by typing `dktl docker:surl`.

## Structure of a DKAN-Tools-based project

One of the many reasons for using DKTL is to create a clear separation between the code specific to a particular DKAN site (i.e. "custom code") and the dependencies we pull in from other sources (primarily, DKAN core and Drupal core).

To accomplish this, DKAN Tools projects will have the following basic directory structure, created when we run `dktl init`.

    ├── dkan              # The upstream DKAN core codebase
    ├── docroot           # Drupal core, and contrib modules not from DKAN
    ├── src               # Site-specific configuration, code and files
    │   ├── make          # Overrides for DKAN and Drupal makefiles
    │   ├── modules       # Symlinked to docroot/sites/all/modules/custom
    │   ├── script        # Deployment script and other misc utilities
    |   └── site          # Symlinked to docroot/sites/default
    │       └── files     # The main site files
    └── dktl.yml          # DKAN Tools configuration

We may wish to create two additional folders in the root of your project later on: _/src/patches_, where we can store local patches to be applied via the make files in _/src/make_; and _/backups_, where database dumps can be stored. The first time we run `dktl install` the _/backups_ folder will be created if it does not already exist.

### The /src/make folder

DKAN uses [Drush Make](https://docs.drush.org/en/8.x/make/) to define its dependencies. DKAN Tools also uses Drush Make to apply overrides patches to DKAN in a managed way, without having to hack either the Drupal or DKAN core.

DKAN defines its Drupal Core dependency in _/dkan/drupal-org-core.make_. Additional DKAN dependencies and patches are defined in _/dkan/drupal-org.make_. These two files should not be changed directly within the _dkan_ folder, but they can be _overridden_ via two files in your project: _/src/make/drupal.make_ and _/src/make/dkan.make_.

If we want to override the version of Drupal being used (for instance, if we need a security update just released in Drupal core but aren't ready to move to the newest DKAN version), we add the right version to _/src/make/drupal.make_:

```yaml
api: 2
core: 7.x
projects:
  drupal:
    type: core
    version: '7.50'
```

In _/src/make/drupal.make_ we can also define the contributed modules, themes, and libraries that our site uses. For example if our site uses the [Deploy](https://www.drupal.org/project/deploy) module we can add this to _/src/make/drupal.make_ under the `projects` section:

```yaml
projects:
  deploy:
    version: '3.1'
```


If our site requires a custom patch to the deploy module, we add it to _/src/patches_. For remote patches (usually from [Drupal.org](https://www.drupal.org)) we just need the url to the patch:

```yaml
projects:
  deploy:
    version: '3.1'
    patch:
      1: '../patches/custom_patch.patch'
      3005415: 'https://www.drupal.org/files/issues/2018-10-09/use_plain_text_format-3005415.patch'
```

### The src/site folder

Most configuration in Drupal sites is placed in the _/sites/default_ directory.

The _/src/site_ folder will replace _/docroot/sites/default_ once Drupal is installed. _/src/site_ should then contain all of the configuration that will be in _/docroot/sites/default_.

DKTL should have already provided some things in _/src/site_: _settings.php_ contains some generalized code that is meant to load any other setting files present, as long as they follow the _settings._\<something\>_.php_ pattern. All of the special settings that you previously had in _settings.php_ or other drupal configuration files should live in _settings.custom.php_ or a similarly-named file in _/src/site_.

## Restoring a database dump or site files

DKAN Tools' `restore` commands can restore from a local or remote dump of the database, as well as restore a files archive. This simplest way to do this is:

```bash
dktl dkan:restore --db_url=<path_to_db> --files_url=<path_to_files>
```

As described below, these options can be stored in a configuration file so that you can type simply `dktl restore`.

You may also restore from a local database backup, as long as it is placed in a folder under the project root called _/backups_. Type `dktl db:restore` with no argument, and the backup in _/backups_ will be restored if there is only one, or you will be allowed to select from a list if there are several.

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

Projects to can define their own commands. To create a custom command, create a new class inside of this project with a similar structure to the this one:

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

## A note to users of DKAN Starter

Users of [DKAN Starter](https://github.com/GetDKAN/dkan_starter) will recognize some concepts here. The release of DKAN Tools eliminates the need for a separate DKAN Starter project, as it provides a workflow to build sites directly from DKAN releases. Support for DKAN Starter and its accompanying [Ahoy](http://www.ahoycli.com/en/latest/) commands is ending, and detailed instructions for migrating DKAN Starter projects to the DKAN Tools workflow is coming soon.

## Troubleshooting

<dl>
  <dt>PHP Warning:  is_file(): Unable to find the wrapper "s3"</dt>
  <dd>Delete the vendor directory in your local dkan-tools and run <code>dktl</code> in your project directory</dd>
  <dt>Changing ownership of new files to host user ... chown: ...: illegal group name</dt>
  <dd>Disable the chown behavior <code>export DKTL_CHOWN="FALSE"</code></dd>
</dl>
