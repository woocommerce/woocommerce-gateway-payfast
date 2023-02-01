/**
 * External dependencies
 */
import { decodeEntities } from '@wordpress/html-entities';
import { __ } from '@wordpress/i18n';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME } from './constants';
import { getPayFastServerData } from './payfast-utils';

const Content = () => {
	return decodeEntities(getPayFastServerData()?.description || '');
};

const Label = () => {
	return (
		<img
			src={getPayFastServerData()?.logo_url}
			alt={getPayFastServerData()?.title}
		/>
	);
};

registerPaymentMethod({
	name: PAYMENT_METHOD_NAME,
	label: <Label />,
	ariaLabel: __('Payfast payment method', 'woocommerce-gateway-payfast'),
	canMakePayment: () => true,
	content: <Content />,
	edit: <Content />,
	supports: {
		features: getPayFastServerData()?.supports ?? [],
	},
});
