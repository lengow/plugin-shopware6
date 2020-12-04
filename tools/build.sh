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
    DIRECTORY=$1
    FILE=$2
    find $DIRECTORY -name $FILE -nowarn -exec rm -rf {} \;
    echo "- Delete $FILE : ""$VERT""DONE""$NORMAL"""
}

remove_directories(){
    DIRECTORY=$1
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
FOLDER_TMP="/tmp/Lengow"
FOLDER_LOGS="/tmp/Lengow/src/Logs"
FOLDER_EXPORT="/tmp/Lengow/src/Export"
FOLDER_TOOLS="/tmp/Lengow/tools"
FOLDER_TEST="/tmp/Lengow/tests"
FOLDER_NODE="/tmp/Lengow/node_modules"
FOLDER_BIN="/tmp/Lengow/bin"
FOLDER_CONFIG="/tmp/Lengow/src/Config"
FOLDER_TRANSLATION="/tmp/Lengow/src/Translations/yml"

VERT="\\033[1;32m"
ROUGE="\\033[1;31m"
NORMAL="\\033[0;39m"
BLEU="\\033[1;36m"

# process
echo
echo "#####################################################"
echo "##                                                 ##"
echo "##       ""$BLEU""Lengow Shopware 6""$NORMAL"" - Build Module          ##"
echo "##                                                 ##"
echo "#####################################################"
echo
FOLDER="$(dirname "$(pwd)")"
echo $FOLDER
if [ ! -d "$FOLDER" ]; then
	echo "Folder doesn't exist : ""$ROUGE""ERROR""$NORMAL"""
	echo
	exit 0
fi

# generate translations
php translate.php
echo "- Generate translations : ""$VERT""DONE""$NORMAL"""
# create files checksum
php checkmd5.php
echo "- Create files checksum : ""$VERT""DONE""$NORMAL"""
# remove TMP FOLDER
remove_directory $FOLDER_TMP
# copy files
cp -rRp $FOLDER $FOLDER_TMP
# remove .gitkeep
remove_files $FOLDER_TMP ".gitkeep"
# remove dod
remove_files $FOLDER_TMP "dod.md"
# remove Readme
remove_files $FOLDER_TMP "README.md"
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
# clean Config Folder
remove_files $FOLDER_CONFIG "marketplaces.json"
# clean Log Folder
remove_files $FOLDER_LOGS "*.txt"
echo "- Clean logs folder : ""$VERT""DONE""$NORMAL"""
# Clean export folder
remove_files $FOLDER_EXPORT "*.csv"
remove_files $FOLDER_EXPORT "*.yaml"
remove_files $FOLDER_EXPORT "*.json"
remove_files $FOLDER_EXPORT "*.xml"
echo "- Clean export folder : ""$VERT""DONE""$NORMAL"""
# remove tools folder
remove_directory $FOLDER_TOOLS
echo "- Remove Tools folder : ""$VERT""DONE""$NORMAL"""
# remove yml translation folder
remove_directory $FOLDER_TRANSLATION
echo "- Remove Translation yml folder : ""$VERT""DONE""$NORMAL"""
# remove node_module folder
remove_directory $FOLDER_NODE
echo "- Remove node_module folder : ""$VERT""DONE""$NORMAL"""
# remove tests folder
remove_directory $FOLDER_TEST
echo "- Remove tests folder : ""$VERT""DONE""$NORMAL"""
# remove bin folder
remove_directory $FOLDER_BIN
echo "- Remove bin folder : ""$VERT""DONE""$NORMAL"""
# make zip
cd /tmp
zip "-r" $ARCHIVE_NAME "Lengow"
echo "- Build archive : ""$VERT""DONE""$NORMAL"""
mv $ARCHIVE_NAME ~/Bureau