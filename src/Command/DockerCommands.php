<?php

namespace DkanTools\Command;

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
     * Run a docker-compose command. E.g. "dktl docker:compose ps".
     *
     * Run a docker-compose command, with configuration options already added.
     *
     * Examples:
     *
     * * "dktl docker:compose ps" to list project's containers and their state
     * * "dktl docker:compose up -d" to start up docker containers
     * * "dktl docker:compose exec cli bash" for a command prompt in the cli
     * * "dktl docker:compose stop" to shut down a project's containers
     * * "dktl docker:compose rm" to erase a project's containers
     *
     * You may also run a shorter alias, "dc" (e.g. "dktl dc ps"). See
     * https://docs.docker.com/compose/reference/ for a full list of commands.
     */
    public function dockerCompose()
    {
        throw new \Exception(self::DKTL_DOCKER_PHP_ERROR);
    }

    /**
     * Display the insecure (http) web URL for the current project.
     */
    public function url()
    {
        throw new \Exception(self::DKTL_DOCKER_PHP_ERROR);
    }

    /**
     * Display the secure (https) web URL for the current project.
     */
    public function surl()
    {
        throw new \Exception(self::DKTL_DOCKER_PHP_ERROR);
    }

    /**
     * Show clickable admin login link for insecure web URL (http)
     */
    public function uli()
    {
        throw new \Exception(self::DKTL_DOCKER_PHP_ERROR);
    }

    /**
     * Show clickable admin login link for secure URL (https)
     */
    public function suli()
    {
        throw new \Exception(self::DKTL_DOCKER_PHP_ERROR);
    }
}
