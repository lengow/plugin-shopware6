#!/bin/bash

set -o errexit
echo "\e[94mPlease enter absolute filepath to shopware devellopement or production directory"
echo "\e[94mexample for devellopement : /home/username/dev/shopware6/devellopement"
read SHOPWAREPATH

copy_directory(){
    ORIGINAL_DIRECTORY="$(dirname "$(pwd)")"
    DESTINATION_DIRECTORY="$SHOPWAREPATH/custom/plugins/Lengow"
    if [ -d "$ORIGINAL_DIRECTORY" ]; then
        if [ -e "$DESTINATION_DIRECTORY" ]; then
            unlink $DESTINATION_DIRECTORY
        fi
        ln -s $ORIGINAL_DIRECTORY $DESTINATION_DIRECTORY
        echo "\e[92m✔ Symlink created : $DESTINATION_DIRECTORY"
        echo "\e[93mto complete the installation, please use shopware CLI to refresh & then install the plugin"
        echo "\e[93mrebuilding the administration may be necessary"
    else
        echo "⚠ Missing directory : $ORIGINAL_DIRECTORY"
    fi
    return $TRUE
}

copy_directory "/"

exit 0;