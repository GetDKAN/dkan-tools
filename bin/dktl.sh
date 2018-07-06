#!/bin/bash
find-up () {
  path=$(pwd)
  while [[ "$path" != "" && ! -e "$path/$1" ]]; do
    path=${path%/*}
  done
  echo "$path"
}

if [ -z `which docker` ]; then
    echo "You don't seem to have docker installed. Exiting."; exit 1
fi
if [ -z `which docker-compose` ]; then
    echo "You don't seem to have docker-compose installed. Exiting."; exit 1
fi

dktl_symlink_location=$(which dktl)
dktl_executable_location=$(readlink -f $dktl_symlink_location)

DKTL_DIRECTORY=$(dirname $(dirname $dktl_executable_location))
export DKTL_DIRECTORY

DKTL_PROJECT_DIRECTORY=$(find-up dktl.yml)
if [ -z $DKTL_PROJECT_DIRECTORY ]; then
    DKTL_PROJECT_DIRECTORY=$(pwd)
fi
export DKTL_PROJECT_DIRECTORY

SLUG=${DKTL_PROJECT_DIRECTORY##*/}
SLUG=${SLUG//-/}
SLUG=${SLUG//_/}
SLUG=$(echo ${SLUG} | tr -d '[:space:]' | tr "[A-Z]" "[a-z]") # Mixed case dirs cause issue with docker image names
#echo $SLUG
export SLUG

DOCKER_COMPOSE_COMMON_CONF="$DKTL_DIRECTORY/assets/docker/docker-compose.common.yml"
PROXY_CONF="$DKTL_DIRECTORY/assets/docker/docker-compose.noproxy.yml"
VOLUME_CONF="$DKTL_DIRECTORY/assets/docker/docker-compose.nosync.yml"
CUSTOM_CONF="$DKTL_PROJECT_DIRECTORY/custom/docker-compose.custom.yml"


if [ -f $CUSTOM_CONF ]; then
    BASE_DOCKER_COMPOSE_COMMAND="docker-compose -f $CUSTOM_CONF -f $VOLUME_CONF -f $PROXY_CONF -p "${SLUG}" --project-directory $DKTL_PROJECT_DIRECTORY"
else
    BASE_DOCKER_COMPOSE_COMMAND="docker-compose -f $DOCKER_COMPOSE_COMMON_CONF -f $VOLUME_CONF -f $PROXY_CONF -p "${SLUG}" --project-directory $DKTL_PROJECT_DIRECTORY"
fi

#Check for proxy container, get domain from that.
PROXY_DOMAIN=`docker inspect proxy 2> /dev/null | grep docker.domain | tr -d ' ",-' | cut -d \= -f 2 | head -1`

#If no proxy is running, use the overridden or default proxy domain
if [ -z "$PROXY_DOMAIN" ]; then
  [ "$AHOY_WEB_DOMAIN" ] && WEB_DOMAIN=$AHOY_WEB_DOMAIN || WEB_DOMAIN="localtest.me"
  PROXY_DOMAIN=${WEB_DOMAIN}
fi

export PROXY_DOMAIN=$PROXY_DOMAIN

# We do a little parsing of the args and perform a few transformations before
# sending to docker. Try to keep this section from bloating out of control.
if [ "$1" = "docker:compose" ] || [ "$1" = "dc" ]; then
    $BASE_DOCKER_COMPOSE_COMMAND ${@:2}
# @todo Need to get proxy support working again at some point.
elif [ "$1" = "docker:url" ]; then
    echo "https://$PROXY_DOMAIN:$($BASE_DOCKER_COMPOSE_COMMAND port web 80|cut -d ':' -f2)"
elif [ "$1" = "docker:surl" ]; then
    echo "https://$PROXY_DOMAIN:$($BASE_DOCKER_COMPOSE_COMMAND port web 443|cut -d ':' -f2)"
elif [ "$1" = "drush:uli" ]; then
    # For drush commands, we also add the URI argument in case it's useful
    $BASE_DOCKER_COMPOSE_COMMAND exec cli php /usr/local/dkan-tools/bin/app.php drush -- uli --uri=`dktl docker:surl`
elif [ "$1" = "drush" ] || [ "$1" = "test:behat" ] || [ "$1" = "test:phpunit" ]; then
    # For several commands, we want to insert a "--" to pass all arguments as an array.
    $BASE_DOCKER_COMPOSE_COMMAND exec cli php /usr/local/dkan-tools/bin/app.php $1 -- "${@:2}"
else
    # Check whether dkan-tools' dependencies have been initialized.
    VENDOR="$($BASE_DOCKER_COMPOSE_COMMAND exec cli ls -lha /usr/local/dkan-tools | grep vendor)"
    if [ -z "$VENDOR" ]; then
        echo "Composer Install"
        $BASE_DOCKER_COMPOSE_COMMAND exec cli composer install --working-dir=/usr/local/dkan-tools/
    fi

    # If dktl is not a proper command inside of the container, create it.
    ALIAS="$($BASE_DOCKER_COMPOSE_COMMAND exec cli which dktl)"
    if [ -z "$ALIAS" ]; then
        $BASE_DOCKER_COMPOSE_COMMAND exec cli chmod 777 /usr/local/dkan-tools/bin/inner_dktl.sh
        $BASE_DOCKER_COMPOSE_COMMAND exec cli ln -s /usr/local/dkan-tools/bin/inner_dktl.sh /usr/local/bin/dktl
    fi

    $BASE_DOCKER_COMPOSE_COMMAND exec cli dktl $1 "${@:2}"
fi

# Docker creates files that appear as owned by root on host. Fix:
if [ ! -z `find $DKTL_PROJECT_DIRECTORY -user root -print -quit` ]; then
    CHOWN_CMD="sudo chown -R $USER:$USER $DKTL_PROJECT_DIRECTORY"
    echo && echo "➜  Changing ownership of new files to host user"
    echo -e "\e[32m$CHOWN_CMD\e[39m" && $CHOWN_CMD
fi
