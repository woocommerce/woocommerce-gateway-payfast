/* eslint-disable no-console */
/**
 * Global setup for e2e tests.
 */

/**
 * External dependencies
 */
const {chromium, expect} = require( '@playwright/test' );

/**
 * Internal dependencies
 */
const fs = require( 'fs' );
const {admin, customer} = require( './config' );
const {login} = require( './utils' );

module.exports = async ( config ) => {
	const {stateDir, baseURL, userAgent} = config.projects[0].use;

	console.log( `State Dir: ${stateDir}` );
	console.log( `Base URL: ${baseURL}` );

	// used throughout tests for authentication
	process.env.ADMINSTATE = `${stateDir}adminState.json`;
	process.env.CUSTOMERSTATE = `${stateDir}customerState.json`;
	process.env.baseURL = baseURL;

	// Clear out the previous save states
	try {
		fs.unlinkSync( process.env.ADMINSTATE );
		console.log( 'Admin state file deleted successfully.' );
	} catch ( err ) {
		if ( err.code === 'ENOENT' ) {
			console.log( 'Admin state file does not exist.' );
		} else {
			console.log( 'Admin state file could not be deleted: ' + err );
		}
	}
	try {
		fs.unlinkSync( process.env.CUSTOMERSTATE );
		console.log( 'Customer state file deleted successfully.' );
	} catch ( err ) {
		if ( err.code === 'ENOENT' ) {
			console.log( 'Customer state file does not exist.' );
		} else {
			console.log( 'Customer state file could not be deleted: ' + err );
		}
	}

	// Specify user agent when running against an external test site to avoid getting HTTP 406 NOT ACCEPTABLE errors.
	const contextOptions = {baseURL, userAgent};

	// Create browser, browserContext, and page for customer and admin users
	const browser = await chromium.launch();
	const adminContext = await browser.newContext( contextOptions );
	const customerContext = await browser.newContext( contextOptions );
	const adminPage = await adminContext.newPage();
	const customerPage = await customerContext.newPage();

	try {
		// Sign in as admin user and save state
		const adminRetries = 5;
		await login( adminPage, admin.username, admin.password, adminRetries );
		// Save state.
		await adminPage
			.context()
			.storageState( {path: process.env.ADMINSTATE} );

		console.log( 'Logged-in as admin successfully.' );

		// Sign in as customer user and save state
		const customerRetries = 5;
		await login( customerPage, customer.username, customer.password, customerRetries );
		// Save state.
		await customerPage
			.context()
			.storageState( {path: process.env.CUSTOMERSTATE} );

		console.log( 'Logged-in as customer successfully.' );
	} catch ( error ) {
		console.error( 'Failed to log in as admin or customer:', error );
		process.exit( 1 );
	} finally {
		await adminContext.close();
		await customerContext.close();
		await browser.close();
	}
};
