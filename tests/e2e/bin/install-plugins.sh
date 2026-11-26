#!/bin/bash

# Install a plugin from GitHub using the gh CLI
install_plugin_with_gh_cli() {
  local PLUGIN_SLUG=$1
  local DIR=$2

  GH_COMMAND=$(which gh)
  if [ -z "$GH_COMMAND" ]; then
    echo "gh command not found; installing using git"
    exit 1
  fi

  echo "Downloading ${PLUGIN_SLUG} from GitHub using gh CLI"

  gh release download --repo woocommerce/${PLUGIN_SLUG} --pattern "*.zip" --dir ${DIR}
  if [ $? -ne 0 ]; then
    echo "Failed to download ${PLUGIN_SLUG} from GitHub using gh CLI"
    exit 1
  fi

  unzip -oq ${DIR}/${PLUGIN_SLUG}.zip -d ${DIR}
  if [ $? -ne 0 ]; then
    echo "Failed to unzip ${PLUGIN_SLUG} downloaded from GitHub"
    exit 1
  fi

  rm ${DIR}/${PLUGIN_SLUG}.zip
  if [ $? -ne 0 ]; then
    echo "Failed to remove ${PLUGIN_SLUG} zip file"
    exit 1
  fi
}

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
install_plugin_with_gh_cli "woocommerce-subscriptions" "./tests/e2e/test-plugins"

if [ $? -ne 0 ]; then
  install_plugin_from_repo "woocommerce-subscriptions" "./tests/e2e/test-plugins/woocommerce-subscriptions"
  if [ $? -ne 0 ]; then
    echo "Failed to install woocommerce-subscriptions"
    exit 1
  fi
  # If we cloned the repo, manually build the plugin
  cd ./tests/e2e/test-plugins/woocommerce-subscriptions
  composer install --no-dev && npm install && npm run build
  cd -
fi
