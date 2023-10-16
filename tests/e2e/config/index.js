const admin = {
	username: 'admin',
	password: 'password',
	store: {
		firstname: 'John',
		lastname: 'Doe',
		company: 'Automattic',
		country: 'US:CA',
		addressfirstline: 'addr 1',
		addresssecondline: 'addr 2',
		city: 'San Francisco',
		state: 'CA',
		postcode: '94107',
	},
};

const customer = {
	username: 'customer',
	password: 'password',
	billing: {
		firstname: 'John',
		lastname: 'Doe',
		company: 'Automattic',
		country: 'FI',
		countryName: 'Finland',
		addressfirstline: 'addr 1',
		addresssecondline: 'addr 2',
		city: 'Helsinki',
		state: '',
		postcode: '00100',
		phone: '123456789',
		email: 'john.doe@example.com',
	},
	shipping: {
		firstname: 'John',
		lastname: 'Doe',
		company: 'Automattic',
		country: 'US',
		addressfirstline: 'addr 1',
		addresssecondline: 'addr 2',
		city: 'San Francisco',
		state: 'CA',
		postcode: '94107',
	},
};

const payfastSandboxCredentials = {
	merchantId: process.env.PAYFAST_MERCHANT_ID || '',
	merchantKey: process.env.PAYFAST_MERCHANT_KEY || '',
	passPharse: process.env.PAYFAST_PASSPHRASE || ''
}

export {admin, customer, payfastSandboxCredentials};
