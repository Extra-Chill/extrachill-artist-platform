// React's test-only act helper is supplied transitively by @wordpress/element.
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';
import { createRoot } from '@wordpress/element';
import LocalSupportTab from '../../src/blocks/artist-manager/components/LocalSupportTab';
import {
	getLocalSupportAvailability,
	getLocalSupportWorkspace,
	searchEventLocations,
	updateLocalSupportAvailability,
} from '../../src/blocks/shared/api/client';

jest.mock( '@extrachill/components', () => ( {
	ActionRow: ( { children, className = '' } ) => (
		<div className={ className }>{ children }</div>
	),
	FieldGroup: ( { children, label } ) => (
		<div>
			<span>{ label }</span>
			{ children }
		</div>
	),
	InlineStatus: ( { children, tone } ) => (
		<div role={ 'error' === tone ? 'alert' : 'status' }>{ children }</div>
	),
	Panel: ( { children } ) => <div>{ children }</div>,
} ) );

jest.mock( '../../src/blocks/shared/api/client', () => ( {
	getLocalSupportAvailability: jest.fn(),
	getLocalSupportWorkspace: jest.fn(),
	searchEventLocations: jest.fn(),
	updateLocalSupportAvailability: jest.fn(),
} ) );

describe( 'Artist Manager Local Support tab', () => {
	let container;
	let root;

	beforeAll( () => {
		global.IS_REACT_ACT_ENVIRONMENT = true;
	} );

	afterAll( () => {
		delete global.IS_REACT_ACT_ENVIRONMENT;
	} );

	beforeEach( () => {
		container = document.createElement( 'div' );
		document.body.appendChild( container );
		root = createRoot( container );
		getLocalSupportAvailability.mockResolvedValue( {
			available: false,
			scene: null,
		} );
		getLocalSupportWorkspace.mockResolvedValue( {
			artist_id: 42,
			workspace_url:
				'https://events.example/local-support/?mode=artist&artist_id=142',
		} );
		searchEventLocations.mockResolvedValue( { locations: [] } );
		updateLocalSupportAvailability.mockResolvedValue( {
			available: true,
			scene: { slug: 'charleston', name: 'Charleston' },
		} );
	} );

	afterEach( () => {
		jest.useRealTimers();
		act( () => root.unmount() );
		container.remove();
		jest.clearAllMocks();
	} );

	async function renderTab( props = {} ) {
		await act( async () => {
			root.render( <LocalSupportTab artistId={ 42 } { ...props } /> );
		} );
	}

	test( 'distinguishes availability, opportunities, and exact organizer eligibility', async () => {
		await renderTab();

		expect( container.textContent ).toContain(
			'This does not create a request for any event.'
		);
		expect( container.textContent ).toContain(
			'Taxonomy attachment alone never grants access.'
		);
	} );

	test( 'links exact Artist context to Events with accessible new-tab semantics', async () => {
		await renderTab();
		const link = container.querySelector( '.ec-am__workspace-action a' );

		expect( link.href ).toBe(
			'https://events.example/local-support/?mode=artist&artist_id=142'
		);
		expect( link.target ).toBe( '_blank' );
		expect( link.rel ).toContain( 'noopener' );
		expect( link.getAttribute( 'aria-label' ) ).toContain(
			'opens in a new tab'
		);
		expect( link.textContent ).toBe(
			'View opportunities and organizer events'
		);
	} );

	test( 'keeps availability usable when the Events doorway is unavailable', async () => {
		getLocalSupportWorkspace.mockResolvedValue( { workspace_url: '' } );
		await renderTab();

		expect(
			container.querySelector( '.ec-am__workspace-action a' )
		).toBeNull();
		expect(
			container.querySelector( '[role="status"]' ).textContent
		).toContain( 'You can still manage availability here.' );
		const checkbox = container.querySelector( 'input[type="checkbox"]' );
		act( () => checkbox.click() );
		expect( checkbox.checked ).toBe( true );
		expect(
			container.querySelector( 'input[type="search"]' )
		).not.toBeNull();
	} );

	test( 'preserves availability save behavior independently of Events', async () => {
		getLocalSupportWorkspace.mockResolvedValue( { workspace_url: '' } );
		await renderTab();
		act( () =>
			container.querySelector( 'input[type="checkbox"]' ).click()
		);
		const save = Array.from( container.querySelectorAll( 'button' ) ).find(
			( button ) => button.textContent === 'Save Local Support Settings'
		);

		await act( async () => save.click() );

		expect( updateLocalSupportAvailability ).toHaveBeenCalledWith(
			42,
			true,
			''
		);
		expect( container.textContent ).toContain(
			'Local support settings saved.'
		);
	} );

	test( 'isolates availability loading and errors from the Events doorway', async () => {
		let rejectLoad;
		getLocalSupportAvailability.mockReturnValue(
			new Promise( ( resolve, reject ) => {
				rejectLoad = reject;
			} )
		);
		await act( async () => {
			root.render( <LocalSupportTab artistId={ 42 } /> );
		} );

		expect( container.textContent ).toContain(
			'Loading local support settings'
		);
		expect(
			container.querySelector( '.ec-am__workspace-action a' )
		).not.toBeNull();

		await act( async () =>
			rejectLoad( new Error( 'Availability failed.' ) )
		);
		expect( container.textContent ).toContain( 'Availability failed.' );
		expect(
			container.querySelector( '.ec-am__workspace-action a' )
		).not.toBeNull();
	} );

	test( 'keeps availability usable while workspace discovery is contended', async () => {
		getLocalSupportWorkspace.mockReturnValue( new Promise( () => {} ) );
		await renderTab();

		expect( container.textContent ).toContain(
			'Checking the Events workspace'
		);
		const checkbox = container.querySelector( 'input[type="checkbox"]' );
		act( () => checkbox.click() );
		expect( checkbox.checked ).toBe( true );
		expect(
			container.querySelector( 'input[role="combobox"]' )
		).not.toBeNull();
	} );

	test( 'ignores out-of-order availability responses from a prior Artist', async () => {
		const requests = {};
		getLocalSupportWorkspace.mockResolvedValue( { workspace_url: '' } );
		getLocalSupportAvailability.mockImplementation(
			( id ) =>
				new Promise( ( resolve ) => {
					requests[ id ] = resolve;
				} )
		);

		await act( async () =>
			root.render( <LocalSupportTab artistId={ 42 } /> )
		);
		await act( async () =>
			root.render( <LocalSupportTab artistId={ 43 } /> )
		);
		await act( async () =>
			requests[ 43 ]( {
				available: true,
				scene: { slug: 'austin', name: 'Austin' },
			} )
		);
		await act( async () =>
			requests[ 42 ]( { available: false, scene: null } )
		);

		expect(
			container.querySelector( 'input[type="checkbox"]' ).checked
		).toBe( true );
		expect(
			container.querySelector( 'input[role="combobox"]' ).value
		).toBe( 'Austin' );
	} );

	test( 'ignores a prior Artist save response after identity changes', async () => {
		let resolveSave;
		updateLocalSupportAvailability.mockReturnValue(
			new Promise( ( resolve ) => {
				resolveSave = resolve;
			} )
		);
		await renderTab();
		act( () =>
			container.querySelector( 'input[type="checkbox"]' ).click()
		);
		const save = Array.from( container.querySelectorAll( 'button' ) ).find(
			( button ) => button.textContent === 'Save Local Support Settings'
		);
		act( () => save.click() );

		getLocalSupportAvailability.mockResolvedValue( {
			available: false,
			scene: null,
		} );
		await act( async () =>
			root.render( <LocalSupportTab artistId={ 43 } /> )
		);
		await act( async () =>
			resolveSave( {
				available: true,
				scene: { slug: 'charleston', name: 'Charleston' },
			} )
		);

		expect( updateLocalSupportAvailability ).toHaveBeenCalledWith(
			42,
			true,
			''
		);
		expect( container.textContent ).not.toContain(
			'Local support settings saved.'
		);
		expect(
			container.querySelector( 'input[type="checkbox"]' ).checked
		).toBe( false );
	} );

	test( 'supports combobox keyboard navigation, selection, and dismissal', async () => {
		jest.useFakeTimers();
		searchEventLocations.mockResolvedValue( {
			locations: [
				{ slug: 'charleston', name: 'Charleston' },
				{ slug: 'austin', name: 'Austin' },
			],
		} );
		await renderTab();
		act( () =>
			container.querySelector( 'input[type="checkbox"]' ).click()
		);
		const input = container.querySelector( 'input[role="combobox"]' );
		act( () => {
			const setValue = Object.getOwnPropertyDescriptor(
				window.HTMLInputElement.prototype,
				'value'
			).set;
			setValue.call( input, 'city' );
			input.dispatchEvent(
				new window.Event( 'input', { bubbles: true } )
			);
			jest.advanceTimersByTime( 300 );
		} );
		await act( async () => Promise.resolve() );

		expect( input.getAttribute( 'aria-expanded' ) ).toBe( 'true' );
		expect( container.querySelector( '[role="listbox"]' ) ).not.toBeNull();
		expect( container.textContent ).toContain(
			'2 Local Scene results available.'
		);
		act( () => {
			input.dispatchEvent(
				new window.KeyboardEvent( 'keydown', {
					key: 'ArrowDown',
					bubbles: true,
				} )
			);
		} );
		expect( input.getAttribute( 'aria-activedescendant' ) ).toContain(
			'option-0'
		);
		act( () => {
			input.dispatchEvent(
				new window.KeyboardEvent( 'keydown', {
					key: 'Enter',
					bubbles: true,
				} )
			);
		} );
		expect( input.value ).toBe( 'Charleston' );
		expect( input.getAttribute( 'aria-expanded' ) ).toBe( 'false' );

		act( () => {
			const setValue = Object.getOwnPropertyDescriptor(
				window.HTMLInputElement.prototype,
				'value'
			).set;
			setValue.call( input, 'city' );
			input.dispatchEvent(
				new window.Event( 'input', { bubbles: true } )
			);
			jest.advanceTimersByTime( 300 );
		} );
		await act( async () => Promise.resolve() );
		act( () => {
			input.dispatchEvent(
				new window.KeyboardEvent( 'keydown', {
					key: 'Escape',
					bubbles: true,
				} )
			);
		} );
		expect( input.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
		jest.useRealTimers();
	} );

	test( 'warns on dirty availability while the cross-site link preserves the page', async () => {
		await renderTab();
		act( () =>
			container.querySelector( 'input[type="checkbox"]' ).click()
		);

		const unload = new Event( 'beforeunload', { cancelable: true } );
		window.dispatchEvent( unload );
		expect( unload.defaultPrevented ).toBe( true );
		expect(
			container.querySelector( '.ec-am__workspace-action' )
		).not.toBeNull();
		expect( container.textContent ).toContain(
			'unsaved availability changes stay on this page'
		);
	} );

	test( 'uses keyboard-native controls and mobile-safe action markup', async () => {
		await renderTab();
		const checkbox = container.querySelector( 'input[type="checkbox"]' );
		const save = Array.from( container.querySelectorAll( 'button' ) ).find(
			( button ) => button.textContent === 'Save Local Support Settings'
		);

		expect( checkbox.id ).toBe( 'ec-am-local-support-available' );
		expect(
			container.querySelector( `label[for="${ checkbox.id }"]` )
		).not.toBeNull();
		expect( save.type ).toBe( 'button' );
		expect(
			container.querySelector( '.ec-am__workspace-action' )
		).not.toBeNull();
	} );
} );
