/**
 * Blog Lead Magnet — Analytics tracking.
 *
 * Tracks CTA views (IntersectionObserver), clicks, and gate unlocks.
 * Uses the localized `icta_analytics` object (ajax_url, nonce, post_id).
 */
( function () {
	'use strict';

	if ( typeof icta_analytics === 'undefined' ) {
		return;
	}

	var sent = new Set();

	/**
	 * Send a tracking event via fetch POST.
	 *
	 * @param {string} ctaType   One of: cta1, cta2, cta3, gate.
	 * @param {string} eventType One of: view, click, unlock.
	 */
	function trackEvent( ctaType, eventType ) {
		var key = ctaType + ':' + eventType;

		if ( sent.has( key ) ) {
			return;
		}

		sent.add( key );

		var body = new FormData();
		body.append( 'action', 'icta_track_event' );
		body.append( 'nonce', icta_analytics.nonce );
		body.append( 'post_id', icta_analytics.post_id );
		body.append( 'cta_type', ctaType );
		body.append( 'event_type', eventType );

		fetch( icta_analytics.ajax_url, {
			method: 'POST',
			credentials: 'same-origin',
			body: body,
		} );
	}

	/**
	 * Determine CTA type from an element's class list.
	 *
	 * @param {Element} el The CTA block element.
	 * @return {string|null} cta1, cta2, cta3, gate, or null.
	 */
	function getCtaType( el ) {
		if ( el.classList.contains( 'icta-block--1' ) ) {
			return 'cta1';
		}
		if ( el.classList.contains( 'icta-block--2' ) ) {
			return 'cta2';
		}
		if ( el.classList.contains( 'icta-block--3' ) ) {
			return 'cta3';
		}
		if ( el.classList.contains( 'icta-gate' ) ) {
			return 'gate';
		}
		return null;
	}

	/**
	 * Find the closest CTA block ancestor for a given element.
	 *
	 * @param {Element} el Child element (e.g. a button).
	 * @return {Element|null} The CTA block wrapper or null.
	 */
	function findCtaBlock( el ) {
		return el.closest( '.icta-block, .icta-gate' );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var blocks = document.querySelectorAll( '.icta-block, .icta-gate' );

		if ( ! blocks.length ) {
			return;
		}

		// --- View tracking via IntersectionObserver ---
		if ( 'IntersectionObserver' in window ) {
			var observer = new IntersectionObserver(
				function ( entries ) {
					entries.forEach( function ( entry ) {
						if ( ! entry.isIntersecting ) {
							return;
						}

						var ctaType = getCtaType( entry.target );

						if ( ctaType ) {
							trackEvent( ctaType, 'view' );
						}

						observer.unobserve( entry.target );
					} );
				},
				{ threshold: 0.5 }
			);

			blocks.forEach( function ( block ) {
				observer.observe( block );
			} );
		}

		// --- Click tracking on .icta-btn elements ---
		document.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.icta-btn' );

			if ( ! btn ) {
				return;
			}

			var block = findCtaBlock( btn );

			if ( ! block ) {
				return;
			}

			var ctaType = getCtaType( block );

			if ( ctaType ) {
				trackEvent( ctaType, 'click' );
			}
		} );

		// --- Gate unlock tracking via custom event ---
		document.addEventListener( 'icta:unlocked', function () {
			trackEvent( 'gate', 'unlock' );
		} );
	} );
} )();
