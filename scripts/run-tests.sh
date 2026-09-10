#!/bin/sh
# Runs the test suite and writes both a human-readable and a JUnit
# report to test-results/. Kept as an actual script file rather than
# an inline docker-compose `command:` string: a multi-line YAML folded
# scalar containing a quoted shell one-liner with a pipe in it is a
# well-known way to get subtly mis-joined lines depending on
# indentation - which is exactly what broke the first version of this
# setup ("sh: 3: Syntax error: "|" unexpected").
set -e

mkdir -p test-results

vendor/bin/phpunit --testdox --log-junit test-results/junit.xml \
    | tee test-results/output.txt
