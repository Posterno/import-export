/*global ajaxurl, pno_schema_import_params */
;(function ( $, window ) {

	/**
	 * schemaImportForm handles the import process.
	 */
	var schemaImportForm = function( $form ) {
		this.$form           = $form;
		this.xhr             = false;
		this.mapping         = pno_schema_import_params.mapping;
		this.position        = 0;
		this.file            = pno_schema_import_params.file;
		this.update_existing = pno_schema_import_params.update_existing;
		this.delimiter       = pno_schema_import_params.delimiter;
		this.security        = pno_schema_import_params.import_nonce;

		// Number of import successes/failures.
		this.imported = 0;
		this.failed   = 0;
		this.updated  = 0;
		this.skipped  = 0;

		// Initial state.
		this.$form.find('.posterno-importer-progress').val( 0 );

		this.run_import = this.run_import.bind( this );

		// Start importing.
		this.run_import();
	};

	/**
	 * Run the import in batches until finished.
	 */
	schemaImportForm.prototype.run_import = function() {
		var $this = this;

		$.ajax( {
			type: 'POST',
			url: ajaxurl,
			data: {
				action          : 'posterno_do_ajax_schema_import',
				position        : $this.position,
				mapping         : $this.mapping,
				file            : $this.file,
				update_existing : $this.update_existing,
				delimiter       : $this.delimiter,
				security        : $this.security
			},
			dataType: 'json',
			success: function( response ) {
				if ( response.success ) {
					$this.position  = response.data.position;
					$this.imported += response.data.imported;
					$this.failed   += response.data.failed;
					$this.updated  += response.data.updated;
					$this.skipped  += response.data.skipped;
					$this.$form.find('.posterno-importer-progress').val( response.data.percentage );

					if ( 'done' === response.data.position ) {
						window.location = response.data.url + '&schemas-imported=' + parseInt( $this.imported, 10 ) + '&schemas-failed=' + parseInt( $this.failed, 10 ) + '&schemas-updated=' + parseInt( $this.updated, 10 ) + '&schemas-skipped=' + parseInt( $this.skipped, 10 );
					} else {
						$this.run_import();
					}
				}
			}
		} ).fail( function( response ) {
			window.console.log( response );
		} );
	};

	/**
	 * Function to call schemaImportForm on jQuery selector.
	 */
	$.fn.pno_schema_importer = function() {
		new schemaImportForm( this );
		return this;
	};

	$( '.posterno-importer' ).pno_schema_importer();

})( jQuery, window );
