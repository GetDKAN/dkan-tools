<?php

namespace DkanTools\Commands;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class RoboFile extends \Robo\Tasks
{
    /**
     * Say hello.
     *
     * @param string $world What to say hello to.
     */
    function hello(array $world)
    {
        if (empty($world)) {
            $world = $this->ask("What is your name?");
        }
        $worlds = implode(' ', $world);
        $this->say("Hello, $worlds");
    }
}
