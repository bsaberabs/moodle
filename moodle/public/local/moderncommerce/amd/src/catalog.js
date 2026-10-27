/**
 * This file is part of Moodle and is licensed under the
 * GNU General Public License, version 3 or later.
 *
 * You may redistribute and modify it under the terms of the GPL.
 * See the plugin root LICENSE file for complete terms.
 *
 * Course Catalog Filter & Pagination Logic
 *
 * @module     local_moderncommerce/catalog
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import Ajax from 'core/ajax';
import Notification from 'core/notification';

/**
 * Initialize the course catalog.
 *
 * @param {Object} config Configuration object
 * @param {string} config.currencySymbol Currency symbol for price display
 * @param {string} config.currencyPosition Currency symbol position
 * @param {number} config.currencyDecimals Decimal places for price display
 * @param {string} config.currencyThousand Thousands separator for price display
 * @param {string} config.currencyDecimal Decimal separator for price display
 * @param {number} config.maxPrice Maximum price for filter slider
 * @param {string} config.pageLabel Label for "Page"
 * @param {string} config.ofLabel Label for "of"
 * @param {string} config.sesskey Session key for AJAX requests
 * @param {boolean} config.isLoggedIn Whether the user is logged in
 * @param {string} config.loginUrl URL to login page
 * @param {string} config.registerUrl URL to registration page
 * @param {string} config.loginRequiredTitle Title for login modal
 * @param {string} config.loginRequiredMessage Message for login modal
 * @param {string} config.loginBtn Login button text
 * @param {string} config.registerBtn Register button text
 * @param {string} config.closeLabel Close button aria label
 * @param {string} config.failedToAddToCart Generic add-to-cart failure message
 */
