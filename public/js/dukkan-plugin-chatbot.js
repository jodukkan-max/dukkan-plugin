/* Dukkan AI Chatbot — storefront widget. */
(function ( $ ) {
	'use strict';

	var $root, $launcher, $panel, $messages, $input, $send;
	var history = [];
	var opened = false;
	var started = false;

	function escHtml( s ) {
		return String( s == null ? '' : s )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

	function scrollToBottom() {
		$messages.scrollTop( $messages.prop( 'scrollHeight' ) );
	}

	function appendMessage( role, html ) {
		var $msg = $( '<div class="dukkan-chatbot__msg dukkan-chatbot__msg--' + role + '"></div>' );
		$msg.append( $( '<div class="dukkan-chatbot__bubble"></div>' ).html( html ) );
		$messages.append( $msg );
		scrollToBottom();
		return $msg;
	}

	function appendText( role, text ) {
		return appendMessage( role, escHtml( text ).replace( /\n/g, '<br>' ) );
	}

	function showTyping() {
		var $msg = $( '<div class="dukkan-chatbot__msg dukkan-chatbot__msg--bot dukkan-chatbot__typing"></div>' );
		$msg.append( '<div class="dukkan-chatbot__bubble"><span></span><span></span><span></span></div>' );
		$messages.append( $msg );
		scrollToBottom();
		return $msg;
	}

	function renderProducts( products ) {
		if ( ! products || ! products.length ) {
			return;
		}

		var $wrap = $( '<div class="dukkan-chatbot__products"></div>' );

		products.forEach( function ( p ) {
			var $card = $( '<div class="dukkan-chatbot__product"></div>' );

			if ( p.image ) {
				$card.append( '<img class="dukkan-chatbot__product-img" src="' + escHtml( p.image ) + '" alt="">' );
			}
			var $info = $( '<div class="dukkan-chatbot__product-info"></div>' );
			$info.append( '<a class="dukkan-chatbot__product-name" href="' + escHtml( p.permalink ) + '">' + escHtml( p.name ) + '</a>' );
			$info.append( '<span class="dukkan-chatbot__product-price">' + ( p.price || '' ) + '</span>' );
			$card.append( $info );

			if ( p.purchasable ) {
				$card.append( '<button type="button" class="dukkan-chatbot__product-add" data-product-id="' + escHtml( p.id ) + '">Add to cart</button>' );
			}

			$wrap.append( $card );
		} );

		$messages.append( $wrap );
		scrollToBottom();
	}

	function renderHandoff() {
		var $card = $( '<div class="dukkan-chatbot__handoff"></div>' );
		$card.append( '<p class="dukkan-chatbot__handoff-title">Want to talk to a human?</p>' );
		$card.append( '<input type="email" class="dukkan-chatbot__handoff-email" placeholder="Your email (optional)">' );
		$card.append( '<button type="button" class="dukkan-chatbot__handoff-btn">Notify our team</button>' );
		$messages.append( $card );
		scrollToBottom();
	}

	function sendMessage( text ) {
		text = $.trim( text );
		if ( ! text ) {
			return;
		}

		appendText( 'user', text );
		history.push( { role: 'user', content: text } );
		$input.val( '' ).height( 'auto' );

		var $typing = showTyping();

		$.post( dukkan_chatbot.ajax_url, {
			action: 'dukkan_chatbot_send',
			nonce: dukkan_chatbot.nonce,
			message: text,
			history: history
		}, function ( res ) {
			$typing.remove();

			if ( ! res || ! res.success ) {
				var err = res && res.data && res.data.message ? res.data.message : 'Something went wrong.';
				appendText( 'bot', err );
				return;
			}

			var reply = res.data.reply || '';
			appendText( 'bot', reply );
			history.push( { role: 'assistant', content: reply } );

			renderProducts( res.data.products );

			if ( res.data.handoff ) {
				renderHandoff();
			}
		} ).fail( function () {
			$typing.remove();
			appendText( 'bot', 'Sorry, something went wrong. Please try again.' );
		} );
	}

	function showGreeting() {
		if ( started ) {
			return;
		}
		started = true;

		if ( dukkan_chatbot.greeting ) {
			appendText( 'bot', dukkan_chatbot.greeting );
		} else {
			appendText( 'bot', 'Hi! Ask me about products, orders, or anything else.' );
		}

		var $chips = $( '<div class="dukkan-chatbot__chips"></div>' );
		dukkan_chatbot.suggestions.forEach( function ( s ) {
			$chips.append( '<button type="button" class="dukkan-chatbot__chip">' + escHtml( s ) + '</button>' );
		} );
		$messages.append( $chips );
		scrollToBottom();
	}

	function openChat() {
		if ( opened ) {
			return;
		}
		opened = true;
		$panel.prop( 'hidden', false ).addClass( 'is-open' );
		$launcher.attr( 'aria-expanded', 'true' );
		showGreeting();
		setTimeout( function () { $input.trigger( 'focus' ); }, 150 );
	}

	function closeChat() {
		opened = false;
		$panel.addClass( 'is-closing' );
		setTimeout( function () {
			$panel.prop( 'hidden', true ).removeClass( 'is-open is-closing' );
		}, 150 );
		$launcher.attr( 'aria-expanded', 'false' );
	}

	function addToCart( productId ) {
		var $btn = $( '.dukkan-chatbot__product-add[data-product-id="' + productId + '"]' );
		$btn.text( 'Adding…' ).prop( 'disabled', true );

		var url = ( window.wc_add_to_cart_params && wc_add_to_cart_params.ajax_url ) ? wc_add_to_cart_params.ajax_url : dukkan_chatbot.add_to_cart_url;

		$.post( url, {
			product_id: productId,
			quantity: 1
		}, function () {
			$btn.text( 'Added ✓' ).addClass( 'is-added' );
		} ).fail( function () {
			$btn.text( 'Add to cart' ).prop( 'disabled', false );
		} );
	}

	$( document ).ready( function () {
		$root     = $( '#dukkan-chatbot' );
		$launcher = $( '#dukkan-chatbot-launcher' );
		$panel    = $( '#dukkan-chatbot-panel' );
		$messages = $( '#dukkan-chatbot-messages' );
		$input    = $( '#dukkan-chatbot-input' );
		$send     = $( '#dukkan-chatbot-send' );

		if ( ! $root.length ) {
			return;
		}

		// Inject the merchant-configured accent color as a CSS variable.
		if ( dukkan_chatbot.accent_color ) {
			$root.css( '--dukkan-accent', dukkan_chatbot.accent_color );
		}

		$launcher.on( 'click', function () {
			if ( $panel.is( ':hidden' ) ) {
				openChat();
			} else {
				closeChat();
			}
		} );

		$( '#dukkan-chatbot-close' ).on( 'click', closeChat );

		$send.on( 'click', function () {
			sendMessage( $input.val() );
		} );

		$input.on( 'keydown', function ( e ) {
			if ( e.which === 13 && ! e.shiftKey ) {
				e.preventDefault();
				sendMessage( $input.val() );
			}
		} );

		$( document ).on( 'click', '.dukkan-chatbot__chip', function () {
			sendMessage( $( this ).text() );
			$( this ).closest( '.dukkan-chatbot__chips' ).remove();
		} );

		$( document ).on( 'click', '.dukkan-chatbot__product-add', function () {
			addToCart( $( this ).data( 'product-id' ) );
		} );

		$( document ).on( 'click', '.dukkan-chatbot__handoff-btn', function () {
			var email = $( '.dukkan-chatbot__handoff-email' ).val();
			var $btn = $( this );
			$btn.text( 'Sending…' ).prop( 'disabled', true );

			$.post( dukkan_chatbot.ajax_url, {
				action: 'dukkan_chatbot_handoff',
				nonce: dukkan_chatbot.nonce,
				email: email,
				history: history
			}, function ( res ) {
				var msg = res && res.data && res.data.message ? res.data.message : 'Our team has been notified.';
				appendText( 'bot', msg );
				$( '.dukkan-chatbot__handoff' ).remove();
			} ).fail( function () {
				$btn.text( 'Notify our team' ).prop( 'disabled', false );
			} );
		} );
	} );
} )( jQuery );
