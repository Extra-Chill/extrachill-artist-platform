import apiFetch from '@wordpress/api-fetch';
import { getLocalSupportWorkspace } from '../../src/blocks/shared/api/client';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

describe( 'Local Support API client', () => {
	beforeEach( () => {
		apiFetch.mockReset();
		apiFetch.mockResolvedValue( { artist_id: 42, workspace_url: '' } );
	} );

	it( 'requests lazy workspace discovery for one exact Artist', async () => {
		await getLocalSupportWorkspace( 42 );

		expect( apiFetch ).toHaveBeenCalledWith( {
			path: '/wp-abilities/v1/abilities/extrachill/artist-get-local-support-workspace/run?input%5Bid%5D=42',
			method: 'GET',
		} );
	} );
} );
