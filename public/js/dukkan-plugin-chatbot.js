/* Dukkan AI Chatbot — storefront widget. */
(function ( $ ) {
	'use strict';

	var $root, $launcher, $panel, $messages, $input, $send;
	var $attach, $file, $mic, $preview, $previewImg, $previewRemove;
	var history = [];
	var opened = false;
	var started = false;
	var pendingImage = '';
	var prevBodyOverflow = '';

	var STORAGE_KEY = 'dukkan_chatbot_history';
	var MAX_STORED_TURNS = 60;
	// Conversation is forgotten after 30 minutes of inactivity.
	var HISTORY_TTL_MS = 30 * 60 * 1000;

	function persistHistory() {
		try {
			var saved = history.slice( -MAX_STORED_TURNS );
			localStorage.setItem( STORAGE_KEY, JSON.stringify( { ts: Date.now(), history: saved } ) );
		} catch ( e ) {}
	}

	function loadHistory() {
		try {
			var raw = localStorage.getItem( STORAGE_KEY );
			if ( raw ) {
				var parsed = JSON.parse( raw );
				var stored = null;

				if ( parsed && Array.isArray( parsed.history ) ) {
					stored = parsed.history;
					// Expire if older than 30 minutes.
					if ( typeof parsed.ts === 'number' && Date.now() - parsed.ts > HISTORY_TTL_MS ) {
						localStorage.removeItem( STORAGE_KEY );
						stored = null;
					}
				}

				if ( stored ) {
					history = stored.filter( function ( t ) {
						return t && ( t.role === 'user' || t.role === 'assistant' ) && typeof t.content === 'string';
					} );
				}
			}
		} catch ( e ) {}
	}

	function renderHistory() {
		history.forEach( function ( turn ) {
			if ( turn.role === 'user' ) {
				appendText( 'user', turn.content );
			} else if ( turn.role === 'assistant' ) {
				appendText( 'bot', turn.content );
			}
		} );
	}

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
		var html = role === 'bot' ? renderMarkdown( text ) : escHtml( text ).replace( /\n/g, '<br>' );
		return appendMessage( role, html );
	}

	function appendMarkdownBot( text ) {
		return appendMessage( 'bot', renderMarkdown( text ) );
	}

	function setStreamText( $msg, text ) {
		$msg.find( '.dukkan-chatbot__bubble' ).html( renderMarkdown( text ) );
		scrollToBottom();
	}

	// Lightweight, safe Markdown renderer (bold, italic, code, links, bullets).
	function renderMarkdown( text ) {
		var html = escHtml( text );
		html = html.replace( /\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>' );
		html = html.replace( /\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/g, '<a href="$2" target="_blank" rel="noopener nofollow">$1</a>' );
		html = html.replace( /`([^`\n]+)`/g, '<code>$1</code>' );
		html = html.replace( /(^|\n)[ \t]*[-*][ \t]+/g, '$1\u2022 ' );
		html = html.replace( /\*([^*\n]+)\*/g, '<em>$1</em>' );
		html = html.replace( /\n/g, '<br>' );
		return html;
	}

	function showTyping() {
		var $msg = $( '<div class="dukkan-chatbot__msg dukkan-chatbot__msg--bot dukkan-chatbot__typing"></div>' );
		$msg.append( '<div class="dukkan-chatbot__bubble"><span></span><span></span><span></span></div>' );
		$messages.append( $msg );
		scrollToBottom();
		return $msg;
	}

	function renderProducts( products, hasMore, $target ) {
		if ( ! products || ! products.length ) {
			return;
		}

		var $wrap = $target || $( '<div class="dukkan-chatbot__products"></div>' );
		if ( ! $target ) {
			$messages.append( $wrap );
		}

		products.forEach( function ( p ) {
			var $card = $( '<div class="dukkan-chatbot__product"></div>' );

			if ( p.image ) {
				$card.append( '<img class="dukkan-chatbot__product-img" src="' + escHtml( p.image ) + '" alt="">' );
			}
			var $info = $( '<div class="dukkan-chatbot__product-info"></div>' );
			$info.append( '<a class="dukkan-chatbot__product-name" href="' + escHtml( p.permalink ) + '">' + escHtml( p.name ) + '</a>' );
			$info.append( '<span class="dukkan-chatbot__product-price">' + ( p.price || '' ) + '</span>' );
			$card.append( $info );

			$wrap.append( $card );
		} );

		if ( hasMore ) {
			$wrap.append( '<button type="button" class="dukkan-chatbot__more">' + ( dukkan_chatbot.more_label || 'View more' ) + '</button>' );
		}

		scrollToBottom();
	}

	function loadMoreProducts( $btn ) {
		var $wrap = $btn.closest( '.dukkan-chatbot__products' );
		var $loading = $( '<span class="dukkan-chatbot__more-loading">Loading…</span>' );
		$btn.replaceWith( $loading );

		$.post( dukkan_chatbot.ajax_url, {
			action: 'dukkan_chatbot_more_products',
			nonce: dukkan_chatbot.nonce
		}, function ( res ) {
			$loading.remove();
			if ( res && res.success && res.data && res.data.products && res.data.products.length ) {
				renderProducts( res.data.products, false, $wrap );
			}
		} ).fail( function () {
			$loading.remove();
			$wrap.append( '<button type="button" class="dukkan-chatbot__more">' + ( dukkan_chatbot.more_label || 'View more' ) + '</button>' );
		} );
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
		if ( ! text && ! pendingImage ) {
			return;
		}

		// A photo with no caption gets a sensible default prompt.
		if ( ! text && pendingImage ) {
			text = 'Find products similar to this image.';
		}

		appendText( 'user', text );
		history.push( { role: 'user', content: text } );
		persistHistory();
		$input.val( '' ).height( 'auto' );

		var image = pendingImage;
		pendingImage = '';
		clearPreview();

		var $typing = showTyping();

		streamSend( text, $typing, image );
	}

	// Classic non-streaming fallback for browsers without fetch/streams.
	function fallbackSend( text, $typing, image ) {
		var payload = {
			action: 'dukkan_chatbot_send',
			nonce: dukkan_chatbot.nonce,
			message: text,
			history: history
		};
		if ( image ) {
			payload.image = image;
		}
		$.post( dukkan_chatbot.ajax_url, payload, function ( res ) {
			$typing.remove();

			if ( ! res || ! res.success ) {
				var err = res && res.data && res.data.message ? res.data.message : 'Something went wrong.';
				appendText( 'bot', err );
				return;
			}

			var reply = res.data.reply || '';
			appendText( 'bot', reply );
			history.push( { role: 'assistant', content: reply } );
			persistHistory();

			renderProducts( res.data.products, res.data.has_more );

			if ( res.data.handoff ) {
				renderHandoff();
			}
		} ).fail( function () {
			$typing.remove();
			appendText( 'bot', 'Sorry, something went wrong. Please try again.' );
		} );
	}

	// Stream the assistant reply via Server-Sent Events.
	function streamSend( text, $typing, image ) {
		if ( typeof fetch !== 'function' || ! window.ReadableStream || ! window.TextDecoder ) {
			fallbackSend( text, $typing, image );
			return;
		}

		var $stream = null;
		var reply   = '';
		var finalized = false;

		var params = new URLSearchParams();
		params.append( 'action', 'dukkan_chatbot_send_stream' );
		params.append( 'nonce', dukkan_chatbot.nonce );
		params.append( 'message', text );
		params.append( 'history', JSON.stringify( history ) );
		if ( image ) {
			params.append( 'image', image );
		}

		function handleEvent( event ) {
			var lines = event.split( '\n' );
			for ( var i = 0; i < lines.length; i++ ) {
				var line = lines[ i ];
				if ( line.indexOf( 'data:' ) !== 0 ) {
					continue;
				}
				var payload;
				try { payload = JSON.parse( line.slice( 5 ).replace( /^\s/, '' ) ); }
				catch ( e ) { continue; }

				if ( payload.t !== undefined ) {
					if ( ! $stream ) {
						$typing.remove();
						$stream = appendMarkdownBot( '' ).addClass( 'dukkan-chatbot__msg--streaming' );
					}
					reply += payload.t;
					setStreamText( $stream, reply );
				} else if ( payload.d ) {
					finalized = true;
					finishStream( payload.d.reply || reply, payload.d, $typing, $stream );
				}
			}
		}

		fetch( dukkan_chatbot.ajax_url, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: params.toString(),
			credentials: 'same-origin'
		} ).then( function ( resp ) {
			if ( ! resp.ok || ! resp.body ) {
				throw new Error( 'bad-status' );
			}
			var reader  = resp.body.getReader();
			var decoder = new TextDecoder( 'utf-8' );
			var buffer  = '';

			function pump() {
				return reader.read().then( function ( r ) {
					if ( r.done ) {
						if ( ! finalized ) {
							finishStream( reply, null, $typing, $stream );
						}
						return;
					}
					buffer += decoder.decode( r.value, { stream: true } );
					var idx;
					while ( ( idx = buffer.indexOf( '\n\n' ) ) !== -1 ) {
						var event = buffer.slice( 0, idx );
						buffer = buffer.slice( idx + 2 );
						handleEvent( event );
					}
					return pump();
				} );
			}
			return pump();
		} ).catch( function () {
			if ( finalized ) {
				return;
			}
			if ( ! reply && ! $stream ) {
				// Nothing streamed — retry via the classic endpoint.
				$typing.remove();
				fallbackSend( text, $typing, image );
			} else {
				finishStream( reply, null, $typing, $stream );
			}
		} );
	}

	function finishStream( reply, done, $typing, $stream ) {
		var finalReply = ( done && done.reply != null && done.reply !== '' ) ? done.reply : reply;

		$typing.remove();

		if ( ! finalReply ) {
			finalReply = 'Sorry, something went wrong. Please try again.';
		}

		if ( $stream ) {
			$stream.removeClass( 'dukkan-chatbot__msg--streaming' );
			setStreamText( $stream, finalReply );
		} else {
			$stream = appendMarkdownBot( finalReply );
		}

		if ( finalReply ) {
			history.push( { role: 'assistant', content: finalReply } );
			persistHistory();
		}

		if ( done ) {
			if ( done.products && done.products.length ) {
				renderProducts( done.products, done.has_more );
			}
			if ( done.handoff ) {
				renderHandoff();
			}
		}
	}

	function showGreeting() {
		if ( started ) {
			return;
		}
		started = true;

		var greeting;
		if ( dukkan_chatbot.greeting ) {
			greeting = dukkan_chatbot.greeting;
		} else {
			greeting = defaultGreeting();
		}

		appendText( 'bot', greeting );
	}

	function defaultGreeting() {
		var hour = new Date().getHours();
		var name = dukkan_chatbot.bot_name || '';

		if ( hour < 5 ) {
			return name ? 'Hi, it\u2019s ' + name + '! You\u2019re up late \u2014 how can I help you tonight?' : 'Hi! You\u2019re up late \u2014 how can I help you tonight?';
		}
		if ( hour < 12 ) {
			return name ? 'Good morning! I\u2019m ' + name + ', your shopping assistant. What can I help you find today?' : 'Good morning! What can I help you find today?';
		}
		if ( hour < 18 ) {
			return name ? 'Good afternoon! I\u2019m ' + name + '. Ask me about products, shipping, orders, or discounts!' : 'Good afternoon! Ask me about products, shipping, orders, or discounts!';
		}
		return name ? 'Good evening! I\u2019m ' + name + '. Looking for something? I\u2019m here to help.' : 'Good evening! Looking for something? I\u2019m here to help.';
	}

	function clearPreview() {
		pendingImage = '';
		$previewImg.attr( 'src', '' );
		$preview.prop( 'hidden', true );
		$file.val( '' );
	}

	function setImage( dataUrl ) {
		pendingImage = dataUrl;
		$previewImg.attr( 'src', dataUrl );
		$preview.prop( 'hidden', false );
	}

	// Map a language code to a speech-recognition locale (BCP-47).
	function normalizeLocale( loc ) {
		return String( loc || 'en-US' ).replace( /_/g, '-' );
	}

	function localeFromLang( lang ) {
		if ( ! lang ) {
			return 'en-US';
		}
		lang = String( lang ).toLowerCase();
		if ( lang === 'ar' || lang.indexOf( 'ar' ) === 0 ) {
			return 'ar-SA';
		}
		if ( lang === 'en' || lang.indexOf( 'en' ) === 0 ) {
			return 'en-US';
		}
		return normalizeLocale( lang );
	}

	// Decide the speech-recognition language based on the bot's language
	// setting and (in auto mode) the language the customer has been using.
	function speechLocale() {
		var mode = dukkan_chatbot.lang_mode || 'auto';

		if ( mode === 'fixed' ) {
			return localeFromLang( dukkan_chatbot.fixed_lang );
		}
		if ( mode === 'site' ) {
			return normalizeLocale( dukkan_chatbot.site_locale );
		}

		// Auto — match the customer: if they have typed Arabic (in the input or
		// in prior turns), recognise Arabic; otherwise use the site language.
		var typed = $input.val() || '';
		if ( /[\u0600-\u06FF]/.test( typed ) ) {
			return 'ar-SA';
		}
		for ( var i = history.length - 1; i >= 0; i-- ) {
			if ( history[ i ].role === 'user' && /[\u0600-\u06FF]/.test( history[ i ].content || '' ) ) {
				return 'ar-SA';
			}
		}
		return normalizeLocale( dukkan_chatbot.site_locale );
	}

	function startVoice() {
		var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
		if ( ! SR ) {
			appendText( 'bot', 'Voice search is not supported in this browser. Please type your question instead.' );
			return;
		}

		var rec = new SR();
		rec.lang = speechLocale();
		rec.interimResults = true;
		rec.maxAlternatives = 1;

		var finalText = '';
		$mic.addClass( 'is-listening' );

		// Stream the transcript live: interim results update the input as the
		// customer speaks, and final results append the confirmed words.
		rec.onresult = function ( e ) {
			var interim = '';
			for ( var i = e.resultIndex; i < e.results.length; i++ ) {
				var res = e.results[ i ];
				if ( res.isFinal ) {
					finalText += res[ 0 ].transcript;
				} else {
					interim += res[ 0 ].transcript;
				}
			}
			$input.val( finalText + interim ).trigger( 'focus' );
		};

		rec.onerror = function () {
			$mic.removeClass( 'is-listening' );
			appendText( 'bot', 'I could not hear that. Please try again or type your question.' );
		};

		rec.onend = function () {
			$mic.removeClass( 'is-listening' );
		};

		try {
			rec.start();
		} catch ( e ) {
			$mic.removeClass( 'is-listening' );
		}
	}

	function openChat() {
		if ( opened ) {
			return;
		}
		opened = true;
		$panel.prop( 'hidden', false ).addClass( 'is-open' );
		$launcher.attr( 'aria-expanded', 'true' );

		// Lock the page scroll so swiping inside the full-screen chat doesn't
		// rubber-band the site behind it (mobile). Store the previous overflow
		// so it can be restored exactly on close.
		prevBodyOverflow = $( 'body' ).css( 'overflow' );
		$( 'body' ).css( 'overflow', 'hidden' );

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

		// Restore the page scroll.
		$( 'body' ).css( 'overflow', prevBodyOverflow || '' );
	}

	$( document ).ready( function () {
		$root     = $( '#dukkan-chatbot' );
		$launcher = $( '#dukkan-chatbot-launcher' );
		$panel    = $( '#dukkan-chatbot-panel' );
		$messages = $( '#dukkan-chatbot-messages' );
		$input    = $( '#dukkan-chatbot-input' );
		$send     = $( '#dukkan-chatbot-send' );
		$attach   = $( '#dukkan-chatbot-attach' );
		$file     = $( '#dukkan-chatbot-file' );
		$mic      = $( '#dukkan-chatbot-mic' );
		$preview  = $( '#dukkan-chatbot-preview' );
		$previewImg   = $( '#dukkan-chatbot-preview-img' );
		$previewRemove = $( '#dukkan-chatbot-preview-remove' );

		if ( ! $root.length ) {
			return;
		}

		// Restore any previous conversation from this browser so navigating
		// between pages keeps the chat.
		loadHistory();
		if ( history.length ) {
			started = true;
			renderHistory();
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

		// Image search.
		$attach.on( 'click', function () {
			$file.trigger( 'click' );
		} );

		$file.on( 'change', function () {
			var f = this.files && this.files[ 0 ];
			if ( ! f ) {
				return;
			}
			if ( ! /^image\//.test( f.type ) ) {
				appendText( 'bot', 'Please choose an image file (JPG, PNG, WEBP or GIF).' );
				return;
			}
			if ( f.size > 3500000 ) {
				appendText( 'bot', 'That image is too large. Please choose one under 3.5MB.' );
				return;
			}
			var reader = new FileReader();
			reader.onload = function ( e ) {
				setImage( e.target.result );
			};
			reader.readAsDataURL( f );
		} );

		$previewRemove.on( 'click', clearPreview );

		// Voice search.
		$mic.on( 'click', startVoice );

		$( document ).on( 'click', '.dukkan-chatbot__more', function () {
			loadMoreProducts( $( this ) );
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
