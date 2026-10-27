/**
 * This file is part of Moodle and is licensed under the
 * GNU General Public License, version 3 or later.
 *
 * You may redistribute and modify it under the terms of the GPL.
 * See the plugin root LICENSE file for complete terms.
 *
 * Bootstrap icon browser interactions.
 *
 * @module     local_moderncommerce/icon_browser
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Initialize the icon browser.
 */
export const init = () => {
    const search = document.getElementById('iconSearch');
    if (search) {
        search.addEventListener('input', (event) => {
            const term = event.target.value.toLowerCase();
            document.querySelectorAll('.mc-icon-browser__item').forEach((item) => {
                const label = item.getAttribute('data-label').toLowerCase();
                item.style.display = label.includes(term) ? 'flex' : 'none';
            });
        });
    }

    document.querySelectorAll('[data-icon]').forEach((element) => {
        element.addEventListener('click', () => {
            const iconClass = element.getAttribute('data-icon');
            navigator.clipboard.writeText(iconClass).then(() => {
                const notification = document.getElementById('copyNotification');
                if (notification) {
                    notification.style.display = 'block';
                    setTimeout(() => {
                        notification.style.display = 'none';
                    }, 2000);
                }
            }).catch(() => {
                // Clipboard permissions vary by browser; the copy action is best-effort.
            });
        });
    });
};
