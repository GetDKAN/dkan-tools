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
     * Bring up docker containers.
     */
    function dockerUp()
    {
        $confMain = __DIR__ . '/../../assets/docker/docker-compose.common.yml';
        $confVolume = __DIR__ . '/../../assets/docker/docker-compose.nosync.yml';
        $confProxy = __DIR__ . '/../../assets/docker/docker-compose.noproxy.yml';

        $dockerCommandBase = "docker-compose -f $confMain -f $confVolume -f $confProxy up -d";

        $result = $this->taskExec($dockerCommandBase)
            ->run();
        $this->say($result->getMessage());
    }
}
