/**
 * Open the collapsible section a link points at.
 *
 * A browser opens a closed <details> on its own only when the URL fragment
 * names something inside it. It does not when the fragment names the details
 * element itself, and that is what core/details writes its anchor onto. Checked
 * in Chrome 148: an id on the details does nothing, an id on the summary does
 * nothing, an id on a paragraph inside it opens the section.
 *
 * That is load bearing here. Eleven retired page URLs 301 to a section on Team
 * Photos or Awards, and the two governing documents have a contents list of
 * twenty-odd links to their own clauses. Without this, every one of them scrolls
 * the reader to a closed grey bar and leaves them to guess.
 *
 * @package Westshore
 */
( function () {
	'use strict';

	function openTarget() {
		var hash = window.location.hash;

		if ( ! hash || hash.length < 2 ) {
			return;
		}

		var target;

		try {
			target = document.getElementById( decodeURIComponent( hash.slice( 1 ) ) );
		} catch ( e ) {
			return;
		}

		if ( ! target ) {
			return;
		}

		// Open the target itself when it is a section, and any section it sits
		// inside, so a clause nested in another one still comes into view.
		var node = target;

		while ( node ) {
			if ( node.tagName === 'DETAILS' ) {
				node.open = true;
			}

			node = node.parentElement;
		}

		// The browser scrolled before the section opened, so the position it
		// chose is the one the closed page had.
		target.scrollIntoView();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', openTarget );
	} else {
		openTarget();
	}

	window.addEventListener( 'hashchange', openTarget );
}() );
