/*global ajaxurl, pno_data_import_params */ ;
(function ($, window) {

	/**
	 * dataImportForm handles the import process.
	 */
	var dataImportForm = function ($form) {
		this.$form = $form;
		this.xhr = false;
		this.mapping = pno_data_import_params.mapping;
		this.position = 0;
		this.file = pno_data_import_params.file;
		this.update_existing = pno_data_import_params.update_existing;
		this.delimiter = pno_data_import_params.delimiter;
		this.security = pno_data_import_params.import_nonce;
		this.type = pno_data_import_params.type

		// Number of import successes/failures.
		this.imported = 0;
		this.failed = 0;
		this.updated = 0;
		this.skipped = 0;

		// Initial state.
		this.$form.find('.posterno-importer-progress').val(0);

		this.run_import = this.run_import.bind(this);

		// Start importing.
		this.run_import();
	};

	/**
	 * Run the import in batches until finished.
	 */
	dataImportForm.prototype.run_import = function () {
		var $this = this;

		$.ajax({
			type: 'POST',
			url: ajaxurl,
			data: {
				action: `posterno_do_ajax_${$this.type}_import`,
				position: $this.position,
				mapping: $this.mapping,
				file: $this.file,
				update_existing: $this.update_existing,
				delimiter: $this.delimiter,
				security: $this.security
			},
			dataType: 'json',
			success: function (response) {
				if (response.success) {
					$this.position = response.data.position;
					$this.imported += response.data.imported;
					$this.failed += response.data.failed;
					$this.updated += response.data.updated;
					$this.skipped += response.data.skipped;
					$this.$form.find('.posterno-importer-progress').val(response.data.percentage);

					if ('done' === response.data.position) {
						window.location = response.data.url + '&items-imported=' + parseInt($this.imported, 10) + '&items-failed=' + parseInt($this.failed, 10) + '&items-updated=' + parseInt($this.updated, 10) + '&items-skipped=' + parseInt($this.skipped, 10);
					} else {
						$this.run_import();
					}
				}
			}
		}).fail(function (response) {
			window.console.log(response);
		});
	};

	/**
	 * Function to call dataImportForm on jQuery selector.
	 */
	$.fn.pno_data_importer = function () {
		new dataImportForm(this);
		return this;
	};

	$('.posterno-importer').pno_data_importer();

})(jQuery, window);
