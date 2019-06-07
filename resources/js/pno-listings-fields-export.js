/*global ajaxurl, pno_listings_fields_export_params */
;(function ( $, window ) {
	/**
	 * listingsFieldsExportForm handles the export process.
	 */
	var listingsFieldsExportForm = function( $form ) {
		this.$form = $form;
		this.xhr   = false;

		// Initial state.
		this.$form.find('.posterno-exporter-progress').val( 0 );

		// Methods.
		this.processStep = this.processStep.bind( this );

		// Events.
		$form.on( 'submit', { listingsFieldsExportForm: this }, this.onSubmit );
	};

	/**
	 * Handle export form submission.
	 */
	listingsFieldsExportForm.prototype.onSubmit = function( event ) {
		event.preventDefault();

		var currentDate    = new Date(),
			day            = currentDate.getDate(),
			month          = currentDate.getMonth() + 1,
			year           = currentDate.getFullYear(),
			timestamp      = currentDate.getTime(),
			filename       = 'pno-listings-fields-export-' + day + '-' + month + '-' + year + '-' + timestamp + '.csv';

		event.data.listingsFieldsExportForm.$form.addClass( 'posterno-exporter__exporting' );
		event.data.listingsFieldsExportForm.$form.find('.posterno-exporter-progress').val( 0 );
		event.data.listingsFieldsExportForm.$form.find('.posterno-exporter-button').prop( 'disabled', true );
		event.data.listingsFieldsExportForm.processStep( 1, $( this ).serialize(), '', filename );
	};

	/**
	 * Process the current export step.
	 */
	listingsFieldsExportForm.prototype.processStep = function( step, data, columns, filename ) {
		var $this = this;

		$.ajax( {
			type: 'POST',
			url: ajaxurl,
			data: {
				form     : data,
				action   : 'posterno_do_ajax_listings_fields_export',
				step     : step,
				columns  : columns,
				filename : filename,
				security : pno_listings_fields_export_params.export_nonce
			},
			dataType: 'json',
			success: function( response ) {
				if ( response.success ) {
					if ( 'done' === response.data.step ) {
						$this.$form.find('.posterno-exporter-progress').val( response.data.percentage );
						window.location = response.data.url;
						setTimeout( function() {
							$this.$form.removeClass( 'posterno-exporter__exporting' );
							$this.$form.find('.posterno-exporter-button').prop( 'disabled', false );
						}, 2000 );
					} else {
						$this.$form.find('.posterno-exporter-progress').val( response.data.percentage );
						$this.processStep( parseInt( response.data.step, 10 ), data, response.data.columns, filename );
					}
				}


			}
		} ).fail( function( response ) {
			window.console.log( response );
		} );
	};

	/**
	 * Function to call listingsFieldsExportForm on jquery selector.
	 */
	$.fn.pno_schemas_export_form = function() {
		new listingsFieldsExportForm( this );
		return this;
	};

	$( '.posterno-exporter' ).pno_schemas_export_form();

})( jQuery, window );