export const init = (config) => {
    const currencySymbol = config.currencySymbol || '';
    const currencyPosition = config.currencyPosition || 'before';
    const currencyDecimals = Number.isFinite(config.currencyDecimals) ? config.currencyDecimals : 2;
    const currencyThousand = config.currencyThousand || ',';
    const currencyDecimal = config.currencyDecimal || '.';
    const maxPrice = config.maxPrice || 300;
    const pageLabel = config.pageLabel || '';
    const ofLabel = config.ofLabel || '';
    const isLoggedIn = config.isLoggedIn || false;
    const loginUrl = config.loginUrl || '/login/index.php';
    const registerUrl = config.registerUrl || '/login/signup.php';
    const loginRequiredTitle = config.loginRequiredTitle || '';
    const loginRequiredMessage = config.loginRequiredMessage || '';
    const loginBtn = config.loginBtn || '';
    const registerBtn = config.registerBtn || '';
    const closeLabel = config.closeLabel || '';
    const failedToAddToCart = config.failedToAddToCart || '';

    /**
     * Format a numeric price for the catalog slider using the store currency settings.
     *
     * @param {number|string} value Price value
     * @returns {string} Formatted price
     */
    const formatPriceValue = (value) => {
        const numericValue = parseFloat(value) || 0;
        const parts = numericValue.toFixed(currencyDecimals).split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, currencyThousand);
        const formattedNumber = parts.length > 1 ? parts[0] + currencyDecimal + parts[1] : parts[0];

        if (currencyPosition === 'after') {
            return formattedNumber + currencySymbol;
        }

        return currencySymbol + formattedNumber;
    };

    const $section = $('.mc-catalog-section');
    const $grid = $('#mc-catalog-grid');
    let $cards = $grid.find('.mc-course-card');

    const $searchInput = $('#mc-catalog-search');
    const $sortSelect = $('#mc-catalog-sort');
    const $perpageSelect = $('#mc-catalog-perpage');
    const $priceRange = $('#mc-price-range');
    const $priceValue = $('#mc-price-value');
    const $filteredCount = $('#mc-filtered-count');
    const $resultsCount = $('#mc-results-count');
    const $noResults = $('#mc-no-results');
    const $pagination = $('#mc-pagination');
    const $paginationPages = $('#mc-pagination-pages');
    const $prevBtn = $('#mc-page-prev');
    const $nextBtn = $('#mc-page-next');
    const $pageInfo = $('#mc-page-info');

    // Pagination state.
    let currentPage = 1;
    let perPage = parseInt($section.data('perpage'), 10) || 12;
    let filteredCards = [];
    let isAnimating = false;

    // Set initial perpage dropdown value.
    $perpageSelect.val(perPage);

    // Sidebar toggle
    const $sidebar = $('#mc-catalog-sidebar');
    const $backdrop = $('#mc-catalog-backdrop');
    const $filterToggle = $('#mc-filter-toggle');
    const $sidebarClose = $('#mc-sidebar-close');
    const $showResults = $('#mc-show-results');

    /**
     * Show login required modal for guests.
     */
    const showLoginModal = () => {
        // Check if modal already exists.
        let $modal = $('#mc-login-modal');
        if (!$modal.length) {
            // Create the modal HTML.
            const modalHtml = `
                <div class="modal fade" id="mc-login-modal" tabindex="-1" role="dialog"
                     aria-labelledby="mc-login-modal-title" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="mc-login-modal-title">
                                    <i class="bi bi-lock me-2"></i>${loginRequiredTitle}
                                </h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="${closeLabel}">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body text-center py-4">
                                <div class="mb-3">
                                    <i class="bi bi-cart3 fs-1 text-primary"></i>
                                </div>
                                <p class="mb-0">${loginRequiredMessage}</p>
                            </div>
                            <div class="modal-footer justify-content-center">
                                <a href="${loginUrl}" class="mc-button btn-mc-primary px-4" data-mc-button="primary">
                                    <i class="bi bi-box-arrow-in-right me-2"></i>${loginBtn}
                                </a>
                                <a href="${registerUrl}" class="mc-button mc-btn-soft px-4" data-mc-button="soft">
                                    <i class="bi bi-person-plus me-2"></i>${registerBtn}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $('body').append(modalHtml);
            $modal = $('#mc-login-modal');
        }

        // Show the modal using Bootstrap.
        if (typeof $modal.modal === 'function') {
            $modal.modal('show');
        } else {
            // Fallback for Bootstrap 5.
            const bsModal = new window.bootstrap.Modal($modal[0]);
            bsModal.show();
        }
    };

    /**
     * Toggle sidebar visibility.
     * @param {boolean} open Whether to open the sidebar
     */
    const toggleSidebar = (open) => {
        if (open) {
            $sidebar.addClass('open');
            $backdrop.addClass('open');
            $('body').addClass('mc-sidebar-open');
        } else {
            $sidebar.removeClass('open');
            $backdrop.removeClass('open');
            $('body').removeClass('mc-sidebar-open');
        }
    };

    $filterToggle.on('click', () => toggleSidebar(true));
    $sidebarClose.on('click', () => toggleSidebar(false));
    $backdrop.on('click', () => toggleSidebar(false));
    $showResults.on('click', () => toggleSidebar(false));

    // Filter section toggle
    $('.mc-filter-section-header').on('click', function() {
        const $filterSection = $(this).closest('.mc-filter-section');
        $filterSection.toggleClass('collapsed');
    });

    // Price range update
    $priceRange.on('input', function() {
        $priceValue.text(formatPriceValue(this.value));
        applyFilters();
    });

    // Per-page selector change
    $perpageSelect.on('change', function() {
        perPage = parseInt($(this).val(), 10) || 12;
        currentPage = 1;
        renderPageWithAnimation();
    });

    /**
     * Get current filter values.
     * @returns {Object} Filter values
     */
    const getFilters = () => {
        const searchVal = $searchInput.val() || '';
        const filters = {
            search: searchVal.toLowerCase().trim(),
            coursetypes: [],
            categories: [],
            rating: null,
            levels: [],
            maxPrice: parseInt($priceRange.val(), 10) || maxPrice
        };

        $('input[name="coursetype"]:checked').each(function() {
            filters.coursetypes.push($(this).val());
        });

        $('input[name="category"]:checked').each(function() {
            filters.categories.push($(this).val());
        });

        const $ratingChecked = $('input[name="rating"]:checked');
        if ($ratingChecked.length) {
            filters.rating = parseFloat($ratingChecked.val());
        }

        $('input[name="level"]:checked').each(function() {
            filters.levels.push($(this).val());
        });

        return filters;
    };

    /**
     * Check if card matches search filter.
     * @param {jQuery} $card Card element
     * @param {string} search Search term
     * @returns {boolean} True if matches
     */
    const matchesSearch = ($card, search) => {
        if (!search) {
            return true;
        }
        const title = ($card.data('title') || '').toString().toLowerCase();
        return title.indexOf(search) !== -1;
    };

    /**
     * Check if card matches array filter.
     * @param {jQuery} $card Card element
     * @param {string} dataKey Data attribute key
     * @param {Array} filterValues Array of allowed values
     * @returns {boolean} True if matches
     */
    const matchesArrayFilter = ($card, dataKey, filterValues) => {
        if (filterValues.length === 0) {
            return true;
        }
        const value = ($card.data(dataKey) || '').toString();
        return filterValues.indexOf(value) !== -1;
    };

    /**
     * Apply filters and rebuild filtered list.
     */
    const applyFilters = () => {
        const filters = getFilters();
        filteredCards = [];

        $cards.each(function() {
            const $card = $(this);

            // Search filter
            if (!matchesSearch($card, filters.search)) {
                return;
            }

            // Course type filter
            if (!matchesArrayFilter($card, 'coursetype', filters.coursetypes)) {
                return;
            }

            // Category filter
            if (!matchesArrayFilter($card, 'category', filters.categories)) {
                return;
            }

            // Rating filter
            if (filters.rating !== null) {
                const rating = parseFloat($card.data('rating')) || 0;
                if (rating < filters.rating) {
                    return;
                }
            }

            // Level filter
            if (filters.levels.length > 0) {
                const level = ($card.data('level') || '').toString();
                if (level === '' || filters.levels.indexOf(level) === -1) {
                    return;
                }
            }

            // Price filter
            const price = parseFloat($card.data('price')) || 0;
            if (price > filters.maxPrice) {
                return;
            }

            filteredCards.push($card);
        });

        // Update counts
        $filteredCount.text(filteredCards.length);
        $resultsCount.text('(' + filteredCards.length + ')');

        // Reset to page 1 when filters change.
        currentPage = 1;
        renderPage(false);
    };

    /**
     * Render current page of results with optional animation.
     * @param {boolean} animate Whether to animate the transition
     */
    const renderPage = (animate) => {
        const totalItems = filteredCards.length;
        const totalPages = Math.ceil(totalItems / perPage) || 1;

        // Clamp current page.
        if (currentPage < 1) {
            currentPage = 1;
        }
        if (currentPage > totalPages) {
            currentPage = totalPages;
        }

        const startIdx = (currentPage - 1) * perPage;
        const endIdx = startIdx + perPage;

        if (animate && !isAnimating) {
            isAnimating = true;
            // Fade out current cards.
            $grid.addClass('mc-grid-animating');
            $grid.css('opacity', 0);

            setTimeout(() => {
                // Hide all cards first.
                $cards.hide().removeClass('mc-card-visible');

                // Show only cards for current page with staggered animation.
                let visibleIndex = 0;
                for (let i = 0; i < filteredCards.length; i++) {
                    if (i >= startIdx && i < endIdx) {
                        ((card, delay) => {
                            setTimeout(() => {
                                card.show().addClass('mc-card-visible');
                            }, delay);
                        })(filteredCards[i], visibleIndex * 50);
                        visibleIndex++;
                    }
                }

                // Fade grid back in.
                $grid.css('opacity', 1);

                setTimeout(() => {
                    $grid.removeClass('mc-grid-animating');
                    isAnimating = false;
                }, 300 + (visibleIndex * 50));
            }, 200);
        } else {
            // No animation - just show/hide.
            $cards.hide().removeClass('mc-card-visible');
            for (let i = 0; i < filteredCards.length; i++) {
                if (i >= startIdx && i < endIdx) {
                    filteredCards[i].show().addClass('mc-card-visible');
                }
            }
        }

        // Show/hide no results message.
        if (totalItems === 0) {
            $noResults.show();
            $pagination.hide();
        } else {
            $noResults.hide();
            $pagination.show();
        }

        // Update pagination controls.
        renderPaginationControls(totalPages);
    };

    /**
     * Helper for animated page render.
     */
    const renderPageWithAnimation = () => {
        renderPage(true);
    };

    /**
     * Render pagination buttons.
     * @param {number} totalPages Total number of pages
     */
    const renderPaginationControls = (totalPages) => {
        $paginationPages.empty();

        // Update prev/next buttons.
        $prevBtn.prop('disabled', currentPage <= 1);
        $nextBtn.prop('disabled', currentPage >= totalPages);

        // Update page info.
        $pageInfo.text(pageLabel + ' ' + currentPage + ' ' + ofLabel + ' ' + totalPages);

        if (totalPages <= 1) {
            return;
        }

        // Build page buttons.
        const maxButtons = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxButtons / 2));
        let endPage = Math.min(totalPages, startPage + maxButtons - 1);

        // Adjust start if we're near the end.
        if (endPage - startPage < maxButtons - 1) {
            startPage = Math.max(1, endPage - maxButtons + 1);
        }

        // First page button if not visible.
        if (startPage > 1) {
            const firstPageButton = '<button class="mc-button mc-pagination-btn mc-pagination-page" ' +
                'data-mc-button="light" data-page="1">1</button>';
            $paginationPages.append(
                $(firstPageButton)
            );
            if (startPage > 2) {
                $paginationPages.append('<span class="mc-pagination-dots">...</span>');
            }
        }

        // Page buttons.
        for (let p = startPage; p <= endPage; p++) {
            const pageButton = '<button class="mc-button mc-pagination-btn mc-pagination-page" ' +
                'data-mc-button="light" data-page="' + p + '">' + p + '</button>';
            const $btn = $(pageButton);
            if (p === currentPage) {
                $btn.addClass('active');
            }
            $paginationPages.append($btn);
        }

        // Last page button if not visible.
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                $paginationPages.append('<span class="mc-pagination-dots">...</span>');
            }
            var lastPageBtn = '<button class="mc-button mc-pagination-btn mc-pagination-page" data-mc-button="light" data-page="' +
                totalPages + '">' + totalPages + '</button>';
            $paginationPages.append($(lastPageBtn));
        }
    };

    /**
     * Scroll to grid on page change.
     */
    const scrollToGrid = () => {
        $('html, body').animate({
            scrollTop: $grid.offset().top - 100
        }, 300);
    };

    // Page button click.
    $(document).on('click', '.mc-pagination-page', function() {
        if (isAnimating) {
            return;
        }
        const page = parseInt($(this).data('page'), 10);
        if (page && page !== currentPage) {
            currentPage = page;
            renderPageWithAnimation();
            scrollToGrid();
        }
    });

    // Prev/Next buttons.
    $prevBtn.on('click', () => {
        if (isAnimating) {
            return;
        }
        if (currentPage > 1) {
            currentPage--;
            renderPageWithAnimation();
            scrollToGrid();
        }
    });

    $nextBtn.on('click', () => {
        if (isAnimating) {
            return;
        }
        const totalPages = Math.ceil(filteredCards.length / perPage) || 1;
        if (currentPage < totalPages) {
            currentPage++;
            renderPageWithAnimation();
            scrollToGrid();
        }
    });

    /**
     * Sort courses.
     */
    const sortCourses = () => {
        const sortBy = $sortSelect.val();
        const $cardsArray = $cards.get();

        $cardsArray.sort((a, b) => {
            const $a = $(a);
            const $b = $(b);

            switch (sortBy) {
                case 'pricelow':
                    return (parseFloat($a.data('price')) || 0) - (parseFloat($b.data('price')) || 0);
                case 'pricehigh':
                    return (parseFloat($b.data('price')) || 0) - (parseFloat($a.data('price')) || 0);
                case 'newest':
                    return parseInt($b.data('timecreated') || $b.data('id'), 10) -
                           parseInt($a.data('timecreated') || $a.data('id'), 10);
                case 'popular':
                default:
                    return (parseFloat($b.data('rating')) || 0) - (parseFloat($a.data('rating')) || 0);
            }
        });

        $grid.append($cardsArray);
        // Refresh cards reference after reordering.
        $cards = $grid.find('.mc-course-card');
    };

    // Clear all filters
    $('#mc-clear-filters').on('click', () => {
        $searchInput.val('');
        $('input[name="coursetype"]').prop('checked', false);
        $('input[name="category"]').prop('checked', false);
        $('input[name="rating"]').prop('checked', false);
        $('input[name="level"]').prop('checked', false);
        $priceRange.val(maxPrice);
        $priceValue.text(formatPriceValue(maxPrice));
        applyFilters();
    });

    // Event listeners
    $searchInput.on('input', applyFilters);
    $sortSelect.on('change', () => {
        sortCourses();
        applyFilters();
    });
    $('input[name="coursetype"], input[name="category"], input[name="rating"], input[name="level"]').on('change', applyFilters);

    // Add to cart handler
    $grid.on('click', '.mc-add-to-cart-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const $btn = $(this);
        const $card = $btn.closest('.mc-course-card');
        const itemId = $card.data('id');
        const itemType = $card.data('itemtype') || 'course';

        // Prevent double-clicks.
        if ($btn.hasClass('loading')) {
            return;
        }

        // Check if user is logged in - show modal if not.
        if (!isLoggedIn) {
            showLoginModal();
            return;
        }

        // Show loading state.
        const $icon = $btn.find('i');
        const originalClass = $icon.attr('class');
        $btn.addClass('loading')
            .prop('disabled', true)
            .attr('aria-busy', 'true')
            .attr('data-mc-button-state', 'loading');
        $icon.attr('class', 'bi bi-arrow-repeat');

        const action = itemType === 'course' ? 'addcourse' : 'addbundle';
        const args = {action: action};
        if (itemType === 'course') {
            args.courseid = itemId;
        } else {
            args.bundleid = itemId;
        }

        Ajax.call([{
            methodname: 'local_moderncommerce_update_cart',
            args: args,
        }])[0].done(function(response) {
            response.success = true;
            response.cartcount = response.count;
            if (response.success) {
                // Show success state.
                $icon.attr('class', 'bi bi-check-lg');
                $btn.removeClass('loading')
                    .addClass('added')
                    .removeAttr('aria-busy')
                    .removeAttr('data-mc-button-state');

                // Update cart count in header if exists.
                const $cartBadge = $('.mc-cart-count, .cart-count, [data-region="cart-count"]');
                if ($cartBadge.length && response.cartcount !== undefined) {
                    $cartBadge.text(response.cartcount).show();
                }

                // Refresh cart dropdown content.
                const $cartDropdown = $('#cartDropdownMenu');
                if ($cartDropdown.length) {
                    Ajax.call([{
                        methodname: 'local_moderncommerce_get_cart_dropdown',
                        args: {},
                    }])[0].done(function(dropdownResponse) {
                        if (dropdownResponse.success && dropdownResponse.html) {
                            $cartDropdown.html(dropdownResponse.html);
                        }
                    });
                }

                // Show success notification.
                Notification.addNotification({
                    message: response.message,
                    type: 'success'
                });

                // Reset button after delay.
                setTimeout(function() {
                    $icon.attr('class', originalClass);
                    $btn.removeClass('added')
                        .prop('disabled', false)
                        .removeAttr('aria-busy')
                        .removeAttr('data-mc-button-state');
                }, 2000);
            } else {
                // Handle failure response.
                $icon.attr('class', originalClass);
                $btn.removeClass('loading')
                    .prop('disabled', false)
                    .removeAttr('aria-busy')
                    .removeAttr('data-mc-button-state');
                Notification.addNotification({
                    message: response.error || failedToAddToCart,
                    type: 'error'
                });
            }
        }).fail(function(error) {
            // Handle AJAX error.
            $icon.attr('class', originalClass);
            $btn.removeClass('loading')
                .prop('disabled', false)
                .removeAttr('aria-busy')
                .removeAttr('data-mc-button-state');

            // Check if login is required (session expired or guest).
            let errorMsg = failedToAddToCart;
            if (error && error.message) {
                errorMsg = error.message;
            }

            Notification.addNotification({
                message: errorMsg,
                type: 'error'
            });
        });
    });

    // Initial render
    applyFilters();
};
