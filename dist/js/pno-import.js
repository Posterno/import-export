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
/******/ 	return __webpack_require__(__webpack_require__.s = 7);
/******/ })
/************************************************************************/
/******/ ({

/***/ "./resources/js/pno-import.js":
/*!************************************!*\
  !*** ./resources/js/pno-import.js ***!
  \************************************/
/*! no static exports found */
/***/ (function(module, exports) {

/*global ajaxurl, pno_data_import_params */
;

(function ($, window) {
  /**
   * dataImportForm handles the import process.
   */
  var dataImportForm = function dataImportForm($form) {
    this.$form = $form;
    this.xhr = false;
    this.mapping = pno_data_import_params.mapping;
    this.position = 0;
    this.file = pno_data_import_params.file;
    this.update_existing = pno_data_import_params.update_existing;
    this.delimiter = pno_data_import_params.delimiter;
    this.security = pno_data_import_params.import_nonce;
    this.type = pno_data_import_params.type; // Number of import successes/failures.

    this.imported = 0;
    this.failed = 0;
    this.updated = 0;
    this.skipped = 0; // Initial state.

    this.$form.find('.posterno-importer-progress').val(0);
    this.run_import = this.run_import.bind(this); // Start importing.

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
        action: "posterno_do_ajax_".concat($this.type, "_import"),
        position: $this.position,
        mapping: $this.mapping,
        file: $this.file,
        update_existing: $this.update_existing,
        delimiter: $this.delimiter,
        security: $this.security
      },
      dataType: 'json',
      success: function success(response) {
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

/***/ }),

/***/ 7:
/*!******************************************!*\
  !*** multi ./resources/js/pno-import.js ***!
  \******************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

module.exports = __webpack_require__(/*! /Users/alessandrotesoro/Local Sites/posterno/app/public/wp-content/plugins/import-export/resources/js/pno-import.js */"./resources/js/pno-import.js");


/***/ })

/******/ });
//# sourceMappingURL=pno-import.js.map