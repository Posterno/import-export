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
/******/ 	return __webpack_require__(__webpack_require__.s = 5);
/******/ })
/************************************************************************/
/******/ ({

/***/ "./resources/js/pno-taxonomy-export.js":
/*!*********************************************!*\
  !*** ./resources/js/pno-taxonomy-export.js ***!
  \*********************************************/
/*! no static exports found */
/***/ (function(module, exports) {

/*global ajaxurl, pno_taxonomy_export_params */
;

(function ($, window) {
  /**
   * taxonomyExportForm handles the export process.
   */
  var taxonomyExportForm = function taxonomyExportForm($form) {
    this.$form = $form;
    this.xhr = false; // Initial state.

    this.$form.find('.posterno-exporter-progress').val(0); // Methods.

    this.processStep = this.processStep.bind(this); // Events.

    $form.on('submit', {
      taxonomyExportForm: this
    }, this.onSubmit);
  };
  /**
   * Handle export form submission.
   */


  taxonomyExportForm.prototype.onSubmit = function (event) {
    event.preventDefault();
    var currentDate = new Date(),
        day = currentDate.getDate(),
        month = currentDate.getMonth() + 1,
        year = currentDate.getFullYear(),
        timestamp = currentDate.getTime(),
        taxonomy_to_export = $('#taxonomy_to_export').val();
    filename = 'pno-taxonomy-export-' + taxonomy_to_export + '-' + day + '-' + month + '-' + year + '-' + timestamp + '.csv';
    event.data.taxonomyExportForm.$form.addClass('posterno-exporter__exporting');
    event.data.taxonomyExportForm.$form.find('.posterno-exporter-progress').val(0);
    event.data.taxonomyExportForm.$form.find('.posterno-exporter-button').prop('disabled', true);
    event.data.taxonomyExportForm.processStep(1, $(this).serialize(), '', filename);
  };
  /**
   * Process the current export step.
   */


  taxonomyExportForm.prototype.processStep = function (step, data, columns, filename) {
    var $this = this;
    taxonomy_to_export = $('#taxonomy_to_export').val();
    $.ajax({
      type: 'POST',
      url: ajaxurl,
      data: {
        form: data,
        action: 'posterno_do_ajax_taxonomy_export',
        step: step,
        columns: columns,
        filename: filename,
        security: pno_taxonomy_export_params.export_nonce,
        taxonomy_to_export: taxonomy_to_export
      },
      dataType: 'json',
      success: function success(response) {
        if (response.success) {
          if ('done' === response.data.step) {
            $this.$form.find('.posterno-exporter-progress').val(response.data.percentage);
            window.location = response.data.url;
            setTimeout(function () {
              $this.$form.removeClass('posterno-exporter__exporting');
              $this.$form.find('.posterno-exporter-button').prop('disabled', false);
            }, 2000);
          } else {
            $this.$form.find('.posterno-exporter-progress').val(response.data.percentage);
            $this.processStep(parseInt(response.data.step, 10), data, response.data.columns, filename);
          }
        }
      }
    }).fail(function (response) {
      window.console.log(response);
    });
  };
  /**
   * Function to call taxonomyExportForm on jquery selector.
   */


  $.fn.pno_taxonomy_export_form = function () {
    new taxonomyExportForm(this);
    return this;
  };

  $('.posterno-exporter').pno_taxonomy_export_form();
})(jQuery, window);

/***/ }),

/***/ 5:
/*!***************************************************!*\
  !*** multi ./resources/js/pno-taxonomy-export.js ***!
  \***************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

module.exports = __webpack_require__(/*! /Users/alessandrotesoro/Local Sites/posterno/app/public/wp-content/plugins/import-export/resources/js/pno-taxonomy-export.js */"./resources/js/pno-taxonomy-export.js");


/***/ })

/******/ });
//# sourceMappingURL=pno-taxonomy-export.js.map