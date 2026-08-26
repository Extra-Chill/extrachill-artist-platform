/**
 * WordPress dependencies
 */
import { useEffect, useId, useRef, useState } from '@wordpress/element';

/**
 * External dependencies
 */
import {
	ActionRow,
	FieldGroup,
	InlineStatus,
	Panel,
} from '@extrachill/components';

/**
 * Internal dependencies
 */
import {
	getLocalSupportAvailability,
	getLocalSupportWorkspace,
	searchEventLocations,
	updateLocalSupportAvailability,
} from '../../shared/api/client';

const useDebounce = ( value, delay ) => {
	const [ debouncedValue, setDebouncedValue ] = useState( value );

	useEffect( () => {
		const handler = setTimeout( () => setDebouncedValue( value ), delay );
		return () => clearTimeout( handler );
	}, [ value, delay ] );

	return debouncedValue;
};

const LocalSupportTab = ( { artistId, onDirtyChange } ) => {
	const [ available, setAvailable ] = useState( false );
	const [ scene, setScene ] = useState( null );
	const [ savedSelection, setSavedSelection ] = useState( null );
	const [ search, setSearch ] = useState( '' );
	const [ locations, setLocations ] = useState( [] );
	const [ activeLocation, setActiveLocation ] = useState( -1 );
	const [ loading, setLoading ] = useState( false );
	const [ saving, setSaving ] = useState( false );
	const [ status, setStatus ] = useState( '' );
	const [ error, setError ] = useState( '' );
	const [ workspaceUrl, setWorkspaceUrl ] = useState( '' );
	const [ workspaceLoading, setWorkspaceLoading ] = useState( false );
	const availabilityGeneration = useRef( 0 );
	const workspaceGeneration = useRef( 0 );
	const searchGeneration = useRef( 0 );
	const listboxId = useId();
	const debouncedSearch = useDebounce( search, 300 );
	const dirty = Boolean(
		savedSelection &&
			( available !== savedSelection.available ||
				scene?.slug !== savedSelection.sceneSlug )
	);

	useEffect( () => {
		onDirtyChange?.( dirty );
	}, [ dirty, onDirtyChange ] );

	useEffect( () => {
		const generation = ++availabilityGeneration.current;
		let cancelled = false;
		setLoading( true );
		setSaving( false );
		setAvailable( false );
		setScene( null );
		setSavedSelection( null );
		setSearch( '' );
		setLocations( [] );
		setStatus( '' );
		setError( '' );

		const load = async () => {
			try {
				const data = await getLocalSupportAvailability( artistId );
				if (
					cancelled ||
					generation !== availabilityGeneration.current
				) {
					return;
				}
				setAvailable( Boolean( data?.available ) );
				setScene( data?.scene || null );
				setSearch( data?.scene?.name || '' );
				setSavedSelection( {
					available: Boolean( data?.available ),
					sceneSlug: data?.scene?.slug,
				} );
			} catch ( loadError ) {
				if (
					! cancelled &&
					generation === availabilityGeneration.current
				) {
					setError(
						loadError?.message ||
							'Could not load local support settings.'
					);
				}
			} finally {
				if (
					! cancelled &&
					generation === availabilityGeneration.current
				) {
					setLoading( false );
				}
			}
		};

		if ( artistId ) {
			load();
		}
		return () => {
			cancelled = true;
		};
	}, [ artistId ] );

	useEffect( () => {
		const generation = ++workspaceGeneration.current;
		let cancelled = false;
		setWorkspaceUrl( '' );
		setWorkspaceLoading( Boolean( artistId ) );

		const loadWorkspace = async () => {
			try {
				const data = await getLocalSupportWorkspace( artistId );
				if (
					! cancelled &&
					generation === workspaceGeneration.current
				) {
					setWorkspaceUrl( data?.workspace_url || '' );
				}
			} catch {
				if (
					! cancelled &&
					generation === workspaceGeneration.current
				) {
					setWorkspaceUrl( '' );
				}
			} finally {
				if (
					! cancelled &&
					generation === workspaceGeneration.current
				) {
					setWorkspaceLoading( false );
				}
			}
		};

		if ( artistId ) {
			loadWorkspace();
		}
		return () => {
			cancelled = true;
		};
	}, [ artistId ] );

	useEffect( () => {
		if ( ! dirty ) {
			return undefined;
		}

		const warnBeforeUnload = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};
		window.addEventListener( 'beforeunload', warnBeforeUnload );
		return () =>
			window.removeEventListener( 'beforeunload', warnBeforeUnload );
	}, [ dirty ] );

	useEffect( () => {
		const generation = ++searchGeneration.current;
		let cancelled = false;
		if (
			debouncedSearch.trim().length < 2 ||
			debouncedSearch === scene?.name
		) {
			setLocations( [] );
			setActiveLocation( -1 );
			return undefined;
		}

		const run = async () => {
			try {
				const data = await searchEventLocations(
					debouncedSearch.trim()
				);
				if ( ! cancelled && generation === searchGeneration.current ) {
					setLocations(
						Array.isArray( data?.locations ) ? data.locations : []
					);
					setActiveLocation( -1 );
				}
			} catch {
				if ( ! cancelled && generation === searchGeneration.current ) {
					setLocations( [] );
					setActiveLocation( -1 );
				}
			}
		};

		run();
		return () => {
			cancelled = true;
		};
	}, [ debouncedSearch, scene ] );

	const selectLocation = ( location ) => {
		setScene( location );
		setSearch( location.name );
		setLocations( [] );
		setActiveLocation( -1 );
	};

	const handleLocationKeyDown = ( event ) => {
		if ( 'Escape' === event.key ) {
			event.preventDefault();
			setLocations( [] );
			setActiveLocation( -1 );
			return;
		}
		if ( ! locations.length ) {
			return;
		}
		if ( 'ArrowDown' === event.key ) {
			event.preventDefault();
			setActiveLocation( ( current ) =>
				Math.min( current + 1, locations.length - 1 )
			);
		} else if ( 'ArrowUp' === event.key ) {
			event.preventDefault();
			setActiveLocation( ( current ) => Math.max( current - 1, 0 ) );
		} else if ( 'Enter' === event.key && activeLocation >= 0 ) {
			event.preventDefault();
			selectLocation( locations[ activeLocation ] );
		}
	};

	const save = async () => {
		const generation = availabilityGeneration.current;
		const savingArtistId = artistId;
		setSaving( true );
		setStatus( '' );
		setError( '' );
		try {
			const data = await updateLocalSupportAvailability(
				savingArtistId,
				available,
				scene?.slug || ''
			);
			if ( generation !== availabilityGeneration.current ) {
				return;
			}
			setAvailable( Boolean( data?.available ) );
			setScene( data?.scene || null );
			setSearch( data?.scene?.name || '' );
			setLocations( [] );
			setSavedSelection( {
				available: Boolean( data?.available ),
				sceneSlug: data?.scene?.slug,
			} );
			setStatus( 'Local support settings saved.' );
		} catch ( saveError ) {
			if ( generation === availabilityGeneration.current ) {
				setError(
					saveError?.message ||
						'Could not save local support settings.'
				);
			}
		} finally {
			if ( generation === availabilityGeneration.current ) {
				setSaving( false );
			}
		}
	};

	let resultCount = '';
	if ( locations.length ) {
		resultCount = `${ locations.length } Local Scene result${
			1 === locations.length ? '' : 's'
		} available.`;
	} else if ( search.trim().length >= 2 ) {
		resultCount = 'No Local Scene results.';
	}

	let workspaceContent;
	if ( workspaceLoading ) {
		workspaceContent = (
			<p className="ec-am__help" role="status">
				Checking the Events workspace…
			</p>
		);
	} else if ( workspaceUrl ) {
		workspaceContent = (
			<ActionRow align="start" className="ec-am__workspace-action">
				<a
					href={ workspaceUrl }
					className="button-2 button-medium"
					target="_blank"
					rel="noopener noreferrer"
					aria-label="View Local Support opportunities and organizer events on Events (opens in a new tab)"
				>
					View opportunities and organizer events
				</a>
				<span className="ec-am__help">
					Opens in a new tab so unsaved availability changes stay on
					this page.
				</span>
			</ActionRow>
		);
	} else {
		workspaceContent = (
			<p className="ec-am__help" role="status">
				The Events workspace is unavailable for this Artist. You can
				still manage availability here.
			</p>
		);
	}

	return (
		<Panel>
			{ loading && (
				<InlineStatus tone="info">
					Loading local support settings…
				</InlineStatus>
			) }
			{ error && <InlineStatus tone="error">{ error }</InlineStatus> }
			{ status && <InlineStatus tone="success">{ status }</InlineStatus> }
			<section className="ec-am__local-support-section">
				<h2>Available for Local Support</h2>
				<p>
					Turn this on when this Artist may appear as a candidate for
					opening opportunities in the selected Local Scene. This does
					not create a request for any event.
				</p>
				<label
					className="ec-am__check-row"
					htmlFor="ec-am-local-support-available"
				>
					<input
						id="ec-am-local-support-available"
						type="checkbox"
						checked={ available }
						onChange={ ( event ) =>
							setAvailable( event.target.checked )
						}
					/>
					<span>Available for Local Support</span>
				</label>
				{ available && (
					<FieldGroup label="Matching Local Scene">
						<div className="ec-am__location-picker">
							<input
								className="ec-am__location-input"
								type="search"
								value={ search }
								role="combobox"
								aria-label="Matching Local Scene"
								aria-autocomplete="list"
								aria-controls={ listboxId }
								aria-expanded={ locations.length > 0 }
								aria-activedescendant={
									activeLocation >= 0
										? `${ listboxId }-option-${ activeLocation }`
										: undefined
								}
								onKeyDown={ handleLocationKeyDown }
								onChange={ ( event ) => {
									setSearch( event.target.value );
									setScene( null );
								} }
								placeholder="Search canonical event locations"
							/>
							<span
								className="screen-reader-text"
								aria-live="polite"
							>
								{ resultCount }
							</span>
							{ locations.length > 0 && (
								<div
									id={ listboxId }
									className="ec-am__location-results"
									role="listbox"
									aria-label="Matching Local Scene results"
								>
									{ locations.map( ( location, index ) => (
										<button
											id={ `${ listboxId }-option-${ index }` }
											type="button"
											role="option"
											tabIndex={ -1 }
											aria-selected={
												index === activeLocation
											}
											key={ location.slug }
											onMouseMove={ () =>
												setActiveLocation( index )
											}
											onClick={ () =>
												selectLocation( location )
											}
										>
											{ location.hierarchy?.label ||
												location.name }
										</button>
									) ) }
								</div>
							) }
						</div>
						<p className="ec-am__help">
							Leave the scene unselected to use your
							account&apos;s canonical Local Scene when saving.
						</p>
					</FieldGroup>
				) }
				<p className="ec-am__privacy-note">
					Your contact information is never included in candidate
					results. Events may share contact information only after
					explicit, request-scoped consent.
				</p>
			</section>
			<section className="ec-am__local-support-section">
				<h2>Looking for Local Support</h2>
				<p>
					Events shows opportunities for this exact Artist. Request
					management appears only for events backed by this
					Artist&apos;s exact confirmed or completed booking while you
					still manage the Artist. Taxonomy attachment alone never
					grants access.
				</p>
				{ workspaceContent }
			</section>
			<ActionRow align="end">
				<button
					type="button"
					className="button-1 button-medium"
					onClick={ save }
					disabled={ loading || saving }
				>
					{ saving ? 'Saving…' : 'Save Local Support Settings' }
				</button>
			</ActionRow>
		</Panel>
	);
};

export default LocalSupportTab;
