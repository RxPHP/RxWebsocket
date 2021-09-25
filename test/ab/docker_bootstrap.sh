#!/bin/bash
set -x

echo "Running $0"

echo Adding "$1 host.dock.internal" to /etc/hosts file

echo $1 host.dock.internal >> /etc/hosts

echo /etc/hosts contains:
cat /etc/hosts
echo
