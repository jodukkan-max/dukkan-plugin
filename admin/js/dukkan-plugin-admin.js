(function( $ ) {
	'use strict';

	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */

	window.showToast = function (message, type = 'success') {
		let icon = '✔';

		if(type === 'error') icon = '✖';
		if(type === 'warning') icon = '!';

		$('#wpldp-toast').remove();

		let toast = $(`
			<div id="wpldp-toast" class="wpldp-toast wpldp-${type}">
				<span class="wpldp-toast-icon">${icon}</span>
				<span class="wpldp-toast-text">${message}</span>
			</div>
		`);

		$('body').append(toast);

		setTimeout(()=>toast.addClass('show'),10);

		setTimeout(()=>{
			toast.removeClass('show');
			setTimeout(()=>toast.remove(),300);
		},2500);
	};

	window.showLoader = function (message = 'Processing...') {
		// $('#wpldp-loader').remove();
		// let loader = $(`
		// 	<div id="wpldp-loader">
		// 		<div class="wpldp-loader-spinner"></div>
		// 		<span class="wpldp-loader-text">${message}</span>
		// 	</div>
		// `);

		// $('body .wpldp-wrapper').append(loader);
	};

	window.hideLoader = function () {
		//$('#wpldp-loader').remove();
	};

	$(document).ready(function(){

		$(".tab").click(function(){

			var tab = $(this).data("tab");

			$(".tab").removeClass("active");
			$(this).addClass("active");

			$(".wpldp-tab-panel").removeClass("active");

			$("#"+tab).addClass("active");

		});

		$('.dukkan-menu-item').on('click',function(){

			let tab = $(this).data('tab');

			$('.dukkan-menu-item').removeClass('active');
			$(this).addClass('active');

			$('.dukkan-tab').removeClass('active');
			$('#dukkan-tab-'+tab).addClass('active');

		});

	});

})( jQuery );
