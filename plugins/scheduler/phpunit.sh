#!/bin/bash

echo "Deleting previous './tests/_coverage'"
rm -rf ./tests/_coverage

echo "Deleting previous 'tests/_testdox.html'"
rm -f ./tests/_testdox.html

echo "Running 'composer dump-autoload -o'"
composer dump-autoload -o

echo "Running 'phpunit phpunit.xml'"
./vendor/bin/phpunit -cphpunit.xml --bootstrap vendor/autoload.php $1
