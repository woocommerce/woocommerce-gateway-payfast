/**
 * External dependencies
 */
import { getSetting } from '@woocommerce/settings';

/**
 * PayFast data comes form the server passed on a global object.
 */
export const getPayFastServerData = () => {
	const payFastServerData = getSetting( 'payfast_data', null );
	if ( ! payFastServerData ) {
		throw new Error( 'PayFast initialization data is not available' );
	}
	return payFastServerData;
};
