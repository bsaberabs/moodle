/**
 * This file is part of Moodle and is licensed under the
 * GNU General Public License, version 3 or later.
 *
 * You may redistribute and modify it under the terms of the GPL.
 * See the plugin root LICENSE file for complete terms.
 *
 * Floating toast notifications for the Modern Commerce design system.
 *
 * Renders the mc-toast component inside a fixed .mc-toast-region positioned at an
 * admin-configurable screen corner/edge. Two entry points:
 *
 *  1. init(options) — stand up the region, then adopt Moodle's server-rendered
 *     core notifications (#user-notifications) into design-system toasts. A
 *     MutationObserver keeps adopting notifications that core adds later via AJAX.
 *  2. show()/success()/error()/warning()/info() — fire a toast programmatically.
 *
 * Toasts rise + fade in, auto-dismiss after a delay (pausing while hovered or
 * focused), can be dismissed manually, stack, and cap to a maximum visible count.
 * Built with plain DOM (no template round-trip) so it is cheap to call from any
 * page; the structure mirrors local_moderncommerce/components/toast.mustache.
 *
 * @module     local_moderncommerce/floating_notifications
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

var VALID_POSITIONS = [
    'top-left', 'top-center', 'top-right',
    'bottom-left', 'bottom-center', 'bottom-right'
];

var VALID_TYPES = ['success', 'warning', 'danger', 'info', 'primary', 'neutral'];

// Bootstrap icon per status — colour is never the only signal.
var TYPE_ICONS = {
    success: 'bi-check-circle',
    warning: 'bi-exclamation-triangle',
    danger: 'bi-x-octagon',
    info: 'bi-info-circle',
    primary: 'bi-stars',
    neutral: 'bi-bell'
};

// Map a core/Bootstrap notification class to a design-system variant.
var CORE_CLASS_TO_TYPE = {
    'alert-success': 'success',
    'alert-warning': 'warning',
    'alert-danger': 'danger',
    'alert-error': 'danger',
    'alert-info': 'info'
};

var DEFAULTS = {
    position: 'top-right',
    autoDismissDelay: 4000,
    maxVisible: 5
};

// Module state (single region per page).
var config = null;
var region = null;
var exitDurationMs = 220; // Keep in step with --mc-transition in the SCSS.

/**
 * Normalise the init argument into a config object.
 *
 * Accepts a plain delay number (legacy signature) or an options object.
 *
 * @param {(number|Object)} options Delay in ms, or {position, autoDismissDelay, maxVisible}.
 * @return {Object}
 */
function normaliseConfig(options) {
    var resolved = {
        position: DEFAULTS.position,
        autoDismissDelay: DEFAULTS.autoDismissDelay,
        maxVisible: DEFAULTS.maxVisible
    };

    if (typeof options === 'number') {
        resolved.autoDismissDelay = options;
    } else if (options && typeof options === 'object') {
        if (VALID_POSITIONS.indexOf(options.position) !== -1) {
            resolved.position = options.position;
        }
        if (typeof options.autoDismissDelay === 'number') {
            resolved.autoDismissDelay = options.autoDismissDelay;
        }
        if (typeof options.maxVisible === 'number' && options.maxVisible > 0) {
            resolved.maxVisible = options.maxVisible;
        }
    }

    return resolved;
}

/**
 * Ensure the fixed toast region exists at the configured position.
 *
 * @return {HTMLElement}
 */
function ensureRegion() {
    if (region && region.parentNode) {
        return region;
    }

    region = document.createElement('div');
    region.className = 'mc-toast-region mc-toast-region--' + config.position;
    region.setAttribute('role', 'region');
    region.setAttribute('aria-live', 'polite');
    region.setAttribute('aria-atomic', 'false');
    region.setAttribute('aria-label', 'Notifications');
    document.body.appendChild(region);

    return region;
}

/**
 * Resolve a usable variant key from arbitrary input.
 *
 * @param {string} type
 * @return {string}
 */
function normaliseType(type) {
    return VALID_TYPES.indexOf(type) !== -1 ? type : 'info';
}

