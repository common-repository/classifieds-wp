jQuery(document).ready(function($) {

	/* Frontend Media Manager */

	var file_frame, file_frame_context;

	var wp_media_post_id = wp.media.model.settings.post.id; // Store the old id

	// check for an input containing the current post ID if one is passed
	if ( ( classified_manager_viewer_i18n.post_id_field != '' ) ) {
		// retrieve the post ID from an input
		var post_id = $('input[name='+classified_manager_viewer_i18n.post_id_field+']').val();
	} else {
		// retrieve the post ID from parameter
		var post_id = classified_manager_viewer_i18n.post_id;
	}

	post_id = parseInt( post_id );

	$('.classified-manager-media-viewer-spinner').html('');

	$(document).on( 'click', '.upload_button', function( event ) {

		event.preventDefault();

		// Each media manager field on the same form is grouped and assigned a unique group ID.
		var current_group_id               = $(this).data('group-id');
		var current_group_id_embeds        = current_group_id+'_embeds';
		var current_group_id_attach_embeds = current_group_id+'_attach_embeds';
		var media_placeholder              = $( '#'+current_group_id+' .media-placeholder' );
		var nonce                          = $('#classified_manager_mv_nonce_'+current_group_id).val();

		if ( undefined === $('input[name='+current_group_id+']').html() ) {
			return;
		}

		// Init with default WP limits.

		var mime_types  = '';
		var file_types  = '';
		var meta_type   = '';
		var file_size   = '';
		var file_limit  = 3;
		var embed_limit = 0;

		var filters = '';

		if ( classified_manager_viewer_i18n.options ) {

			var options = JSON.parse( classified_manager_viewer_i18n.options );

			if ( undefined !== options[ current_group_id ] ) {
				filters = options[ current_group_id ].filters;
			}

		}

		mime_types  = filters.mime_types;
		file_types  = filters.file_types;
		meta_type   = filters.meta_type;
		file_limit  = filters.file_limit;
		embed_limit = filters.embed_limit;
		file_size   = filters.file_size;

		// Dynamically retrieve the filter options for the current media manager.
		$.ajax({
			type : 'POST',
			url  : classified_manager_viewer_i18n.ajaxurl,
			data : {
				action      : 'classified_manager_mv_get_options',
				cm_mv_id    : current_group_id,
				cm_mv_nonce : nonce,
			},
			dataType : 'json'
		}).done( function(response) {
			// Registered the media manager instance.
		});

		// Set the wp.media post id so the uploader grabs the ID we want when initialized.
		// Skip it if the 'post_id' is empty.
		if ( post_id ) {
			wp.media.model.settings.post.id = post_id;
		}

		// Create the media frame.
		file_frame = wp.media.frames.file_frame = wp.media({
			title: $(this).data('uploader_title'),
			button: {
			  text: $(this).data('uploader_button_text'),
			},
			library: {
				post_mime_type: mime_types,
			},
			frame    : 'post',
			state    : ( file_limit !== 0 ? 'classified_manager-upload-media' : 'classified_manager-embed-media' ),
			multiple : true,  // allow multiple files to be selected
		});

		// Disable default view states.
		file_frame.states.remove('embed');
		file_frame.states.remove('gallery');
		file_frame.states.remove('gallery-edit');
		file_frame.states.remove('gallery-library');

		// Create custom states for the add and embed Views.

		if ( file_limit !== 0 ) {

			file_frame.states.add([

				new wp.media.controller.Library({
					id                  : 'classified_manager-upload-media',
					title               : 'CLASSIFIED_MANAGER-MV-ITEM ' + classified_manager_viewer_i18n.insert_media_title,
					toolbar             : 'main-insert',
					filterable          : false,
					searchable          : true,
					library             : wp.media.query( file_frame.options.library ),
					multiple            : file_frame.options.multiple ? 'reset' : false,
					editable            : true,
					displayUserSettings : false,
					displaySettings     : false,
					allowLocalEdits     : true,
				}),

			]);

		}

		if ( embed_limit != 0 ) {

			file_frame.states.add([

				// Embed states.
				new wp.media.controller.Embed({
					id      : 'classified_manager-embed-media',
					toolbar : 'main-embed',
					title   : 'CLASSIFIED_MANAGER-MV-ITEM ' + classified_manager_viewer_i18n.embed_media_title,
				}),

			]);

		}

		// Params to be passed to the WP ajax uploader request.
		wp.media.frames.file_frame.uploader.options.uploader['params']['classified_manager_media_manager'] = true;
		wp.media.frames.file_frame.uploader.options.uploader['params']['classified_manager_mv_id']         = current_group_id;
		wp.media.frames.file_frame.uploader.options.uploader['params']['classified_manager_mv_mime_types'] = mime_types;
		wp.media.frames.file_frame.uploader.options.uploader['params']['classified_manager_mv_file_limit'] = file_limit;
		wp.media.frames.file_frame.uploader.options.uploader['params']['classified_manager_mv_file_size']  = file_size;

		// When the media view is closed, process the selected attachments or embeds.
		file_frame.on( 'close', function() {

			// Retrieve all the selected files.
			var attachments      = file_frame.state().get('selection');
			var embed_urls       = '';
			var attach_embed     = true;
			var warn_file_limit  = false;
			var warn_embed_limit = false;
			var files            = 0;
			var embeds           = 0;

			// If no file were attached look for embeds.
			if ( undefined === attachments ) {
				embed_urls = file_frame.state().props.get('url');

				// Maybe clear all existing embeds if requested by user.
				if ( $('.clear-embeds').is(':checked') && ! embed_urls ) {
					embed_urls = 'clear';
				}

				attach_embed = false;

			} else {

				var attach_ids = new Array();
				var embed_ids  = new Array();
				var index = 0;

				// enqueue the attachments
				attachments.each( function( attachment ) {

					if ( attachment['id'] ) {

						if ( 'video' !== attachment['attributes']['type'] ) {

							if ( ( attach_ids.length < file_limit ) || file_limit < 0 ) {
								attach_ids.push( attachment['id'] );
							}
							files++;

						} else {

							if ( ( embed_ids.length < embed_limit ) || embed_limit < 0 ) {
								embed_ids.push( attachment['id'] );
							}
							embeds++;
						}

					}

				});

				warn_file_limit  = Boolean( files > file_limit );
				warn_embed_limit = Boolean( embeds > file_limit );
			}

			if ( attach_ids || embed_ids || embed_urls ) {

				var data = {
					action        : 'classified_manager_mv_manage_files',
					cm_mv_nonce   : nonce,
					cm_mv_id      : current_group_id,
					post_id       : post_id,
					attachments   : attach_ids,
					attach_embeds : embed_ids,
					embed_urls    : embed_urls,
					meta_type     : meta_type,
					file_limit    : file_limit,
					mime_types    : mime_types,
				};

				// dynamically create the list of selected images and display them in the form
				$.post( classified_manager_viewer_i18n.ajaxurl, data, function(response) {

					var upload_button = $( 'input[data-group-id='+current_group_id+'].upload_button' );
					var button_text   = $( upload_button ).data('upload-text');

					var has_content_attach = Boolean( $('input[name='+current_group_id+']').val() );
					var has_content_embed  = Boolean( $('input[name='+current_group_id_embeds+']').val() );

					var output = output_embeds = '';

					if ( ! response || typeof response.output === 'undefined' ) {
						console.log('Error while assigning images: ' + response );
						return;
					}

					output = response.output;

					if ( undefined === attach_ids && output.attach_ids.length ) {
						attach_id = output.attach_ids;

						// Append the new attachment HTML.
						$( '.media-attachments', media_placeholder ).append( output.files.html );

						// Append the new attachment to the input.
						var ids = $('input[name='+current_group_id+']').val();
						$('input[name='+current_group_id+']').val( ids ? ids + ',' + attach_id : attach_id );

						has_content_attach = true;
 					} else {

						// Manage attachments if user is returning from the gallery view
						// if 'attach_ids' is empty (not 'undefined'), it's because the user un-selected the attachments
						if ( undefined !== attach_ids ) {

							// check if the user selected any attachments
							if ( attach_ids.length == 0 ) {
								has_content_attach = false;
							} else {
								has_content_attach = true;
							}

							// output the attachments HTML or clear them if requested by the user
							$( '.media-attachments', media_placeholder ).html( output.files.html );

							// store the attachments on a hidden input
							$('input[name='+current_group_id+']').val( attach_ids );

							if ( undefined !== output.embeds ) {
								embed_urls    = output.embeds.url;
								output_embeds = output.embeds.html;

								if ( ! output.embeds.list ) {
									clear_embeds();
								}

							}

						}

					}

					if ( undefined !== embed_ids ) {
						// Store the embed attachments on a hidden input
						$('input[name='+current_group_id_attach_embeds+']').val( embed_ids );
					}

					if ( 0 !== embed_limit ) {

						// Manage embeds if user is returning from the embed view and added an URL
						if ( embed_urls && 'clear' !== embed_urls ) {

							var curr_embeds = $('input[name='+current_group_id_embeds+']').val();

							if ( curr_embeds ) {

								// Clear any previous embeds if requested by the user ('Clear Embeds' is checked).
								if ( ! $('.clear-embeds').is(':checked') ) {
									curr_embeds = curr_embeds.split(',');
								} else {
									curr_embeds = '';
								}

							}

							// Append or add new embeds while the embed limit is not reached.
							if ( embed_limit < 0 || ( embed_limit && curr_embeds.length < embed_limit ) ) {

								if ( curr_embeds.length > 0 ) {
									embed_urls    = $('input[name='+current_group_id_embeds+']').val() + ', ' + output.embeds.url;
									output_embeds = $( '.media-embeds', media_placeholder ).html() + '<br/>' + output.embeds.html;

								} else {
									output_embeds = output.embeds.html;
								}

								// output all the embeded URL's
								$( '.media-embeds', media_placeholder ).html( output_embeds );

								// store the embeds on a hidden input
								$('input[name='+current_group_id_embeds+']').val( embed_urls );

								has_content_embed = true;

							} else {

								alert( classified_manager_viewer_i18n.allowed_embeds_reached_text );
							}

						} else {

							// maybe clear embeds content if user requested it
							if ( ! $('input[name='+current_group_id_embeds+']').val() || 'clear' == embed_urls ) {

								clear_embeds();

								has_content_embed = false;
							}

						}

					} else {

						if ( embed_urls ) {
							alert( classified_manager_viewer_i18n.embeds_not_allowed_text );
						}
					}

					if ( warn_file_limit ) {
						alert( classified_manager_viewer_i18n.files_limit_reached_text );
					}

					if ( warn_embed_limit ) {
						alert( classified_manager_viewer_i18n.embed_limit_reached_text );
					}

					// update the content and the buttons context
					if ( has_content_attach || has_content_embed ) {

						$( '.no-media', media_placeholder ).hide();
						button_text = $( upload_button ).data('manage-text');
					} else {

						$( '.no-media', media_placeholder ).show();
					}

					$( upload_button ).val( button_text );

					$('.classified-manager-media-viewer-spinner').html('');

				}, "json" ).fail( function( error ) {
			    	console.log('Error while assigning images. Error: ' + JSON.stringify( error ) );
			  	});

			}

			// restore the main post ID
			wp.media.model.settings.post.id = wp_media_post_id;

			file_frame = '';

			$(file_frame_context).remove();

			delete_transients();

		});

		// Pre-select attachments and remove sidebar items.
		file_frame.on( 'open', function() {

			file_frame_context = document.activeElement;

			// clear
			$('.classified_manager-media-settings').remove();

			$('.media-frame-menu').closest('.media-modal').addClass('classified-manager-media-viewer');

			// mark custom menu items by searching for the CLASSIFIED_MANAGER-MV-ITEM flag
			$('.media-frame-menu .media-menu-item:contains("CLASSIFIED_MANAGER-MV-ITEM '+classified_manager_viewer_i18n.insert_media_title+'")').addClass('classified_manager-mv-item');
			$('.media-frame-menu .media-menu-item:contains("CLASSIFIED_MANAGER-MV-ITEM '+classified_manager_viewer_i18n.embed_media_title+'")').addClass('classified_manager-mv-item');

			// remove any default non CLASSIFIED_MANAGER-MV-ITEM menu items
			$('.media-frame-menu .media-menu-item:not(.classified_manager-mv-item)').remove();

			// pre-select attached files when the media manager is displayed
			var selection = file_frame.state().get('selection');

			if ( file_limit !== 0 && undefined != $('input[name='+current_group_id+']').val() ) {

				var ids       = $('input[name='+current_group_id+']').val().trim();
				var embed_ids = $('input[name='+current_group_id_attach_embeds+']').val().trim();

				if ( embed_ids ) {
					ids = ids + ',' + embed_ids;
				}

				if ( '' !== ids ) {

					ids = ids.split(',');

					ids.forEach( function(id) {
					  	attachment = wp.media.attachment(id);
					  	attachment.fetch();
				  		selection.add( attachment ? [ attachment ] : [] );
				   	});

				}

			}

			$('.classified-manager-media-viewer-spinner').html( '<img src="' + classified_manager_viewer_i18n.spinner + '" alt="" width="16" height="16" />' );
		});

		// Open the media manager modal.
		file_frame.open();

		// Display file upload/embed restrictions on each tab change.
		$(document).on( 'click', '.media-menu-item', function() {

			// hide screen readers text
			$('.screen-reader-text').hide();

			$('.media-frame-content select.attachment-filters').css( { 'max-width' :'calc(75% - 12px)' } );

			$('a').css( 'text-decoration', 'none' );

			display_custom_notes();
		});

		// Display file upload/embed restrictions on mouse move for the first time the media UI is open.
		$(document).on( 'mousemove', '.media-modal-content', function(event) {
		//	display_custom_notes();
		})

		// Clear any transients when UI is closed.
		function delete_transients() {

			$.ajax({
				type  : 'POST',
				url   : classified_manager_viewer_i18n.ajaxurl,
				data  : {
					action: 'classified_manager_delete_media_viewer_transients',
				},
				dataType : 'json'
			}).done( function(response) {
				// do nothing
			} );

		}

		// Display file upload/embed restrictions on each tab change.
		function display_custom_notes() {

			// remove the upload size default tag
			$('.max-upload-size').remove();

			// remove embed settings (title, etc)
			$('.embed-link-settings .setting').remove();

			// restore menu item titles by removing the CLASSIFIED_MANAGER-MV-ITEM flag
			$( '.media-menu-item.classified_manager-mv-item, .media-frame-title h1').each( function() {
				var text = $(this).text().replace( 'CLASSIFIED_MANAGER-MV-ITEM', '' ).trim();
				$(this).text( text );
			});

			// Custom notes to display on the media view.

			$('.classified_manager-media-settings').remove();

			// *** FILE SIZE ***

			// convert bytes to specific size unit
			if ( file_size > 0 ) {

				var sizes = new Array( 'KB', 'MB', 'GB' );

				var file_size_unit = file_size;

				for ( u = -1; file_size_unit >= 1024 && u < sizes.length - 1; u++ ) {
					file_size_unit /= 1024;
				}

				if ( u < 0 ) {
					file_size_unit = 0;
					u = 0;
				} else {
					file_size_unit = parseInt( file_size_unit );
				}

				$('.post-upload-ui').append( '<p class="classified_manager-media-settings classified_manager-mv-file-size-restrictions classified_manager-mv-file-size">' + classified_manager_viewer_i18n.file_size_text + ': ' + file_size_unit + sizes[u] + '</p>');
			}

			// *** EMBEDS ***

			$('.embed-link-settings').append('<div class="classified_manager-media-settings classified_manager-mv-embed-settings" style="margin-top: 20px;"><label class="clear-embeds" style="margin-top: 10px;"><input class="clear-embeds" type="checkbox"> ' + classified_manager_viewer_i18n.clear_embeds_text + '</input></label></div>');

			if ( embed_limit > 0 ) {
				$('.classified_manager-mv-embed-settings').append( '<p class="classified_manager-mv-embed-restrictions classified_manager-mv-embed-limit">' + classified_manager_viewer_i18n.embed_limit_text + ': ' + embed_limit + '</p>');
			}

			// *** ATTACHMENTS ***

			if ( file_limit > 0 ) {
				$('.post-upload-ui').append( '<p class="classified_manager-media-settings classified_manager-mv-file-restrictions classified_manager-mv-file-limit">' + classified_manager_viewer_i18n.files_limit_text + ': ' + file_limit + '</p>');
			}
			if ( file_types ) {
				$('.post-upload-ui').append( '<p class="classified_manager-media-settings classified_manager-mv-file-restrictions classified_manager-mv-file-types">' + classified_manager_viewer_i18n.files_type_text + ': ' + file_types + '</p>');
			}

		}

		$('.media-menu-item:first').trigger('click');

		function clear_embeds() {
			$('.media-embeds', media_placeholder).html('');
			$('input[name='+current_group_id_embeds+']').val('');
			$('input[name='+current_group_id_attach_embeds+']').val('');
		}

	});


	// restore the main ID when the add media button is pressed
	$('a.media-button').on('click', function() {
		wp.media.model.settings.post.id = wp_media_post_id;
	});


});
