#!/bin/bash
find-up () {
  path=$(pwd)
  while [[ "$path" != "" && ! -e "$path/$1" ]]; do
    path=${path%/*}
  done
  echo "$path"
}

dktl_symlink_location=$(which dktl)
dktl_executable_location=$(readlink $dktl_symlink_location)

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

# We do a little parsing of the args and perform a few transformations before
# sending to docker. Try to keep this section from bloating out of control.
if [ "$1" = "docker:compose" ] || [ "$1" = "dc" ]; then
    echo "DKTL is running on the host"
# @todo Need to get proxy support working again at some point.
elif [ "$1" = "docker:url" ]; then
    echo "DKTL is running on the host"
elif [ "$1" = "docker:surl" ]; then
    echo "DKTL is running on the host"
else
    # Check whether dkan-tools' dependencies have been initialized.
    VENDOR="$(ls -lha $DKTL_DIRECTORY | grep vendor)"
    if [ -z "$VENDOR" ]; then
        echo "Composer Install"
        composer install --working-dir=$DKTL_DIRECTORY
    fi

    php $DKTL_DIRECTORY/bin/app.php $1 "${@:2}"
fi
