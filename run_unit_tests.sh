echo "## This is unit tests script"
export APP_ENV=testing

./vendor/phpunit/phpunit/phpunit --testsuite Unit

OUT=$?