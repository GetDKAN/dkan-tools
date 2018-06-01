<?php

namespace DkanTools\Util;

class Docker
{
    public static function cmd($cmdStr) {
        $dockerCommand = self::getEnvCmd();
        $confMain = dirname(__DIR__, 2) . '/assets/docker/docker-compose.common.yml';
        $confVolume = dirname(__DIR__, 2) . '/assets/docker/docker-compose.nosync.yml';

        $dockerCommand[] = "docker-compose -f $confMain -f $confVolume $cmdStr";
        return implode(" && ", $dockerCommand);
    }

    public static function execBash($service, $cmd) {
        $cmdStr = self::cmd("exec $service bash -c '$cmd'");
        return `$cmdStr`;
    }

    public static function getSlug() {
        return str_replace(['-', '_'], '', basename(getcwd()));
    }

    private static function getEnvCmd()
    {
        $slug = self::getSlug();
        $env = [
            "export SLUG=$slug",
            "export COMPOSE_PROJECT_NAME=$slug",
            'export DKTL_DIRECTORY=' . __DIR__,
            'export DKTL_CURRENT_DIRECTORY=' . getcwd(),
            'export PROXY_DOMAIN='. self::getDockerProxy()
        ];
        return $env;
    }

    public static function getDockerProxy()
    {
        // Check for proxy container, get domain from that.
        if ($proxy_domain = `docker inspect proxy 2> /dev/null | grep docker.domain | tr -d ' ",-' | cut -d = -f 2 | head -1`) {
            return trim($proxy_domain);
        }
        // If no proxy is running, use the overridden or default proxy domain
        elseif ($proxy_domain = getenv('AHOY_WEB_DOMAIN')) {
            return $proxy_domain;
        }
        else {
            return 'localtest.me';
        }
    }

    public static function getDbUrl() {
        $dbUser = self::execBash('db', 'echo -n $MYSQL_USER');
        $dbPassword = self::execBash('db', 'echo -n $MYSQL_PASSWORD');
        $dbDb = self::execBash('db', 'echo -n $MYSQL_DATABASE');
        $host = self::execBash('db', 'echo -n $HOSTNAME');
        $db = "mysql://$dbUser:$dbPassword@$host/$dbDb";
        return $db;
    }

    public static function getContainer($service) {
        $idCommand = self::cmd("ps -q $service");
        $id = trim(`$idCommand`);
        $container = trim(`docker inspect $id -f '{{ .Name }}' | cut -c 2-`);
        return $container;
    }

    public static function getCliContainer() {
        static $container = '';
        if (!$container) {
            $container = self::getContainer('cli');
        }
        return $container;
    }

    public static function getDbContainer() {
        static $container = '';
        if (!$container) {
            $container = self::getContainer('db');
        }
        return $container;
    }

}
