/* Dukkan > Checkout Fields tab. */
jQuery( function ( $ ) {
	var $table = $( '[data-cf-table]' );
	if ( ! $table.length ) {
		return;
	}
	var i18n = window.dukkanCheckoutFields || {};
	var next = $table.find( '[data-cf-field]' ).length;

	$table.sortable( {
		items: '> tbody.dukkan-cf__field',
		handle: '.dukkan-cf__drag',
		axis: 'y',
		placeholder: 'dukkan-cf__placeholder',
		helper: function ( e, el ) {
			el.children().children().each( function () {
				$( this ).width( $( this ).width() );
			} );
			return el;
		}
	} );

	$table.on( 'click', '[data-cf-toggle]', function ( e ) {
		e.preventDefault();
		var $f = $( this ).closest( '[data-cf-field]' );
		$f.find( '[data-cf-more]' ).toggleClass( 'is-open' );
		$f.find( '[data-cf-row]' ).toggleClass( 'is-open' );
	} );

	$table.on( 'change', '[data-cf-enabled]', function () {
		$( this ).closest( '[data-cf-row]' ).toggleClass( 'is-disabled', ! this.checked );
	} );

	$table.on( 'change', '[data-cf-type]', function () {
		var choice = this.value === 'select' || this.value === 'radio';
		$( this ).closest( '[data-cf-field]' ).find( '[data-cf-options]' ).toggleClass( 'is-hidden', ! choice );
	} );

	$table.on( 'click', '[data-cf-delete]', function () {
		if ( window.confirm( i18n.confirmDelete ) ) {
			$( this ).closest( '[data-cf-field]' ).remove();
		}
	} );

	$( '[data-cf-add]' ).on( 'click', function () {
		var html = $( '#tmpl-dukkan-cf-row' ).html().replace( /__i__/g, 'n' + ( next++ ) );
		var $f = $( $.parseHTML( $.trim( html ) ) ).filter( 'tbody' );
		$table.append( $f );
		$f.find( '[data-cf-name]' ).val( $( this ).data( 'prefix' ) ).trigger( 'focus' );
	} );

	$( '[data-cf-reset]' ).on( 'click', function ( e ) {
		if ( ! window.confirm( i18n.confirmReset ) ) {
			e.preventDefault();
		}
	} );

	$( '.dukkan-cf__form' ).on( 'submit', function ( e ) {
		if ( e.originalEvent && e.originalEvent.submitter && e.originalEvent.submitter.name === 'reset' ) {
			return;
		}
		var seen = {};
		var ok = true;
		$table.find( '[data-cf-name]' ).each( function () {
			var v = $.trim( this.value );
			if ( ! /^[a-z0-9_]+$/.test( v ) ) {
				window.alert( i18n.badName + ' (' + v + ')' );
				ok = false;
				return false;
			}
			if ( seen[ v ] ) {
				window.alert( i18n.dupName + ' (' + v + ')' );
				ok = false;
				return false;
			}
			seen[ v ] = true;
		} );
		if ( ! ok ) {
			e.preventDefault();
			return;
		}
		// Post rows in their on-screen order.
		$table.find( '[data-cf-field]' ).each( function ( idx ) {
			$( this ).find( '[name^="fields["]' ).each( function () {
				this.name = this.name.replace( /^fields\[[^\]]+\]/, 'fields[' + idx + ']' );
			} );
		} );
	} );
} );
