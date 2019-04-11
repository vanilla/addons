#!/bin/bash

echo "Deleting previous './tests.result'"
rm -rf ./tests.result

echo "Running 'composer dump-autoload -o'"
composer dump-autoload -o

echo "Running 'phpunit phpunit.xml'"
./vendor/bin/phpunit -cphpunit.xml --bootstrap vendor/autoload.php $1
