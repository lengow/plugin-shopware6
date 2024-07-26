#!/bin/bash
# Build archive for Shopware 6 module
# Step :
#     - Remove .DS_Store
#     - Remove .README.md
#     - Remove .idea
#     - Clean export folder
#     - Clean logs folder
#     - Clean translation folder
#     - Clean tools folder
#     - Remove .gitFolder and .gitignore

remove_if_exist(){
    if [ -f $1 ]; then
      rm $1
    fi
}

remove_directory(){
    if [ -d "$1" ]; then
        rm -rf $1
    fi
}
remove_files(){
    DIRECTORY="$1"
    FILE="$2"
    if [ -f "${DIRECTORY}/${FILE}" ]; then
        find "$DIRECTORY" -name "$FILE" -exec rm -rf {} \;
        echo -e "- Delete ${FILE} : ${VERT}DONE${NORMAL}"
    fi
    if [ -d "${DIRECTORY}/${FILE}" ]; then
        rm -Rf "${DIRECTORY}/${FILE}"
    fi
}

remove_directories(){
    DIRECTORY="$1"
    find $DIRECTORY -maxdepth 1 -mindepth 1 -type d -exec rm -rf {} \;
    echo "- Delete $FILE : ""$VERT""DONE""$NORMAL"""
}
# check parameters
if [ -z "$1" ]; then
	echo 'Version parameter is not set'
	echo
	exit 0
else
	VERSION="$1"
	ARCHIVE_NAME='lengow.shopware6.'$VERSION'.zip'
fi

# variables
FOLDER_TMP="/tmp/LengowConnector"
FOLDER_LOGS="/tmp/LengowConnector/src/Logs"
FOLDER_EXPORT="/tmp/LengowConnector/src/Export"
FOLDER_TOOLS="/tmp/LengowConnector/tools"
FOLDER_TEST="/tmp/LengowConnector/tests"
FOLDER_NODE="/tmp/LengowConnector/node_modules"
FOLDER_BIN="/tmp/LengowConnector/bin"
FOLDER_CONFIG="/tmp/LengowConnector/src/Config"
FOLDER_TRANSLATION="/tmp/LengowConnector/src/Translations/yml"

VERT="\\033[1;32m"
ROUGE="\\033[1;31m"
NORMAL="\\033[0;39m"
BLEU="\\033[1;36m"

# process
echo
echo "#####################################################"
echo "##                                                 ##"
echo -e "##       "${BLEU}Lengow Shopware6${NORMAL}" - Build Module           ##"
echo "##                                                 ##"
echo "#####################################################"
echo
sleep 3
FOLDER="$(dirname "$(pwd)")"
echo $FOLDER
sleep 2
if [ ! -d "$FOLDER" ]; then
	echo -e "Folder doesn't exist : ${ROUGE}ERROR${NORMAL}"
	echo
	exit 0
fi

# generate translations
php translate.php
echo -e "- Generate translations : ${VERT}DONE${NORMAL}"
# create files checksum
php checkmd5.php
echo -e "- Create files checksum : ${VERT}DONE${NORMAL}"
sleep 3
# remove TMP FOLDER
rm -Rf "$FOLDER_TMP/*"
remove_directory $FOLDER_TMP
# copy files
# copy files
rsync -a --exclude='.DS_Store' "$FOLDER/" "$FOLDER_TMP/"
# remove .gitkeep
remove_files $FOLDER_TMP ".gitkeep"
# remove dod
remove_files $FOLDER_TMP "dod.md"
# remove Readme
remove_files $FOLDER_TMP "README.md"
# remove licence
remove_files $FOLDER_TMP "LICENCE.md"
# remove .git
remove_files $FOLDER_TMP ".git"
# remove .gitignore
remove_files $FOLDER_TMP ".gitignore"
# remove .DS_Store
remove_files $FOLDER_TMP ".DS_Store"
# remove .idea
remove_files $FOLDER_TMP ".idea"
# remove .eslintrc
remove_files $FOLDER_TMP ".eslintrc.js"
remove_files $FOLDER_TMP ".eslintrc.json"
# remove package-lock
remove_files $FOLDER_TMP "package-lock.json"
# remove phpunit
remove_files $FOLDER_TMP "phpunit.xml.dist"
# remove Jenkinsfile
remove_files $FOLDER_TMP "Jenkinsfile"
# clean Config Folder
remove_files $FOLDER_CONFIG "marketplaces.json"
# clean Log Folder
remove_files $FOLDER_LOGS "*.txt"
echo -e "- Clean logs folder : ${VERT}DONE${NORMAL}"
# Clean export folder
remove_files $FOLDER_EXPORT "*.csv"
remove_files $FOLDER_EXPORT "*.yaml"
remove_files $FOLDER_EXPORT "*.json"
remove_files $FOLDER_EXPORT "*.xml"
echo -e "- Clean export folder : ${VERT}DONE${NORMAL}"
# remove tools folder
remove_directory $FOLDER_TOOLS
echo -e "- Remove Tools folder : ${VERT}DONE${NORMAL}"
# remove yml translation folder
remove_directory $FOLDER_TRANSLATION
echo -e "- Remove Translation yml folder :${VERT}DONE${NORMAL}"
# remove node_module folder
remove_directory $FOLDER_NODE
echo -e "- Remove node_module folder :${VERT}DONE${NORMAL}"
# remove tests folder
remove_directory $FOLDER_TEST
echo -e "- Remove tests folder : ${VERT}DONE${NORMAL}"
# remove bin folder
remove_directory $FOLDER_BIN
echo -e "- Remove bin folder : ${VERT}DONE${NORMAL}"
sleep 3
# make zip
# make zip
cd /tmp
zip "-r" "$ARCHIVE_NAME" "LengowConnector"
echo -e "- Build archive : ${VERT}DONE${NORMAL}"
if [ -d  "~/Bureau" ]; then
    mv "$ARCHIVE_NAME" ~/Bureau
else
    mv "$ARCHIVE_NAME" ~/shared
fi
sleep 3
echo "End of build Shopware6 plugin."
