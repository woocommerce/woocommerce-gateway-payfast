#!/bin/bash

# Clone latest version of a plugin (fallback to default branch if latest version is not found)
install_plugin_from_repo() {
  local PLUGIN_SLUG=$1
  local DIR=$2

  rm -rf "${DIR}"
  git clone --quiet --depth=1 "git@github.com:woocommerce/${PLUGIN_SLUG}.git" "${DIR}"

  # Parse latest version from main plugin file
  local LATEST_VERSION=$(grep -o '\* Version:.*' ${DIR}/${PLUGIN_SLUG}.php | sed 's/* Version://' | tr -d ' ')
  echo Latest version ${LATEST_VERSION} for ${PLUGIN_SLUG}

  # Fetch a specific tag
  cd "${DIR}"
  git fetch --depth 1 "git@github.com:woocommerce/${PLUGIN_SLUG}.git" tag "${LATEST_VERSION}"

  # If the latest version tag is available then switch
  if [ $(git tag -l "${LATEST_VERSION}") ]; then
    git checkout "${LATEST_VERSION}" --quiet
  fi

  cd -
}

# Install Subscriptions.
install_plugin_from_repo "woocommerce-subscriptions" "./tests/e2e/test-plugins/woocommerce-subscriptions"
cd ./tests/e2e/test-plugins/woocommerce-subscriptions
npm install && npm run build
cd -
