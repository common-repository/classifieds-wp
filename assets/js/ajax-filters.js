jQuery( document ).ready( function ( $ ) {

	var xhr = []; 

	$( '.classified_listings' ).on( 'update_results', function ( event, page, append, loading_previous ) {
		var data         = '';
		var target       = $( this );
		var form         = target.find( '.classified_filters' );
		var showing      = target.find( '.showing_classifieds' );
		var results      = target.find( '.classified_listings' );
		var per_page     = target.data( 'per_page' );
		var per_row      = target.data( 'per_row' );
		var orderby      = target.data( 'orderby' );
		var order        = target.data( 'order' );
		var featured     = target.data( 'featured' );
		var unavailable       = target.data( 'unavailable' );
		var index        = $( 'div.classified_listings' ).index(this);

		if ( index < 0 ) {
			return;
		}

		if ( xhr[index] ) {
			xhr[index].abort();
		}

		if ( ! append ) {
			$( results ).addClass( 'loading' );
			$( 'li.classified_listing, li.no_classified_listings_found', results ).css( 'visibility', 'hidden' );

			// Not appending. If page > 1, we should show a load previous button so the user can get to earlier-page listings if needed
			if ( page > 1 && true != target.data( 'show_pagination' ) ) {
				$( results ).before( '<a class="load_more_classifieds load_previous" href="#"><strong>' + classified_manager_ajax_filters.i18n_load_prev_listings + '</strong></a>' );
			} else {
				target.find( '.load_previous' ).remove();
			}

			target.find( '.load_more_classifieds' ).data( 'page', page );
		}

		if ( true == target.data( 'show_filters' ) ) {

			var filter_classified_type = [];

			$( ':input[name="filter_classified_type[]"]:checked, :input[name="filter_classified_type[]"][type="hidden"], :input[name="filter_classified_type"]', form ).each( function () {
				filter_classified_type.push( $( this ).val() );
			} );

			var categories = form.find( ':input[name^="search_categories"]' ).map( function () {
			return $( this ).val();
			} ).get();
			var keywords   = '';
			var location   = '';
			var $keywords  = form.find( ':input[name="search_keywords"]' );
			var $location  = form.find( ':input[name="search_location"]' );

			// Workaround placeholder scripts
			if ( $keywords.val() !== $keywords.attr( 'placeholder' ) ) {
				keywords = $keywords.val();
			}

			if ( $location.val() !== $location.attr( 'placeholder' ) ) {
				location = $location.val();
			}

			data = {
				lang: classified_manager_ajax_filters.lang,
				search_keywords: keywords,
				search_location: location,
				search_categories: categories,
				filter_classified_type: filter_classified_type,
				per_page: per_page,
				per_row: per_row,
				orderby: orderby,
				order: order,
				page: page,
				featured: featured,
				unavailable: unavailable,
				show_pagination: target.data( 'show_pagination' ),
				form_data: form.serialize()
			};

		} else {

			var categories = target.data( 'categories' );
			var keywords   = target.data( 'keywords' );
			var location   = target.data( 'location' );

			if ( categories ) {
				categories = categories.split( ',' );
			}

			data = {
				lang: classified_manager_ajax_filters.lang,
				search_categories: categories,
				search_keywords: keywords,
				search_location: location,
				per_page: per_page,
				per_row: per_row,
				orderby: orderby,
				order: order,
				page: page,
				featured: featured,
				unavailable: unavailable,
				show_pagination: target.data( 'show_pagination' )
			};

		}

		xhr[index] = $.ajax( {
			type: 'POST',
			url: classified_manager_ajax_filters.ajax_url.toString().replace( "%%endpoint%%", "get_listings" ),
			data: data,
			success: function ( result ) {
				if ( result ) {
					try {
						if ( result.showing ) {
							$( showing ).show().html( '<span>' + result.showing + '</span>' + result.showing_links );
						} else {
							$( showing ).hide();
						}

						if ( result.showing_all ) {
							$( showing ).addClass( 'wp-classified-manager-showing-all' );
						} else {
							$( showing ).removeClass( 'wp-classified-manager-showing-all' );
						}

						if ( result.html ) {
							if ( append && loading_previous ) {
								$( results ).prepend( result.html );
							} else if ( append ) {
								$( results ).append( result.html );
							} else {
								$( results ).html( result.html );
							}
						}

						if ( true == target.data( 'show_pagination' ) ) {
							target.find('.classified-manager-pagination').remove();

							if ( result.pagination ) {
								target.append( result.pagination );
							}
						} else {
							if ( ! result.found_classifieds || result.max_num_pages <= page ) {
								$( '.load_more_classifieds:not(.load_previous)', target ).hide();
							} else if ( ! loading_previous ) {
								$( '.load_more_classifieds', target ).show();
							}
							$( '.load_more_classifieds', target ).removeClass( 'loading' );
							$( 'li.classified_listing', results ).css( 'visibility', 'visible' );
						}

						$( results ).removeClass( 'loading' );

						target.triggerHandler( 'updated_results', result );

					} catch ( err ) {
						if ( window.console ) {
							console.log( err );
						}
					}
				}
			},
			error: function ( jqXHR, textStatus, error ) {
				if ( window.console && 'abort' !== textStatus ) {
					console.log( textStatus + ': ' + error );
				}
			},
			statusCode: {
				404: function() {
					if ( window.console ) {
						console.log( "Error 404: Ajax Endpoint cannot be reached. Go to Settings > Permalinks and save to resolve." );
					}
				}
			}
		} );
	} );

	$( '#search_keywords, #search_location, .classified_types :input, #search_categories, .classified-manager-filter' ).change( function() {
		var target   = $( this ).closest( 'div.classified_listings' );
		target.triggerHandler( 'update_results', [ 1, false ] );
		classified_manager_store_state( target, 1 );
	} )

	.on( "keyup", function(e) {
		if ( e.which === 13 ) {
			$( this ).trigger( 'change' );
		}
	} );

	$( '.classified_filters' ).on( 'click', '.reset', function () {
		var target = $( this ).closest( 'div.classified_listings' );
		var form = $( this ).closest( 'form' );

		form.find( ':input[name="search_keywords"], :input[name="search_location"], .classified-manager-filter' ).not(':input[type="hidden"]').val( '' ).trigger( 'chosen:updated' );
		form.find( ':input[name^="search_categories"]' ).not(':input[type="hidden"]').val( 0 ).trigger( 'chosen:updated' );
		$( ':input[name="filter_classified_type[]"]', form ).not(':input[type="hidden"]').attr( 'checked', 'checked' );

		target.triggerHandler( 'reset' );
		target.triggerHandler( 'update_results', [ 1, false ] );
		classified_manager_store_state( target, 1 );

		return false;
	} );

	$( document.body ).on( 'click', '.load_more_classifieds', function() {
		var target           = $( this ).closest( 'div.classified_listings' );
		var page             = parseInt( $( this ).data( 'page' ) || 1 );
		var loading_previous = false;

		$(this).addClass( 'loading' );

		if ( $(this).is('.load_previous') ) {
			page             = page - 1;
			loading_previous = true;
			if ( page === 1 ) {
				$(this).remove();
			} else {
				$( this ).data( 'page', page );
			}
		} else {
			page = page + 1;
			$( this ).data( 'page', page );
			classified_manager_store_state( target, page );
		}

		target.triggerHandler( 'update_results', [ page, true, loading_previous ] );
		return false;
	} );

	$( 'div.classified_listings' ).on( 'click', '.classified-manager-pagination a', function() {
		var target = $( this ).closest( 'div.classified_listings' );
		var page   = $( this ).data( 'page' );

		classified_manager_store_state( target, page );

		target.triggerHandler( 'update_results', [ page, false ] );

		$( "body, html" ).animate({
            scrollTop: target.offset().top
        }, 600 );

		return false;
	} );

	if ( $.isFunction( $.fn.chosen ) ) {
		if ( classified_manager_ajax_filters.is_rtl == 1 ) {
			$( 'select[name^="search_categories"]' ).addClass( 'chosen-rtl' );
		}
		$( 'select[name^="search_categories"]' ).chosen({ search_contains: true });
	}

	if ( window.history && window.history.pushState ) {
		$supports_html5_history = true;
	} else {
		$supports_html5_history = false;
	}

	var location = document.location.href.split('#')[0];

	function classified_manager_store_state( target, page ) {
		if ( $supports_html5_history ) {
			var form  = target.find( '.classified_filters' );
			var data  = $( form ).serialize();
			var index = $( 'div.classified_listings' ).index( target );
			window.history.replaceState( { id: 'classified_manager_state', page: page, data: data, index: index }, '', location + '#s=1' );
		}
	}

	// Inital classified and form population
	$(window).on( "load", function( event ) {
		$( '.classified_filters' ).each( function() {
			var target      = $( this ).closest( 'div.classified_listings' );
			var form        = target.find( '.classified_filters' );
			var inital_page = 1;
			var index       = $( 'div.classified_listings' ).index( target );

	   		if ( window.history.state && window.location.hash ) {
	   			var state = window.history.state;
	   			if ( state.id && 'classified_manager_state' === state.id && index == state.index ) {
					inital_page = state.page;
					form.deserialize( state.data );
					form.find( ':input[name^="search_categories"]' ).not(':input[type="hidden"]').trigger( 'chosen:updated' );
				}
	   		}

			target.triggerHandler( 'update_results', [ inital_page, false ] );
	   	});
	});
} );
