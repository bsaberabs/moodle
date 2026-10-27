// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Coupon Form Validation
 *
 * @module     local_moderncommerce/coupon_form
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import Ajax from 'core/ajax';

/**
 * Initialize the coupon form validation.
 *
 * @param {Object} config Configuration object
 * @param {string} config.sesskey Session key for AJAX requests
 * @param {string} config.checkUrl AJAX endpoint for checking coupon code
 * @param {number} config.couponId Current coupon ID (0 for new)
 * @param {string} config.codeInUseMsg Message when code is already in use
 * @param {string} config.codeAvailableMsg Message when code is available
 * @param {string} config.checkingMsg Message shown while checking the code
 */
export const init = (config) => {
    const couponId = config.couponId || 0;
    const codeInUseMsg = config.codeInUseMsg || '';
    const codeAvailableMsg = config.codeAvailableMsg || '';
    const checkingMsg = config.checkingMsg || '';

    const $codeInput = $('input[name="code"]');
    const $form = $codeInput.closest('form');

    if (!$codeInput.length) {
        return;
    }

    // Create feedback element.
    const $feedback = $('<div class="coupon-code-feedback small mt-1"></div>');
    $codeInput.after($feedback);

    let checkTimeout = null;
    let lastCheckedCode = '';
    let isCodeValid = true;

    /**
     * Check if the coupon code exists.
     * @param {string} code The code to check
     */
    const checkCode = (code) => {
        code = code.toUpperCase().trim();

        if (code === lastCheckedCode) {
            return;
        }

        if (code.length < 2) {
            $feedback.html('').removeClass('text-danger text-success');
            $codeInput.removeClass('is-invalid is-valid');
            isCodeValid = true;
            return;
        }

        lastCheckedCode = code;
        $feedback.html('<i class="bi bi-hourglass-split"></i> ' + checkingMsg)
            .removeClass('text-danger text-success').addClass('text-muted');

        Ajax.call([{
            methodname: 'local_moderncommerce_check_coupon_code',
            args: {
                code: code,
                couponid: couponId
            }
        }])[0].done(function(response) {
            if (response.success) {
                if (response.exists) {
                    $feedback.html('<i class="bi bi-x-circle"></i> ' + codeInUseMsg)
                        .removeClass('text-muted text-success')
                        .addClass('text-danger');
                    $codeInput.removeClass('is-valid').addClass('is-invalid');
                    isCodeValid = false;
                } else {
                    $feedback.html('<i class="bi bi-check-circle"></i> ' + codeAvailableMsg)
                        .removeClass('text-muted text-danger')
                        .addClass('text-success');
                    $codeInput.removeClass('is-invalid').addClass('is-valid');
                    isCodeValid = true;
                }
            }
        }).fail(function() {
            $feedback.html('').removeClass('text-danger text-success text-muted');
            $codeInput.removeClass('is-invalid is-valid');
            isCodeValid = true;
        });
    };

    // Debounced input handler.
    $codeInput.on('input', function() {
        const self = this;
        if (checkTimeout) {
            clearTimeout(checkTimeout);
        }
        checkTimeout = setTimeout(function() {
            checkCode($(self).val());
        }, 400);
    });

    // Prevent form submission if code is invalid.
    $form.on('submit', function(e) {
        if (!isCodeValid) {
            e.preventDefault();
            $codeInput.focus();
            return false;
        }
        return true;
    });

    // Check on blur as well.
    $codeInput.on('blur', function() {
        if (checkTimeout) {
            clearTimeout(checkTimeout);
        }
        checkCode($(this).val());
    });
};
