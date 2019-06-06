/******/ (function(modules) { // webpackBootstrap
/******/ 	// The module cache
/******/ 	var installedModules = {};
/******/
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/
/******/ 		// Check if module is in cache
/******/ 		if(installedModules[moduleId]) {
/******/ 			return installedModules[moduleId].exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = installedModules[moduleId] = {
/******/ 			i: moduleId,
/******/ 			l: false,
/******/ 			exports: {}
/******/ 		};
/******/
/******/ 		// Execute the module function
/******/ 		modules[moduleId].call(module.exports, module, module.exports, __webpack_require__);
/******/
/******/ 		// Flag the module as loaded
/******/ 		module.l = true;
/******/
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/
/******/
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = modules;
/******/
/******/ 	// expose the module cache
/******/ 	__webpack_require__.c = installedModules;
/******/
/******/ 	// define getter function for harmony exports
/******/ 	__webpack_require__.d = function(exports, name, getter) {
/******/ 		if(!__webpack_require__.o(exports, name)) {
/******/ 			Object.defineProperty(exports, name, { enumerable: true, get: getter });
/******/ 		}
/******/ 	};
/******/
/******/ 	// define __esModule on exports
/******/ 	__webpack_require__.r = function(exports) {
/******/ 		if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 			Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 		}
/******/ 		Object.defineProperty(exports, '__esModule', { value: true });
/******/ 	};
/******/
/******/ 	// create a fake namespace object
/******/ 	// mode & 1: value is a module id, require it
/******/ 	// mode & 2: merge all properties of value into the ns
/******/ 	// mode & 4: return value when already ns object
/******/ 	// mode & 8|1: behave like require
/******/ 	__webpack_require__.t = function(value, mode) {
/******/ 		if(mode & 1) value = __webpack_require__(value);
/******/ 		if(mode & 8) return value;
/******/ 		if((mode & 4) && typeof value === 'object' && value && value.__esModule) return value;
/******/ 		var ns = Object.create(null);
/******/ 		__webpack_require__.r(ns);
/******/ 		Object.defineProperty(ns, 'default', { enumerable: true, value: value });
/******/ 		if(mode & 2 && typeof value != 'string') for(var key in value) __webpack_require__.d(ns, key, function(key) { return value[key]; }.bind(null, key));
/******/ 		return ns;
/******/ 	};
/******/
/******/ 	// getDefaultExport function for compatibility with non-harmony modules
/******/ 	__webpack_require__.n = function(module) {
/******/ 		var getter = module && module.__esModule ?
/******/ 			function getDefault() { return module['default']; } :
/******/ 			function getModuleExports() { return module; };
/******/ 		__webpack_require__.d(getter, 'a', getter);
/******/ 		return getter;
/******/ 	};
/******/
/******/ 	// Object.prototype.hasOwnProperty.call
/******/ 	__webpack_require__.o = function(object, property) { return Object.prototype.hasOwnProperty.call(object, property); };
/******/
/******/ 	// __webpack_public_path__
/******/ 	__webpack_require__.p = "/";
/******/
/******/
/******/ 	// Load entry module and return exports
/******/ 	return __webpack_require__(__webpack_require__.s = 0);
/******/ })
/************************************************************************/
/******/ ({

/***/ "./resources/js/pno-schemas-export.js":
/*!********************************************!*\
  !*** ./resources/js/pno-schemas-export.js ***!
  \********************************************/
/*! no static exports found */
/***/ (function(module, exports) {

/*global ajaxurl, wc_product_export_params */
;

(function ($, window) {
  /**
   * productExportForm handles the export process.
   */
  var productExportForm = function productExportForm($form) {
    this.$form = $form;
    this.xhr = false; // Initial state.

    this.$form.find('.woocommerce-exporter-progress').val(0); // Methods.

    this.processStep = this.processStep.bind(this); // Events.

    $form.on('submit', {
      productExportForm: this
    }, this.onSubmit);
  };
  /**
   * Handle export form submission.
   */


  productExportForm.prototype.onSubmit = function (event) {
    event.preventDefault();
    var currentDate = new Date(),
        day = currentDate.getDate(),
        month = currentDate.getMonth() + 1,
        year = currentDate.getFullYear(),
        timestamp = currentDate.getTime(),
        filename = 'wc-product-export-' + day + '-' + month + '-' + year + '-' + timestamp + '.csv';
    event.data.productExportForm.$form.addClass('woocommerce-exporter__exporting');
    event.data.productExportForm.$form.find('.woocommerce-exporter-progress').val(0);
    event.data.productExportForm.$form.find('.woocommerce-exporter-button').prop('disabled', true);
    event.data.productExportForm.processStep(1, $(this).serialize(), '', filename);
  };
  /**
   * Process the current export step.
   */


  productExportForm.prototype.processStep = function (step, data, columns, filename) {
    var $this = this,
        selected_columns = $('.woocommerce-exporter-columns').val(),
        export_meta = $('#woocommerce-exporter-meta:checked').length ? 1 : 0,
        export_types = $('.woocommerce-exporter-types').val(),
        export_category = $('.woocommerce-exporter-category').val();
    $.ajax({
      type: 'POST',
      url: ajaxurl,
      data: {
        form: data,
        action: 'woocommerce_do_ajax_product_export',
        step: step,
        columns: columns,
        selected_columns: selected_columns,
        export_meta: export_meta,
        export_types: export_types,
        export_category: export_category,
        filename: filename,
        security: wc_product_export_params.export_nonce
      },
      dataType: 'json',
      success: function success(response) {
        if (response.success) {
          if ('done' === response.data.step) {
            $this.$form.find('.woocommerce-exporter-progress').val(response.data.percentage);
            window.location = response.data.url;
            setTimeout(function () {
              $this.$form.removeClass('woocommerce-exporter__exporting');
              $this.$form.find('.woocommerce-exporter-button').prop('disabled', false);
            }, 2000);
          } else {
            $this.$form.find('.woocommerce-exporter-progress').val(response.data.percentage);
            $this.processStep(parseInt(response.data.step, 10), data, response.data.columns, filename);
          }
        }
      }
    }).fail(function (response) {
      window.console.log(response);
    });
  };
  /**
   * Function to call productExportForm on jquery selector.
   */


  $.fn.wc_product_export_form = function () {
    new productExportForm(this);
    return this;
  };

  $('.woocommerce-exporter').wc_product_export_form();
})(jQuery, window);

/***/ }),

/***/ "./resources/scss/screen.scss":
/*!************************************!*\
  !*** ./resources/scss/screen.scss ***!
  \************************************/
/*! no static exports found */
/***/ (function(module, exports) {

// removed by extract-text-webpack-plugin

/***/ }),

/***/ 0:
/*!*******************************************************************************!*\
  !*** multi ./resources/js/pno-schemas-export.js ./resources/scss/screen.scss ***!
  \*******************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

__webpack_require__(/*! /Users/alessandrotesoro/Local Sites/posterno/app/public/wp-content/plugins/import-export/resources/js/pno-schemas-export.js */"./resources/js/pno-schemas-export.js");
module.exports = __webpack_require__(/*! /Users/alessandrotesoro/Local Sites/posterno/app/public/wp-content/plugins/import-export/resources/scss/screen.scss */"./resources/scss/screen.scss");


/***/ })

/******/ });
//# sourceMappingURL=pno-schemas-export.js.map