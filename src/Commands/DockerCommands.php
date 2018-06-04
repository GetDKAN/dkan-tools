<?php

namespace DkanTools\Commands;

/**
 * These functions are not meant to be run, simply to provide documentation for
 * the docker comands supplied by dktl.sh.
 *
 * @see http://robo.li/
 */
class DockerCommands extends \Robo\Tasks
{
    const DKTL_DOCKER_PHP_ERROR = "This command was run in error; docker commands should run through dktl.sh";

    /**
     * Run a docker-compose command. E.g. dktl docker:compose ps. Alias  "dc".
     */
     function dockerCompose()
     {
         throw new \Exception(DKTL_DOCKER_PHP_ERROR);
     }

     /**
      * Display the web URL for the current project.
      */
      function dockerUrl()
      {
          throw new \Exception(DKTL_DOCKER_PHP_ERROR);
      }

      /**
       * Display the secure (https) web URL for the current project.
       */
      function dockerSurl()
      {
          throw new \Exception(DKTL_DOCKER_PHP_ERROR);
      }
}