/**
 * Schedule a toast's auto-dismiss, tracking remaining time so hover/focus can
 * pause and resume the countdown rather than restart it.
 *
 * @param {HTMLElement} toast
 * @param {number} delay
 */
function armDismissTimer(toast, delay) {
    if (delay <= 0) {
        return; // Sticky toast — manual dismiss only.
    }

    var timerId = null;
    var remaining = delay;
    var startedAt = 0;

    var resume = function() {
        startedAt = Date.now();
        toast.classList.remove('mc-toast--paused');
        timerId = window.setTimeout(function() {
            dismiss(toast);
        }, remaining);
    };

    var pause = function() {
        if (timerId === null) {
            return;
        }
        window.clearTimeout(timerId);
        timerId = null;
        remaining -= (Date.now() - startedAt);
        toast.classList.add('mc-toast--paused');
    };

    toast.addEventListener('mouseenter', pause);
    toast.addEventListener('mouseleave', resume);
    toast.addEventListener('focusin', pause);
    toast.addEventListener('focusout', resume);

    resume();
}

/**
 * Append an icon element to the toast.
 *
 * @param {HTMLElement} toast
 * @param {string} iconClass
 */
function appendIcon(toast, iconClass) {
    if (!iconClass) {
        return;
    }
    var icon = document.createElement('i');
    icon.className = 'bi ' + iconClass + ' mc-toast__icon';
    icon.setAttribute('aria-hidden', 'true');
    toast.appendChild(icon);
}

/**
 * Build the body (optional title + message) for the toast.
 *
 * @param {Object} options Toast options.
 * @return {HTMLElement}
 */
function buildBody(options) {
    var body = document.createElement('div');
    body.className = 'mc-toast__body';

    if (options.title) {
        var title = document.createElement('p');
        title.className = 'mc-toast__title';
        title.textContent = options.title;
        body.appendChild(title);
    }

    var message = document.createElement('p');
    message.className = 'mc-toast__message';
    if (options.messageNodes) {
        // Adopted core notification — preserve any inline markup (e.g. links).
        options.messageNodes.forEach(function(node) {
            message.appendChild(node);
        });
    } else {
        message.textContent = options.message || '';
    }
    body.appendChild(message);

    return body;
}

/**
 * Append a manual close button to the toast.
 *
 * @param {HTMLElement} toast
 */
function appendCloseButton(toast) {
    var close = document.createElement('button');
    close.type = 'button';
    close.className = 'mc-toast__close';
    close.setAttribute('aria-label', 'Dismiss notification');
    close.innerHTML = '<i class="bi bi-x-lg" aria-hidden="true"></i>';
    close.addEventListener('click', function() {
        dismiss(toast);
    });
    toast.appendChild(close);
}

/**
 * Append the auto-dismiss countdown bar.
 *
 * @param {HTMLElement} toast
 * @param {number} delay
 */
function appendProgress(toast, delay) {
    if (delay <= 0) {
        return;
    }
    var progress = document.createElement('span');
    progress.className = 'mc-toast__progress';
    progress.style.animationDuration = delay + 'ms';
    toast.appendChild(progress);
}

/**
 * Trim the region to the configured maximum, removing the oldest toasts.
 */
function enforceMaxVisible() {
    var toasts = region.querySelectorAll('.mc-toast:not(.mc-toast--out)');
    var overflow = toasts.length - config.maxVisible;
    for (var i = 0; i < overflow; i++) {
        dismiss(toasts[i]);
    }
}

/**
 * Render a toast and add it to the region.
 *
 * @param {Object} options {message|messageNodes, title, type, delay, dismissible, icon}
 * @return {HTMLElement} The toast element.
 */
