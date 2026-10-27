// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Component gallery interactions.
 *
 * @module     local_moderncommerce/components_demo
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as Toast from 'local_moderncommerce/floating_notifications';

const triggerId = 'mc-components-toast-trigger';
const statusId = 'mc-components-toast-status';
const modalId = 'mc-components-modal';
const modalTriggerId = 'mc-components-modal-trigger';
const drawerId = 'mc-components-drawer';
const drawerTriggerId = 'mc-components-drawer-trigger';

/**
 * Update the visible demo status.
 *
 * @param {string} message
 * @param {boolean} isError
 */
const setStatus = (message, isError) => {
    const status = document.getElementById(statusId);
    if (!status) {
        return;
    }
    status.textContent = message;
    status.classList.toggle('text-danger', !!isError);
    status.classList.toggle('text-muted', !isError);
};

/**
 * Attach the live toast trigger.
 */
const bindToastTrigger = () => {
    const trigger = document.getElementById(triggerId);
    if (!trigger || trigger.getAttribute('data-mc-toast-bound') === '1') {
        return;
    }

    trigger.setAttribute('data-mc-toast-bound', '1');
    setStatus('Ready.', false);

    trigger.addEventListener('click', function() {
        if (!Toast || typeof Toast.success !== 'function') {
            setStatus('Toast API is not available.', true);
            return;
        }
        Toast.success('Order #1042 marked paid.', {
            title: 'Payment successful'
        });
        setStatus('Toast triggered.', false);
    });
};

/**
 * Set the demo modal visibility.
 *
 * @param {boolean} open
 */
const setModalOpen = (open) => {
    const modal = document.getElementById(modalId);
    const trigger = document.getElementById(modalTriggerId);
    if (!modal) {
        return;
    }

    modal.hidden = !open;
    document.body.classList.toggle('mc-modal-open', open);

    if (trigger) {
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    if (open) {
        const close = modal.querySelector('[data-mc-demo-modal-close]');
        if (close && typeof close.focus === 'function') {
            close.focus();
        }
        return;
    }

    if (trigger && typeof trigger.focus === 'function') {
        trigger.focus();
    }
};

/**
 * Get focusable elements inside the demo modal.
 *
 * @param {HTMLElement} modal
 * @return {HTMLElement[]}
 */
const getModalFocusable = (modal) => {
    return Array.prototype.filter.call(
        modal.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), ' +
            'textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'),
        function(element) {
            return element.offsetParent !== null || element === document.activeElement;
        }
    );
};

/**
 * Set the demo drawer visibility.
 *
 * @param {boolean} open
 */
const setDrawerOpen = (open) => {
    const drawer = document.getElementById(drawerId);
    const trigger = document.getElementById(drawerTriggerId);
    if (!drawer) {
        return;
    }

    drawer.hidden = !open;
    document.body.classList.toggle('mc-drawer-open', open);

    if (trigger) {
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    if (open) {
        const close = drawer.querySelector('[data-mc-demo-drawer-close]');
        if (close && typeof close.focus === 'function') {
            close.focus();
        }
        return;
    }

    if (trigger && typeof trigger.focus === 'function') {
        trigger.focus();
    }
};

/**
 * Attach the live modal trigger.
 */
const bindModalTrigger = () => {
    const trigger = document.getElementById(modalTriggerId);
    const modal = document.getElementById(modalId);
    if (!trigger || !modal || trigger.getAttribute('data-mc-modal-bound') === '1') {
        return;
    }

    trigger.setAttribute('data-mc-modal-bound', '1');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.addEventListener('click', function() {
        setModalOpen(true);
    });

    Array.prototype.forEach.call(modal.querySelectorAll('[data-mc-demo-modal-close]'), function(close) {
        close.addEventListener('click', function() {
            setModalOpen(false);
        });
    });

    modal.addEventListener('click', function(event) {
        if (event.target === modal) {
            setModalOpen(false);
        }
    });

    document.addEventListener('keydown', function(event) {
        if (modal.hidden) {
            return;
        }
        if (event.key === 'Escape') {
            setModalOpen(false);
            return;
        }
        if (event.key !== 'Tab') {
            return;
        }

        const focusable = getModalFocusable(modal);
        if (focusable.length === 0) {
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
};

/**
 * Attach the live drawer trigger.
 */
const bindDrawerTrigger = () => {
    const trigger = document.getElementById(drawerTriggerId);
    const drawer = document.getElementById(drawerId);
    if (!trigger || !drawer || trigger.getAttribute('data-mc-drawer-bound') === '1') {
        return;
    }

    trigger.setAttribute('data-mc-drawer-bound', '1');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.addEventListener('click', function() {
        setDrawerOpen(true);
    });

    Array.prototype.forEach.call(drawer.querySelectorAll('[data-mc-demo-drawer-close]'), function(close) {
        close.addEventListener('click', function() {
            setDrawerOpen(false);
        });
    });

    document.addEventListener('keydown', function(event) {
        if (drawer.hidden) {
            return;
        }
        if (event.key === 'Escape') {
            setDrawerOpen(false);
            return;
        }
        if (event.key !== 'Tab') {
            return;
        }

        const focusable = getModalFocusable(drawer);
        if (focusable.length === 0) {
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
};

/**
 * Initialise component gallery interactions.
 */
export const init = () => {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            bindToastTrigger();
            bindModalTrigger();
            bindDrawerTrigger();
        });
        return;
    }
    bindToastTrigger();
    bindModalTrigger();
    bindDrawerTrigger();
};
