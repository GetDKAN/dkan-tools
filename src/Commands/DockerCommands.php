<?php
namespace DkanTools\Commands;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class DockerCommands extends \Robo\Tasks
{
    /**
     * Bring up docker containers for project.
     */
    function dockerUp()
    {
        $dockerUpStack = $this->getDockerStack('up -d');
        $dockerUpStack->run();
    }

    /**
     * Bring down docker containers for project.
     */
    function dockerStop()
    {
        $dockerStopStack = $this->getDockerStack('stop');
        $dockerStopStack->run();
    }

    /**
     * Docker exec command --
     */
    function dockerExec(string $cmd, string $service = 'cli')
    {
        $dockerComposeStack = $this->getDockerStack("exec $service $cmd");
        $dockerComposeStack->run();
    }

    /**
     * Arbitrary docker compose command --
     */
    function dockerCompose(string $cmd)
    {
        $dockerComposeStack = $this->getDockerStack($cmd);
        $dockerComposeStack->run();
    }

    /**
     * Get a taskExecStack object that forms the base of all docker-compose
     * commands.
     *
     * @param string Docker compose action to append (e.g. "stop").
     * @return object A CollectionBuilder object built with taskExecStack(),
     *                ready to run().
     */
    function getDockerStack($cmd)
    {
        $confMain = dirname(__DIR__, 2) . '/assets/docker/docker-compose.common.yml';
        $confVolume = dirname(__DIR__, 2) . '/assets/docker/docker-compose.nosync.yml';
        $confProxy = dirname(__DIR__, 2) . '/assets/docker/docker-compose.noproxy.yml';

        $dockerCommand = "docker-compose -f $confMain -f $confVolume -f $confProxy $cmd";
        $slug = str_replace(['-', '_'], '', basename(getcwd()));

        return $this->taskExecStack()
            ->exec("export SLUG=$slug")
            ->exec('export DKTL_DIRECTORY=' . __DIR__)
            ->exec('export DKTL_CURRENT_DIRECTORY=' . getcwd())
            ->exec('export PROXY_DOMAIN='. $this->getDockerProxy())
            ->exec($dockerCommand);
    }

    /**
     * Helper function to get the proxy domain for docker compose.
     *
     * @return string Domain for proxy.
     */
    function getDockerProxy()
    {
        $inspect = 'docker inspect proxy 2> /dev/null | grep docker.domain | tr -d \' ",-\' | cut -d \= -f 2 | head -1';
        $res = $this->taskExec($inspect)
            ->printOutput(FALSE)
            ->run();
        // Check for proxy container, get domain from that.
        if ($proxy_domain = $res->getMessage()) {
            return $proxy_domain;
        }
        // If no proxy is running, use the overridden or default proxy domain
        elseif ($proxy_domain = getenv('AHOY_WEB_DOMAIN')) {
            return $proxy_domain;
        }
        else {
            return 'localtest.me';
        }

    }
}
