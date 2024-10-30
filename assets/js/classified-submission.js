jQuery(document).ready(function($) {
 
	$('body').on( 'click', '.classified-manager-remove-uploaded-file', function() {
		$(this).closest( '.classified-manager-uploaded-file' ).remove();
		return false;
	});

	// Check if jQuery.validate is loaded.
	if ( jQuery().validate ) {

		// Validate required fields.
		var validator = $('#submit-classified-form').validate({
			ignore       : [],
			errorClass   : 'classified-manager-validator-error',
			errorElement : 'label',
			rules: {
				classified_description: {
					required: function() {
						var content = $('#classified_description_ifr').contents().find('body').html();
						return undefined === content || ( content.indexOf('data-mce-bogus') >= 0 && '<p><br data-mce-bogus="1"></p>' == content /* for Firefox compat */ );
					},
				},
				"_classified-images": {
					required        : wpcm_i18n.images_required,
					images_required : wpcm_i18n.images_required
				},
			},
			errorPlacement: function( error, element ) {

				if ( 'textarea' == element.prop('type') ) {
					error.appendTo( element.closest('.wp-editor-wrap') );
				} else if ( 'select-multiple' == element.prop('type') ) {
					error.appendTo( element.closest('.field') );
				} else {
					error.insertAfter(element); // default error placement.
				}

			},
			showErrors: function( errorMap, errorList ) {
				$('label.classified-manager-validator-error').remove();
				this.defaultShowErrors();
			}
		});

		if ( wpcm_i18n.images_required ) {

			// Featured image required.
			$.validator.addMethod( "images_required", function( value, elem, param ) {
				return Boolean( $('[name=_classified-images]').val() );
			}, $.validator.messages.required );

		}

		// Trigger the validation for the 'wp-editor' if needed.
		$('#submit-classified-form').submit( function(e){

			var content = $('#classified_description_ifr').contents().find('body').html();

			if ( '<p><br data-mce-bogus="1"></p>' == content ) {

				$('#classified_description').val('');

				validator.form();

				e.preventDefault();
				return false;
			}

		});
	}

});