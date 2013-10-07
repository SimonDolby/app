require(['jquery'], function($) {
	$(function() {

		var $toc = $('#styleguideTOC');

		if ($toc.length) {
			require(['wikia.window', 'wikia.ui.toc'], function(w, TOC) {

				var tocOffsetTop = $toc.offset().top,
					TOC_TOP_MARGIN = 10, // const for top margin of fixed TOC set in CSS
					tocInstance = new TOC($toc);

				tocInstance.init();

				/**
				 * Fix / unfix TOC position
				 */
				function setTOCPosition() {
					var scrollTop = $('body').scrollTop();

					// in Chrome $('body').scrollTop() does change when you scroll whereas $('html').scrollTop() doesn't
					// in Firefox/IE $('html').scrollTop() does change when you scroll whereas $('body').scrollTop() doesn't
					scrollTop = ( scrollTop === 0 ) ? $('html').scrollTop() : scrollTop;

					if( scrollTop >= tocOffsetTop - TOC_TOP_MARGIN ) {
						$toc.addClass('toc-fixed');
					} else {
						$toc.removeClass('toc-fixed');
					}
				}

				var throttled = $.throttle( 50, setTOCPosition);
				$(w).on('scroll', throttled);
			});
		}

		var $drawerSection = $( '#drawer' ),
			leftDrawerMsg = $.msg( 'styleguide-example-left' ),
			rightDrawerMsg = $.msg( 'styleguide-example-right' );

		require( [ 'wikia.ui.factory' ], function( uiFactory ) {
			uiFactory.init( "button" ).then( function( buttonInstance ) {
				var $drawerExamples = $drawerSection.find( '.example' );
				var drawerSampleButtons = buttonInstance.render( { type: "button", vars: {
					"type": "button",
					"classes": [ "primary", "normal", "sampleDrawerLeft" ],
					"value": leftDrawerMsg
				} } );
				drawerSampleButtons += buttonInstance.render( { type: "button", vars: {
					"type": "button",
					"classes": [ "primary", "normal", "sampleDrawerRight" ],
					"value": rightDrawerMsg
				} } );
				$drawerExamples.append( drawerSampleButtons );
			} );

			uiFactory.init( "drawer" ).then( function( drawerInstance ) {
				var drawerSamples = drawerInstance.render( {
					type: "default",
					vars: {
						side: 'left',
						content: '<h1>' + leftDrawerMsg + '</h1>',
						"class": "styleguide-example-left"
					}
				} );
				drawerSamples += drawerInstance.render( {
					type: "default",
					vars: {
						side: 'right',
						content: '<h1>' + rightDrawerMsg + '</h1>',
						"class": "styleguide-example-right"
					}
				} );
				$('body').append( drawerSamples );

				require( [ 'wikia.ui.drawer' ], function( drawer ) {
					var leftDrawer = drawer.init( 'left' );
					var rightDrawer = drawer.init( 'right' );

					$( ".sampleDrawerLeft" ).click(function() {
						if( leftDrawer.isOpen() ) {
							leftDrawer.close();
						} else {
							leftDrawer.open();
						}
					} );

					$( ".sampleDrawerRight" ).click(function() {
						if( rightDrawer.isOpen() ) {
							rightDrawer.close();
						} else {
							rightDrawer.open();
						}
					} );
				} );
			} );
		} );

		/**
		 * Show hide Style guide section
		 *
		 * @param {Object} $target - jQuery selector (show/hide link) that gives context to which element should be show / hide
		 */
		function showHideSections($target) {
			var	$section = $target.parent().next(),
				linkLabel;

			$section.toggleClass('shown');

			linkLabel = ($section.hasClass('shown') ? $.msg( 'styleguide-hide-parameters' ) : $.msg( 'styleguide-show-parameters' ));
			$target.text(linkLabel);
		}

		/** Attach events */
		$('body').on('click', '#mw-content-text section .toggleParameters', function(event) {
			event.preventDefault();
			showHideSections($(event.target));
		});

		/** Let's skip default browser action for example links/buttons (DAR-2172) */
		$('.example').on( 'click', 'a', function(event) {
			event.preventDefault();
		});
	});
});
