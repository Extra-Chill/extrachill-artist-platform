import {
	getArtist,
	getLinks,
	getSocials,
	updateArtist,
	updateLinks,
	updateSocials,
} from '../../src/blocks/shared/api/client';
import { adapter } from '../../src/blocks/link-page-editor/adapter';

jest.mock( '../../src/blocks/shared/api/client', () => ( {
	getArtist: jest.fn( async () => ( { id: 7, name: 'Identity' } ) ),
	getLinks: jest.fn( async () => ( {
		links: [],
		settings: {},
		css_vars: {},
	} ) ),
	getSocials: jest.fn( async () => ( { social_links: [] } ) ),
	updateArtist: jest.fn( async () => ( {} ) ),
	updateLinks: jest.fn( async () => ( {} ) ),
	updateSocials: jest.fn( async () => ( {} ) ),
	uploadMedia: jest.fn(),
	deleteMedia: jest.fn(),
	generateQRCode: jest.fn(),
} ) );

const draft = {
	identity: { name: 'Changed', imageId: 9 },
	page: {
		links: [],
		settings: {},
		bio: '',
		styles: {},
		backgroundImageId: 0,
	},
	socials: [],
};

describe( 'shared editor Artist adapter', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'saves only identity resources when only identity is dirty', async () => {
		await adapter.save( 7, draft, { dirtyAreas: [ 'identity' ] } );
		expect( updateArtist ).toHaveBeenCalledTimes( 1 );
		expect( updateLinks ).not.toHaveBeenCalled();
		expect( updateSocials ).not.toHaveBeenCalled();
		expect( getArtist ).toHaveBeenCalledWith( 7 );
		expect( getLinks ).toHaveBeenCalledWith( 7 );
		expect( getSocials ).toHaveBeenCalledWith( 7 );
	} );

	it( 'keeps unrelated resources untouched for page and social saves', async () => {
		await adapter.save( 7, draft, { dirtyAreas: [ 'links' ] } );
		expect( updateLinks ).toHaveBeenCalledTimes( 1 );
		expect( updateArtist ).not.toHaveBeenCalled();
		expect( updateSocials ).not.toHaveBeenCalled();

		jest.clearAllMocks();
		await adapter.save( 7, draft, { dirtyAreas: [ 'socials' ] } );
		expect( updateSocials ).toHaveBeenCalledTimes( 1 );
		expect( updateArtist ).not.toHaveBeenCalled();
		expect( updateLinks ).not.toHaveBeenCalled();
	} );
} );
