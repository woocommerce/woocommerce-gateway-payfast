#!/usr/bin/env node
/**
 * Pins the WordPress and WooCommerce versions of the PHPUnit wp-env environment.
 *
 * Usage: node tests/phpunit/bin/set-versions.js [--wp=<version>] [--wc=<version>]
 *
 * A version is `latest`, an exact release such as `6.9.1`, or a support policy
 * selector `L`, `L-1`, or `L-2`. A policy selector resolves to the newest patch
 * release of that major version, so `L-1` with WordPress 7.1.2 as the latest
 * release resolves to the newest 7.0.x release.
 *
 * The versions are written to `.wp-env.phpunit.override.json`. Restart the
 * environment with `npm run test:php:env:start` afterwards. Passing `latest`
 * for both versions deletes the override file.
 */

const fs = require('fs');
const path = require('path');

const baseConfigPath = path.resolve(__dirname, '../../../.wp-env.phpunit.json');
const overridePath = path.resolve(
	__dirname,
	'../../../.wp-env.phpunit.override.json'
);

const STABLE_VERSION = /^\d+\.\d+(\.\d+)?$/;
const POLICY_SELECTOR = /^L(-(\d+))?$/;

/**
 * Parses `--name=value` arguments.
 *
 * @param {string[]} argv Command line arguments.
 * @return {Object} Parsed arguments.
 */
function parseArgs(argv) {
	const args = {};
	for (const arg of argv) {
		const match = arg.match(/^--(wp|wc)=(.+)$/);
		if (!match) {
			throw new Error(`Unknown argument: ${arg}`);
		}
		args[match[1]] = match[2];
	}
	return args;
}

/**
 * Compares two version strings numerically.
 *
 * @param {string} a First version.
 * @param {string} b Second version.
 * @return {number} Negative, zero, or positive, like a sort comparator.
 */
function compareVersions(a, b) {
	const pa = a.split('.').map(Number);
	const pb = b.split('.').map(Number);
	for (let i = 0; i < Math.max(pa.length, pb.length); i++) {
		const diff = (pa[i] || 0) - (pb[i] || 0);
		if (diff !== 0) {
			return diff;
		}
	}
	return 0;
}

/**
 * Resolves a selector against a list of released versions.
 *
 * @param {string}   selector Version selector.
 * @param {string[]} released Released versions.
 * @param {string}   name     Product name, for error messages.
 * @return {string} The resolved version.
 */
function resolveVersion(selector, released, name) {
	const stable = released.filter((v) => STABLE_VERSION.test(v));

	if (STABLE_VERSION.test(selector)) {
		if (!stable.includes(selector)) {
			throw new Error(`${name} ${selector} is not a released version.`);
		}
		return selector;
	}

	const policy =
		selector === 'latest' ? ['L'] : selector.match(POLICY_SELECTOR);
	if (!policy) {
		throw new Error(`Invalid ${name} version: ${selector}`);
	}
	const offset = policy[2] ? Number(policy[2]) : 0;

	// Keep the newest release of each major version (X.Y), newest major first.
	const newestByMajor = new Map();
	for (const version of stable) {
		const major = version.split('.').slice(0, 2).join('.');
		const current = newestByMajor.get(major);
		if (!current || compareVersions(version, current) > 0) {
			newestByMajor.set(major, version);
		}
	}
	const majors = [...newestByMajor.keys()].sort(compareVersions).reverse();

	if (offset >= majors.length) {
		throw new Error(`Cannot resolve ${name} ${selector}.`);
	}
	return newestByMajor.get(majors[offset]);
}

/**
 * Fetches JSON from a URL.
 *
 * @param {string} url URL to fetch.
 * @return {Promise<Object>} Parsed response.
 */
async function fetchJson(url) {
	const response = await fetch(url);
	if (!response.ok) {
		throw new Error(`Request to ${url} failed with ${response.status}.`);
	}
	return response.json();
}

async function main() {
	const args = parseArgs(process.argv.slice(2));
	const wpSelector = args.wp || 'latest';
	const wcSelector = args.wc || 'latest';

	if (wpSelector === 'latest' && wcSelector === 'latest') {
		fs.rmSync(overridePath, { force: true });
		console.log('Using the latest WordPress and WooCommerce.');
		return;
	}

	const override = {};

	if (wpSelector !== 'latest') {
		// The stable-check API lists every WordPress release.
		const wpReleases = await fetchJson(
			'https://api.wordpress.org/core/stable-check/1.0/'
		);
		const wpVersion = resolveVersion(
			wpSelector,
			Object.keys(wpReleases),
			'WordPress'
		);
		override.core = `https://wordpress.org/wordpress-${wpVersion}.zip`;
		console.log(`WordPress ${wpSelector} => ${wpVersion}`);
	}

	if (wcSelector !== 'latest') {
		const wcInfo = await fetchJson(
			'https://api.wordpress.org/plugins/info/1.0/woocommerce.json'
		);
		const wcVersion = resolveVersion(
			wcSelector,
			Object.keys(wcInfo.versions),
			'WooCommerce'
		);
		console.log(`WooCommerce ${wcSelector} => ${wcVersion}`);

		// wp-env replaces, rather than merges, the plugin list from an override file.
		const baseConfig = JSON.parse(fs.readFileSync(baseConfigPath, 'utf8'));
		override.plugins = baseConfig.plugins.map((plugin) =>
			plugin.includes('/plugin/woocommerce.zip')
				? `https://downloads.wordpress.org/plugin/woocommerce.${wcVersion}.zip`
				: plugin
		);
	}

	fs.writeFileSync(overridePath, JSON.stringify(override, null, '\t') + '\n');
	console.log(`Wrote ${path.basename(overridePath)}.`);
}

main().catch((error) => {
	console.error(error.message);
	process.exit(1);
});
