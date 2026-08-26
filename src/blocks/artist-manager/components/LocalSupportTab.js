/**
 * WordPress dependencies
 */
import { useEffect, useState } from '@wordpress/element';

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

const LocalSupportTab = ( { artistId, workspaceUrl = '' } ) => {
	const [ available, setAvailable ] = useState( false );
	const [ scene, setScene ] = useState( null );
	const [ savedSelection, setSavedSelection ] = useState( null );
	const [ search, setSearch ] = useState( '' );
	const [ locations, setLocations ] = useState( [] );
	const [ loading, setLoading ] = useState( false );
	const [ saving, setSaving ] = useState( false );
	const [ status, setStatus ] = useState( '' );
	const [ error, setError ] = useState( '' );
	const debouncedSearch = useDebounce( search, 300 );
	const dirty = Boolean(
		savedSelection &&
			( available !== savedSelection.available ||
				scene?.slug !== savedSelection.sceneSlug )
	);

	useEffect( () => {
		const load = async () => {
			setLoading( true );
			setSavedSelection( null );
			try {
				const data = await getLocalSupportAvailability( artistId );
				setAvailable( Boolean( data?.available ) );
				setScene( data?.scene || null );
				setSearch( data?.scene?.name || '' );
				setSavedSelection( {
					available: Boolean( data?.available ),
					sceneSlug: data?.scene?.slug,
				} );
				setError( '' );
			} catch ( err ) {
				setError(
					err?.message || 'Could not load local support settings.'
				);
			} finally {
				setLoading( false );
			}
		};

		if ( artistId ) {
			load();
		}
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
		const run = async () => {
			if (
				debouncedSearch.trim().length < 2 ||
				debouncedSearch === scene?.name
			) {
				setLocations( [] );
				return;
			}
			try {
				const data = await searchEventLocations(
					debouncedSearch.trim()
				);
				setLocations(
					Array.isArray( data?.locations ) ? data.locations : []
				);
			} catch {
				setLocations( [] );
			}
		};

		run();
	}, [ debouncedSearch, scene ] );

	const save = async () => {
		setSaving( true );
		setStatus( '' );
		setError( '' );
		try {
			const data = await updateLocalSupportAvailability(
				artistId,
				available,
				scene?.slug || ''
			);
			setAvailable( Boolean( data?.available ) );
			setScene( data?.scene || null );
			setSearch( data?.scene?.name || '' );
			setLocations( [] );
			setSavedSelection( {
				available: Boolean( data?.available ),
				sceneSlug: data?.scene?.slug,
			} );
			setStatus( 'Local support settings saved.' );
		} catch ( err ) {
			setError(
				err?.message || 'Could not save local support settings.'
			);
		} finally {
			setSaving( false );
		}
	};

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
								aria-label="Matching Local Scene"
								onChange={ ( event ) => {
									setSearch( event.target.value );
									setScene( null );
								} }
								placeholder="Search canonical event locations"
							/>
							{ locations.length > 0 && (
								<div className="ec-am__location-results">
									{ locations.map( ( location ) => (
										<button
											type="button"
											key={ location.slug }
											onClick={ () => {
												setScene( location );
												setSearch( location.name );
												setLocations( [] );
											} }
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
					This means an event has an event-scoped request managed
					privately on Events. Request management is limited to
					eligible organizer events backed by exact canonical booking
					evidence; an Artist taxonomy attachment alone never grants
					access.
				</p>
				<p>
					You can also view open opportunities and express interest as
					this Artist. Events may show an empty organizer state when
					no eligible event exists.
				</p>
				{ workspaceUrl ? (
					<ActionRow
						align="start"
						className="ec-am__workspace-action"
					>
						<a
							href={ workspaceUrl }
							className="button-2 button-medium"
							target="_blank"
							rel="noopener noreferrer"
							aria-label="View Local Support opportunities on Events (opens in a new tab)"
						>
							View opportunities on Events
						</a>
						<span className="ec-am__help">
							Opens in a new tab so unsaved availability changes
							stay on this page.
						</span>
					</ActionRow>
				) : (
					<p className="ec-am__help" role="status">
						The Events workspace is unavailable for this Artist. You
						can still manage availability here.
					</p>
				) }
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
