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

jQuery(document).ready(function ($) {
  /**
   * Import screen JS
   */
  var PNO_Import = {
    init: function init() {
      this.submit();
    },
    submit: function submit() {
      var self = this;
      $('.pno-import-form').ajaxForm({
        beforeSubmit: self.before_submit,
        success: self.success,
        complete: self.complete,
        dataType: 'json',
        error: self.error
      });
    },
    before_submit: function before_submit(arr, $form, options) {
      $form.find('.notice-wrap').empty();
      $form.find('.spinner').show();
      $form.find('progress').val(0).show();
      $form.find('.button-primary').prop("disabled", true);
    },
    success: function success(responseText, statusText, xhr, $form) {},
    complete: function complete(xhr) {
      var response = jQuery.parseJSON(xhr.responseText);

      if (response.success) {
        var $form = $('.pno-import-form').parent();
        $form.find('.pno-import-file-wrap,.notice-wrap').remove();
        console.log(response.data);
        return;
        $form.find('.pno-import-options').slideDown(); // Show column mapping

        var select = $form.find('select.pno-import-csv-column');
        var row = select.parents('tr').first();
        var options = '';
        var columns = response.data.columns.sort(function (a, b) {
          if (a < b) return -1;
          if (a > b) return 1;
          return 0;
        });
        $.each(columns, function (key, value) {
          options += '<option value="' + value + '">' + value + '</option>';
        });
        select.append(options);
        select.on('change', function () {
          var $key = $(this).val();

          if (!$key) {
            $(this).parent().next().html('');
          } else {
            if (false != response.data.first_row[$key]) {
              $(this).parent().next().html(response.data.first_row[$key]);
            } else {
              $(this).parent().next().html('');
            }
          }
        });
        $.each(select, function () {
          $(this).val($(this).attr('data-field')).change();
        });
        $(document.body).on('click', '.pno-import-proceed', function (e) {
          e.preventDefault();
          $form.append('<div class="notice-wrap"><span class="spinner is-active"></span><div class="pno-progress"><div></div></div></div>');
          response.data.mapping = $form.serialize();
          PNO_Import.process_step(1, response.data, self);
        });
      } else {
        PNO_Import.error(xhr);
      }
    },
    error: function error(xhr) {
      // Something went wrong. This will display error on form
      var response = jQuery.parseJSON(xhr.responseText);
      var import_form = $('.pno-import-form');
      var notice_wrap = import_form.find('.notice-wrap');
      var spinner = import_form.find('.spinner');
      var progress = import_form.find('progress');
      var button = import_form.find('.button-primary');
      import_form.find('.button-disabled').removeClass('button-disabled');

      if (response.data.error) {
        notice_wrap.html('<div style="margin:10px 0;" class="carbon-wp-notice notice-error is-alt"><p style="margin:0">' + response.data.error + '</p></div>');
      } else {
        notice_wrap.remove();
      }

      progress.hide().val(0);
      spinner.hide();
      button.removeAttr('disabled');
    },
    process_step: function process_step(step, import_data, self) {
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
          step: step
        },
        dataType: "json",
        success: function success(response) {
          if ('done' == response.data.step || response.data.error) {
            // We need to get the actual in progress form, not all forms on the page
            var import_form = $('.pno-import-form');
            var notice_wrap = import_form.find('.notice-wrap');
            import_form.find('.button-primary').removeAttr('disabled');

            if (response.data.error) {
              notice_wrap.html('<div style="margin:10px 0;" class="carbon-wp-notice notice-error is-alt"><p style="margin:0">' + response.data.error + '</p></div>');
            } else {
              import_form.find('.pno-import-options').hide();
              $('html, body').animate({
                scrollTop: import_form.parent().offset().top
              }, 500);
              notice_wrap.html('<div style="margin:10px 0;" class="carbon-wp-notice notice-success is-alt"><p style="margin:0">' + response.data.message + '</p></div>');
            }
          } else {
            $('progress').val(response.data.percentage);
            PNO_Import.process_step(parseInt(response.data.step), import_data, self);
          }
        }
      }).fail(function (response) {
        if (window.console && window.console.log) {
          console.log(response);
        }
      });
    }
  };
  PNO_Import.init();
});

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