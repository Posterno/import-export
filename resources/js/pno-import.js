jQuery(document).ready(function ($) {

	/**
	 * Import screen JS
	 */
	var PNO_Import = {

		init : function() {
			this.submit();
		},

		submit : function() {

			var self = this;

			$('.pno-import-form').ajaxForm({
				beforeSubmit: self.before_submit,
				success: self.success,
				complete: self.complete,
				dataType: 'json',
				error: self.error
			});

		},

		before_submit : function( arr, $form, options ) {
			$form.find('.notice-wrap').empty();
			$form.find('.spinner').show();
			$form.find('progress').val(0).show();
			$form.find('.button-primary').prop("disabled", true);
		},

		success: function( responseText, statusText, xhr, $form ) {},

		complete: function( xhr ) {

			var response = jQuery.parseJSON( xhr.responseText );

			if( response.success ) {

				var $form = $('.pno-import-form').parent();

				$form.find('.pno-import-file-wrap,.notice-wrap').remove();

				console.log( 'hehehe yup' );
				return;

				$form.find('.pno-import-options').slideDown();

				// Show column mapping
				var select  = $form.find('select.pno-import-csv-column');
				var row     = select.parents( 'tr' ).first();
				var options = '';

				var columns = response.data.columns.sort(function(a,b) {
					if( a < b ) return -1;
					if( a > b ) return 1;
					return 0;
				});

				$.each( columns, function( key, value ) {
					options += '<option value="' + value + '">' + value + '</option>';
				});

				select.append( options );

				select.on('change', function() {
					var $key = $(this).val();

					if( ! $key ) {

						$(this).parent().next().html( '' );

					} else {

						if( false != response.data.first_row[$key] ) {
							$(this).parent().next().html( response.data.first_row[$key] );
						} else {
							$(this).parent().next().html( '' );
						}

					}

				});

				$.each( select, function() {
					$( this ).val( $(this).attr( 'data-field' ) ).change();
				});

				$(document.body).on('click', '.pno-import-proceed', function(e) {

					e.preventDefault();

					$form.append( '<div class="notice-wrap"><span class="spinner is-active"></span><div class="pno-progress"><div></div></div></div>' );

					response.data.mapping = $form.serialize();

					PNO_Import.process_step( 1, response.data, self );
				});

			} else {

				PNO_Import.error( xhr );

			}

		},

		error : function( xhr ) {

			// Something went wrong. This will display error on form
			var response    = jQuery.parseJSON( xhr.responseText );
			var import_form = $('.pno-import-form');
			var notice_wrap = import_form.find('.notice-wrap');
			var spinner = import_form.find('.spinner');
			var progress = import_form.find('progress');
			var button = import_form.find('.button-primary');

			import_form.find('.button-disabled').removeClass('button-disabled');

			if ( response.data.error ) {
				notice_wrap.html('<div style="margin:10px 0;" class="carbon-wp-notice notice-error is-alt"><p style="margin:0">' + response.data.error + '</p></div>');
			} else {
				notice_wrap.remove();
			}

			progress.hide().val(0)
			spinner.hide();
			button.removeAttr('disabled')

		},

		process_step : function( step, import_data, self ) {

			$.ajax({
				type: 'POST',
				url: ajaxurl,
				data: {
					form: import_data.form,
					nonce: import_data.nonce,
					class: import_data.class,
					upload: import_data.upload,
					mapping: import_data.mapping,
					action: 'pno_do_ajax_import',
					step: step,
				},
				dataType: "json",
				success: function( response ) {

					if( 'done' == response.data.step || response.data.error ) {

						// We need to get the actual in progress form, not all forms on the page
						var import_form  = $('.pno-import-form');
						var notice_wrap  = import_form.find('.notice-wrap');

						import_form.find('.button-primary').removeAttr('disabled');

						if ( response.data.error ) {

							notice_wrap.html('<div style="margin:10px 0;" class="carbon-wp-notice notice-error is-alt"><p style="margin:0">' + response.data.error + '</p></div>');

						} else {

							import_form.find( '.pno-import-options' ).hide();
							$('html, body').animate({
								scrollTop: import_form.parent().offset().top
							}, 500 );

							notice_wrap.html('<div style="margin:10px 0;" class="carbon-wp-notice notice-success is-alt"><p style="margin:0">' + response.data.message + '</p></div>');

						}

					} else {
						$('progress').val( response.data.percentage );
						PNO_Import.process_step( parseInt( response.data.step ), import_data, self );
					}

				}
			}).fail(function (response) {
				if ( window.console && window.console.log ) {
					console.log( response );
				}
			});

		}

	};
	PNO_Import.init();

});
