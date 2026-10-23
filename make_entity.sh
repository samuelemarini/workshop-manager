#!/bin/sh
DRUN_CMD_SBUILDER="docker compose run -v ${HOME}/.gitconfig:/etc/gitconfig -v ${PWD}/app:/app -w/app web" 
$DRUN_CMD_SBUILDER php bin/console make:entity