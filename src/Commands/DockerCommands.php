<?php
namespace DkanTools\Commands;

use DkanTools\Util\Docker;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class DockerCommands extends \Robo\Tasks
{
    /**
     * Arbitrary docker compose command.
     *
     * @param string $cmd The command string to execute after "docker-compose"
     * @param bool $printOutput Whether to set printOutput to true or false.
     */
    function dockerCompose(string $cmd, $printOutput = TRUE)
    {
        return $this->taskExec(Docker::cmd($cmd))
            ->printOutput($printOutput)
            ->run();
    }

    /**
     * Bring up docker containers for project.
     */
    function dockerUp()
    {
        $this->dockerCompose('up -d');
    }

    /**
     * Bring down docker containers for project.
     */
    function dockerStop()
    {
        $this->dockerCompose('stop');
    }

    /**
     * Docker exec command.
     */
    function dockerExec(string $service, string $cmd)
    {
        $cmdStr = implode(' ', $cmd);
        return $this->dockerCompose("exec $service bash -c '$cmdStr'");
    }

    /**
     * Runs docker-compose ps to list project containers.
     */
    function dockerPs()
    {
        return $this->dockerCompose('ps');
    }

    /**
     * Stop and remove all docker containers
     */
    function dockerDestroy()
    {
        $this->dockerStop();
        return $this->dockerCompose('rm');
    }

    /**
     * Stop, destroy then rebuild project containers.
     */
    function dockerReset()
    {
        $this->dockerDestroy();
        return $this->dockerUp();
    }

    /**
     * Open current project site in the browser.
     *
     * @param string $protocol Must be "http" or "https"
     */
    function dockerUrl($protocol = 'http')
    {
        if (!in_array($protocol, ['http', 'https'])) {
            return new Robo\ResultData(0, 'Invalid protocol.');
        }
        $host = $this->getDockerProxy();
        if (!isset($port)) {
            $intPort = ($protocol == 'https') ? '80' : '443';
        }
        $port = $this->dockerCompose("port web $intPort|cut -d ':' -f2", FALSE)
            ->getMessage();
        return $this->taskOpenBrowser("{$protocol}://{$host}:{$port}")->run();
    }

    function getDockerContainer($service)
    {
        return $this->dockerCompose("ps -q $service", FALSE)->getMessage();
    }

    function dockerContainer($service) {
        $this->say($this->getDockerContainer($service));
    }

    /**
     * Opens secure URL for project site in the browser.
     */
    function dockerSurl()
    {
        return $this->dockerUrl('https');
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
