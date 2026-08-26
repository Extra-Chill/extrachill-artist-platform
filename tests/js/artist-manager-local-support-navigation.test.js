// React's test-only act helper is supplied transitively by @wordpress/element.
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';
import { createRoot } from '@wordpress/element';
import { App } from '../../src/blocks/artist-manager/view';
import { getArtist } from '../../src/blocks/shared/api/client';

jest.mock(
	'../../src/blocks/artist-manager/components/LocalSupportTab',
	() =>
		( { onDirtyChange } ) => (
			<div data-local-support-panel>
				<button type="button" onClick={ () => onDirtyChange( true ) }>
					Make availability dirty
				</button>
			</div>
		)
);

jest.mock(
	'../../src/blocks/shared/components/ArtistSwitcher',
	() =>
		( { onChange, selectedId } ) => (
			<button
				type="button"
				data-selected-artist={ selectedId }
				onClick={ () => onChange( 43 ) }
			>
				Switch Artist
			</button>
		)
);

jest.mock( '@extrachill/components', () => ( {
	ActionRow: ( { children } ) => <div>{ children }</div>,
	Badge: ( { children } ) => <span>{ children }</span>,
	BlockShell: ( { children } ) => <div>{ children }</div>,
	BlockShellInner: ( { children } ) => <div>{ children }</div>,
	BlockShellHeader: ( { actions, title } ) => (
		<header>
			<h1>{ title }</h1>
			{ actions }
		</header>
	),
	FieldGroup: ( { children } ) => <div>{ children }</div>,
	ImagePreview: () => null,
	InlineStatus: ( { children } ) => <div>{ children }</div>,
	MediaField: () => null,
	Panel: ( { children } ) => <div>{ children }</div>,
	PanelHeader: ( { actions } ) => <div>{ actions }</div>,
	ResponsiveTabs: ( {
		tabs,
		active,
		onChange,
		renderPanel,
		classPrefix = 'ec-responsive-tabs',
	} ) => (
		<div>
			{ tabs.map( ( tab ) => (
				<button
					type="button"
					data-tab={ tab.id }
					key={ tab.id }
					onClick={ () => onChange( tab.id ) }
				>
					{ tab.label }
				</button>
			) ) }
			<div className="is-active">
				<button type="button" className={ `${ classPrefix }__trigger` }>
					Collapse active mobile panel
				</button>
			</div>
			<div>
				<button
					type="button"
					className={ `${ classPrefix }__trigger` }
					data-mobile-tab="info"
					onClick={ () => onChange( 'info' ) }
				>
					Open Info mobile panel
				</button>
			</div>
			{ renderPanel( active ) }
		</div>
	),
} ) );

jest.mock( '../../src/blocks/shared/api/client', () => ( {
	cancelRosterInvite: jest.fn(),
	deleteMedia: jest.fn(),
	exportSubscribers: jest.fn(),
	getArtist: jest.fn(),
	getRoster: jest.fn().mockResolvedValue( { members: [], invites: [] } ),
	getSubscribers: jest.fn().mockResolvedValue( { subscribers: [] } ),
	inviteRosterMember: jest.fn(),
	removeRosterMember: jest.fn(),
	searchArtistCapableUsers: jest.fn(),
	updateArtist: jest.fn(),
	uploadMedia: jest.fn(),
} ) );

describe( 'Artist Manager Local Support navigation guard', () => {
	let container;
	let root;

	beforeAll( () => {
		global.IS_REACT_ACT_ENVIRONMENT = true;
	} );

	afterAll( () => {
		delete global.IS_REACT_ACT_ENVIRONMENT;
	} );

	beforeEach( async () => {
		window.ecArtistManagerConfig = {
			selectedId: 42,
			artistSiteUrl: 'https://artist.example',
			userArtists: [
				{ id: 42, name: 'First Artist' },
				{ id: 43, name: 'Second Artist' },
			],
		};
		getArtist.mockImplementation( async ( id ) => ( {
			id,
			name: 42 === id ? 'First Artist' : 'Second Artist',
			slug: 42 === id ? 'first-artist' : 'second-artist',
		} ) );
		window.confirm = jest.fn();
		container = document.createElement( 'div' );
		document.body.appendChild( container );
		root = createRoot( container );
		await act( async () => root.render( <App /> ) );
		act( () =>
			container.querySelector( '[data-tab="local-support"]' ).click()
		);
		act( () =>
			Array.from( container.querySelectorAll( 'button' ) )
				.find(
					( button ) =>
						button.textContent === 'Make availability dirty'
				)
				.click()
		);
	} );

	afterEach( () => {
		act( () => root.unmount() );
		container.remove();
		delete window.ecArtistManagerConfig;
		jest.clearAllMocks();
	} );

	test( 'blocks desktop tab changes until discard is confirmed', () => {
		window.confirm.mockReturnValue( false );
		act( () => container.querySelector( '[data-tab="info"]' ).click() );

		expect( window.confirm ).toHaveBeenCalledTimes( 1 );
		expect(
			container.querySelector( '[data-local-support-panel]' )
		).not.toBeNull();
	} );

	test( 'blocks Artist switching and then allows an explicit discard', async () => {
		const switcher = container.querySelector( '[data-selected-artist]' );
		window.confirm.mockReturnValueOnce( false ).mockReturnValueOnce( true );
		act( () => switcher.click() );
		expect( switcher.getAttribute( 'data-selected-artist' ) ).toBe( '42' );

		await act( async () => switcher.click() );
		expect( getArtist ).toHaveBeenCalledWith( 43 );
		expect(
			container
				.querySelector( '[data-selected-artist]' )
				.getAttribute( 'data-selected-artist' )
		).toBe( '43' );
	} );

	test( 'blocks active mobile accordion collapse', () => {
		window.confirm.mockReturnValue( false );
		act( () =>
			Array.from( container.querySelectorAll( 'button' ) )
				.find(
					( button ) =>
						button.textContent === 'Collapse active mobile panel'
				)
				.click()
		);

		expect( window.confirm ).toHaveBeenCalledTimes( 1 );
		expect(
			container.querySelector( '[data-local-support-panel]' )
		).not.toBeNull();
	} );

	test( 'blocks switching to another mobile accordion panel', () => {
		window.confirm.mockReturnValue( false );
		act( () =>
			container.querySelector( '[data-mobile-tab="info"]' ).click()
		);

		expect( window.confirm ).toHaveBeenCalledTimes( 1 );
		expect(
			container.querySelector( '[data-local-support-panel]' )
		).not.toBeNull();
	} );

	test( 'allows one mobile panel transition after one confirmation', () => {
		window.confirm.mockReturnValue( true );
		act( () =>
			container.querySelector( '[data-mobile-tab="info"]' ).click()
		);

		expect( window.confirm ).toHaveBeenCalledTimes( 1 );
		expect(
			container.querySelector( '[data-local-support-panel]' )
		).toBeNull();
	} );

	test( 'blocks same-window profile navigation', () => {
		window.confirm.mockReturnValue( false );
		const link = Array.from( container.querySelectorAll( 'a' ) ).find(
			( candidate ) => candidate.textContent === 'View Profile'
		);
		const click = new window.MouseEvent( 'click', {
			bubbles: true,
			cancelable: true,
		} );
		act( () => link.dispatchEvent( click ) );

		expect( click.defaultPrevented ).toBe( true );
		expect( window.confirm ).toHaveBeenCalledTimes( 1 );
	} );
} );