function show(options) {
    options = options || {};
    // Allow programmatic use (e.g. from a React app) before init() has run —
    // fall back to defaults so a toast still appears at the default position.
    if (!config) {
        config = normaliseConfig();
    }
    ensureRegion();

    var type = normaliseType(options.type);
    var delay = typeof options.delay === 'number' ? options.delay : config.autoDismissDelay;
    var dismissible = options.dismissible !== false; // Default on.

    var toast = document.createElement('div');
    toast.className = 'mc-toast mc-toast--' + type;
    // Errors announce assertively even inside the polite region.
    toast.setAttribute('role', type === 'danger' ? 'alert' : 'status');

    appendIcon(toast, options.icon || TYPE_ICONS[type]);
    toast.appendChild(buildBody(options));
    if (dismissible) {
        appendCloseButton(toast);
    }
    appendProgress(toast, delay);

    region.appendChild(toast);
    enforceMaxVisible();
    armDismissTimer(toast, delay);

    return toast;
}

/**
 * Dismiss a toast with its exit animation, then remove it.
 *
 * @param {HTMLElement} toast
 */
function dismiss(toast) {
    if (!toast || toast.classList.contains('mc-toast--out')) {
        return; // Already leaving.
    }
    toast.classList.add('mc-toast--out');
    window.setTimeout(function() {
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
        // Tidy the region away once empty so it never blocks the layout.
        if (region && !region.querySelector('.mc-toast')) {
            if (region.parentNode) {
                region.parentNode.removeChild(region);
            }
            region = null;
        }
    }, exitDurationMs);
}

/**
 * Convert a single core notification node into a design-system toast.
 *
 * @param {HTMLElement} alert The core .alert element inside #user-notifications.
 */
function adoptCoreNotification(alert) {
    if (!alert || alert.getAttribute('data-mc-adopted') === '1') {
        return;
    }
    alert.setAttribute('data-mc-adopted', '1');

    // Derive the variant from the core notification class.
    var type = 'info';
    Object.keys(CORE_CLASS_TO_TYPE).some(function(cls) {
        if (alert.classList.contains(cls)) {
            type = CORE_CLASS_TO_TYPE[cls];
            return true;
        }
        return false;
    });

    // Lift the message content (minus any core close control) so inline links survive.
    var messageNodes = [];
    Array.prototype.forEach.call(alert.childNodes, function(node) {
        if (node.nodeType === 1 &&
                (node.classList.contains('close') || node.classList.contains('btn-close'))) {
            return;
        }
        messageNodes.push(node.cloneNode(true));
    });

    show({type: type, messageNodes: messageNodes});

    // Remove the original so it does not also render in core's location.
    if (alert.parentNode) {
        alert.parentNode.removeChild(alert);
    }
}

/**
 * Adopt any notifications currently in #user-notifications and watch for more.
 */
function adoptCoreNotifications() {
    var container = document.getElementById('user-notifications');
    if (!container) {
        return;
    }

    Array.prototype.forEach.call(container.querySelectorAll('.alert'), adoptCoreNotification);

    if (typeof window.MutationObserver !== 'function') {
        return;
    }
    var observer = new window.MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            Array.prototype.forEach.call(mutation.addedNodes, function(node) {
                if (node.nodeType !== 1) {
                    return;
                }
                if (node.classList && node.classList.contains('alert')) {
                    adoptCoreNotification(node);
                } else if (node.querySelectorAll) {
                    Array.prototype.forEach.call(node.querySelectorAll('.alert'), adoptCoreNotification);
                }
            });
        });
    });
    observer.observe(container, {childList: true});
}

/**
 * Initialise the toast system: position the region and adopt server notifications.
 *
 * @param {(number|Object)} options Delay in ms, or {position, autoDismissDelay, maxVisible}.
 */
function init(options) {
    config = normaliseConfig(options);
    adoptCoreNotifications();
}

/**
 * Convenience wrapper that fires a typed toast.
 *
 * @param {string} type
 * @return {function(string, Object=): HTMLElement}
 */
function typed(type) {
    return function(message, options) {
        options = options || {};
        options.type = type;
        options.message = message;
        return show(options);
    };
}

export {init, show, dismiss};

export const success = typed('success');
export const error = typed('danger');
export const warning = typed('warning');
export const info = typed('info');
