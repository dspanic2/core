#!/bin/sh
#
# An example hook script to verify what is about to be committed.
# Called by "git commit" with no arguments.  The hook should
# exit with non-zero status after issuing an appropriate message if
# it wants to stop the commit.
#
# To enable this hook, rename this file to "pre-commit".

cd "$(dirname "$0")"

cd ../../ # Project directory

./vendor/bin/phpunit --configuration src/ShapeUnitTestingBundle/phpunit.xml
TEST_EXIT_CODE=$?
if [ $TEST_EXIT_CODE == 1 ]; then
  echo "Commit failed! Check tests! Run manually with composer run-tests"
  exit 1
fi
