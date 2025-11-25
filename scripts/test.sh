#!/bin/bash
#
# Run PHPUnit tests in Docker container
#
# Usage:
#   ./scripts/test.sh              # Run with default PHP version (7.4)
#   ./scripts/test.sh 8.1          # Run with PHP 8.1
#   ./scripts/test.sh all          # Run with all PHP versions
#   ./scripts/test.sh 7.4 tests/Unit/Reporter  # Run specific tests

set -e

PHP_VERSION="${1:-7.4}"
TEST_PATH="${2:-}"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

run_tests() {
    local version=$1
    local service="php${version//./}"

    echo -e "${YELLOW}=== Running tests with PHP ${version} ===${NC}"

    # Build and run
    docker-compose build "${service}" --quiet
    docker-compose run --rm "${service}" vendor/bin/phpunit ${TEST_PATH}

    local exit_code=$?
    if [ $exit_code -eq 0 ]; then
        echo -e "${GREEN}PHP ${version}: All tests passed${NC}"
    else
        echo -e "${RED}PHP ${version}: Tests failed${NC}"
        return $exit_code
    fi
}

if [ "$PHP_VERSION" = "all" ]; then
    # Run with all supported PHP versions
    VERSIONS=("7.4" "8.0" "8.1" "8.2" "8.3")
    FAILED=()

    for version in "${VERSIONS[@]}"; do
        if ! run_tests "$version"; then
            FAILED+=("$version")
        fi
        echo ""
    done

    # Summary
    echo "=== Summary ==="
    if [ ${#FAILED[@]} -eq 0 ]; then
        echo -e "${GREEN}All PHP versions passed!${NC}"
    else
        echo -e "${RED}Failed versions: ${FAILED[*]}${NC}"
        exit 1
    fi
else
    run_tests "$PHP_VERSION"
fi
