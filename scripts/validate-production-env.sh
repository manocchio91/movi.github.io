#!/usr/bin/env sh
set -eu
exec sh "$(dirname "$0")/validate-staging-env.sh" "${1:-.env.production}" production
