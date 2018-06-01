#!/bin/bash

function myHelp () {
cat <<-END
DKAN Tools
----------

More info to come!
END
}

if [ -z "$1" ]; then
    myHelp; exit 0
fi
if [ -z `which docker` ]; then
    echo "You don't seem to have docker installed. Exiting."; exit 1
fi
if [ -z `which docker-compose` ]; then
    echo "You don't seem to have docker-compose installed. Exiting."; exit 1
fi

dktl_symlink_location=$(which dktl)
echo $dktl_symlink_location

dktl_executable_location=$(readlink -f $dktl_symlink_location)
echo $dktl_executable_location

DKTL_DIRECTORY=$(dirname $(dirname $dktl_executable_location))
export DKTL_DIRECTORY

DKTL_CURRENT_DIRECTORY=$(pwd)
# echo $DKTL_CURRENT_DIRECTORY
export DKTL_CURRENT_DIRECTORY

SLUG=${PWD##*/}
SLUG=${SLUG//-/}
SLUG=${SLUG//_/}
SLUG=$(echo ${SLUG} | tr -d '[:space:]' | tr "[A-Z]" "[a-z]") # Mixed case dirs cause issue with docker image names
#echo $SLUG
export SLUG

DOCKER_COMPOSE_COMMON_CONF="$DKTL_DIRECTORY/assets/docker/docker-compose.common.yml"
PROXY_CONF="$DKTL_DIRECTORY/assets/docker/docker-compose.noproxy.yml"
VOLUME_CONF="$DKTL_DIRECTORY/assets/docker/docker-compose.nosync.yml"
CUSTOM_CONF="$DKTL_CURRENT_DIRECTORY/custom/docker-compose.custom.yml"


if [ -f $CUSTOM_CONF ]; then
    BASE_DOCKER_COMPOSE_COMMAND="docker-compose -f $CUSTOM_CONF -f $VOLUME_CONF -f $PROXY_CONF -p "${SLUG}" --project-directory $DKTL_CURRENT_DIRECTORY"
else
    BASE_DOCKER_COMPOSE_COMMAND="docker-compose -f $DOCKER_COMPOSE_COMMON_CONF -f $VOLUME_CONF -f $PROXY_CONF -p "${SLUG}" --project-directory $DKTL_CURRENT_DIRECTORY"
fi


echo "DOCKER COMPOSE COMMAND: ${BASE_DOCKER_COMPOSE_COMMAND}"

#Check for proxy container, get domain from that.
PROXY_DOMAIN=`docker inspect proxy 2> /dev/null | grep docker.domain | tr -d ' ",-' | cut -d \= -f 2 | head -1`

#If no proxy is running, use the overridden or default proxy domain
if [ -z "$PROXY_DOMAIN" ]; then
  [ "$AHOY_WEB_DOMAIN" ] && WEB_DOMAIN=$AHOY_WEB_DOMAIN || WEB_DOMAIN="localtest.me"
  PROXY_DOMAIN=${WEB_DOMAIN}
fi

export PROXY_DOMAIN=$PROXY_DOMAIN

if [ "$1" = "docker:compose" ] || [ "$1" = "dc" ]; then
    $BASE_DOCKER_COMPOSE_COMMAND ${@:2}
else
    script_file=$1.php
    script_file_exists=$(docker-compose -f $DOCKER_COMPOSE_COMMON_CONF -f $VOLUME_CONF -f $PROXY_CONF -f $CUSTOM_CONF -p "${SLUG}" --project-directory $DKTL_CURRENT_DIRECTORY exec cli ls /dktl | grep $script_file)
    echo $script_file_exists

    $BASE_DOCKER_COMPOSE_COMMAND up -d
    if [ -n "$script_file_exists" ]
    then
        $BASE_DOCKER_COMPOSE_COMMAND exec cli composer --working-dir=/dktl install
        $BASE_DOCKER_COMPOSE_COMMAND exec cli php /dktl/$script_file ${@:2}
    else
        $BASE_DOCKER_COMPOSE_COMMAND exec cli $@
    fi
fi
