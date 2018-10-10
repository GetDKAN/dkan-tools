#!/bin/bash

find-up () {
  path=$(pwd)
  while [[ "$path" != "" && ! -e "$path/$1" ]]; do
    path=${path%/*}
  done
  echo "$path"
}

# Determine whether we want to run inside the docker container or in the host machine.

if [ -z $DKTL_MODE ] || [ "$DKTL_MODE" = "DOCKER" ]; then
  DKTL_MODE="DOCKER"
elif [ "$DKTL_MODE" = "HOST" ]; then
  DKTL_MODE="HOST"
else
  echo "Incorrect DKTL_MODE set to ${DKTL_MODE}. Appropriate values are 'DOCKER'and 'HOST'."
  exit 1
fi

# Check dependencies.

if [ "$DKTL_MODE" = "DOCKER" ]; then
  if [ -z `which docker` ] || [ -z `which docker-compose` ]; then
      echo "docker and docker-compose are required. Exiting."
      exit 1
  fi
else
  if [ -z `which php` ] || [ -z `which composer` ] || [ -z `which drush` ]; then
    echo "php, composer and drush are required. Exiting."
    exit 1
  fi
fi

# Basic Information

DKTL_PROJECT_DIRECTORY=$(find-up dktl.yml)
if [ -z "$DKTL_PROJECT_DIRECTORY" ]; then
  if [ "$1" = "init" ]; then
    DKTL_PROJECT_DIRECTORY=$(pwd)
  else
    echo "DKTL is running outside of a DKTL project. Run dktl init in the project directory first."
    exit 1
  fi
fi
export DKTL_PROJECT_DIRECTORY

DKTL_DIRECTORY=$(which dktl)
DKTL_DIRECTORY=$(readlink $DKTL_DIRECTORY)
DKTL_DIRECTORY=$(dirname $(dirname $DKTL_DIRECTORY))
export DKTL_DIRECTORY

DKTL_SLUG=${DKTL_PROJECT_DIRECTORY##*/}
DKTL_SLUG=${DKTL_SLUG//-/}
DKTL_SLUG=${DKTL_SLUG//_/}
DKTL_SLUG=$(echo ${DKTL_SLUG} | tr -d '[:space:]' | tr "[A-Z]" "[a-z]") # Mixed case dirs cause issue with docker image names
export DKTL_SLUG

# Setup for Docker mode and Docker specific commands.
if [ "$DKTL_MODE" = "DOCKER" ]; then

  # Check for proxy container, get domain from that.
  PROXY_DOMAIN=`docker inspect proxy 2> /dev/null | grep docker.domain | tr -d ' ",-' | cut -d \= -f 2 | head -1`
  # If no proxy is running, use the overridden or default proxy domain
  if [ -z "$PROXY_DOMAIN" ]; then
    [ "$AHOY_WEB_DOMAIN" ] && WEB_DOMAIN=$AHOY_WEB_DOMAIN || WEB_DOMAIN="localtest.me"
    PROXY_DOMAIN=${WEB_DOMAIN}
  fi
  export DKTL_PROXY_DOMAIN=$PROXY_DOMAIN

  DOCKER_COMPOSE_COMMON_CONF="$DKTL_DIRECTORY/assets/docker/docker-compose.common.yml"
  PROXY_CONF="$DKTL_DIRECTORY/assets/docker/docker-compose.noproxy.yml"
  VOLUME_CONF="$DKTL_DIRECTORY/assets/docker/docker-compose.nosync.yml"
  CUSTOM_CONF="$DKTL_PROJECT_DIRECTORY/src/docker/docker-compose.custom.yml"
  if [ -f $CUSTOM_CONF ]; then
      BASE_DOCKER_COMPOSE_COMMAND="docker-compose -f $CUSTOM_CONF -f $VOLUME_CONF -f $PROXY_CONF -p "${DKTL_SLUG}" --project-directory $DKTL_PROJECT_DIRECTORY"
  else
      BASE_DOCKER_COMPOSE_COMMAND="docker-compose -f $DOCKER_COMPOSE_COMMON_CONF -f $VOLUME_CONF -f $PROXY_CONF -p "${DKTL_SLUG}" --project-directory $DKTL_PROJECT_DIRECTORY"
  fi

  if [ "$1" = "docker:compose" ] || [ "$1" = "dc" ]; then
    $BASE_DOCKER_COMPOSE_COMMAND ${@:2}
    exit 0
  elif [ "$1" = "docker:url" ]; then
    echo "http://$PROXY_DOMAIN:$($BASE_DOCKER_COMPOSE_COMMAND port web 80|cut -d ':' -f2)"
    exit 0
  elif [ "$1" = "docker:surl" ]; then
    echo "https://$PROXY_DOMAIN:$($BASE_DOCKER_COMPOSE_COMMAND port web 443|cut -d ':' -f2)"
    exit 0
  fi
fi

if [ "$DKTL_MODE" = "HOST" ]; then
  if [ "$1" = "docker:compose" ] || [ "$1" = "dc" ] || [ "$1" = "docker:url" ] || [ "$1" = "docker:surl" ]; then
    echo "${1} is not available in 'HOST' mode"
    exit 1
  fi
fi

# Other commands
if [ "$DKTL_MODE" = "DOCKER" ]; then

  # Check containers state if in DOCKER mode
  containers=$($BASE_DOCKER_COMPOSE_COMMAND top)
  if [ -z "$containers" ]; then
    echo "Starting docker containers."
    $BASE_DOCKER_COMPOSE_COMMAND up -d
  fi

  # The containers are running, set DKTL inside the cli container.
  ALIAS="$($BASE_DOCKER_COMPOSE_COMMAND exec cli which dktl)"
  if [ -z "$ALIAS" ]; then
      $BASE_DOCKER_COMPOSE_COMMAND exec cli chmod 777 /usr/local/dkan-tools/bin/dktl.sh
      $BASE_DOCKER_COMPOSE_COMMAND exec cli ln -s /usr/local/dkan-tools/bin/dktl.sh /usr/local/bin/dktl
  fi

  # Proxy pass to internal DKTL
  $BASE_DOCKER_COMPOSE_COMMAND exec cli dktl $1 "${@:2}"

  if [ -z $DKTL_CHOWN ] || [ "$DKTL_CHOWN" = "TRUE" ]; then
    # Docker creates files that appear as owned by root on host. Fix:
    if [ ! -z `find $DKTL_PROJECT_DIRECTORY -user root -print -quit` ]; then
      CHOWN_CMD="sudo chown -R $USER:$USER $DKTL_PROJECT_DIRECTORY"
      echo && echo "➜  Changing ownership of new files to host user"
      echo -e "\e[32m$CHOWN_CMD\e[39m" && $CHOWN_CMD
    fi
  fi
fi

if [ "$DKTL_MODE" = "HOST" ]; then
  if [ "$1" = "drush" ] || [ "$1" = "test:behat" ] || [ "$1" = "test:phpunit" ] || [ "$1" = "test:lint" ] || [ "$1" = "test:lint-fix" ]; then
    # For several commands, we want to insert a "--" to pass all arguments as an array.
    php $DKTL_DIRECTORY/bin/app.php $1 -- "${@:2}"
  else
    # Check whether dkan-tools' dependencies have been initialized.
    VENDOR="$(ls -lha $DKTL_DIRECTORY | grep vendor)"
    if [ -z "$VENDOR" ]; then
      echo "Composer Install"
      composer install --working-dir=$DKTL_DIRECTORY
    fi
    php $DKTL_DIRECTORY/bin/app.php $1 "${@:2}"
  fi
fi