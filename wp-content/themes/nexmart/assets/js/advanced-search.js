/**
 * Advanced Search System with AJAX
 * Modern implementation with live suggestions
 */

(function() {
    'use strict';

    class AdvancedSearch {
        constructor() {
            this.searchInput = null;
            this.searchResults = null;
            this.debounceTimer = null;
            this.currentRequest = null;
            this.recentSearches = this.loadRecentSearches();
            this.init();
        }

        init() {
            this.createSearchUI();
            this.attachEventListeners();
            this.loadTrendingSearches();
        }

        createSearchUI() {
            // Find all search inputs
            const searchInputs = document.querySelectorAll('input[type="search"][name="s"]');
            
            searchInputs.forEach(input => {
                const parent = input.closest('form') || input.parentElement;
                
                // Create results dropdown
                const resultsDiv = document.createElement('div');
                resultsDiv.className = 'search-results-dropdown absolute top-full left-0 right-0 mt-2 bg-white rounded-xl shadow-2xl border border-gray-200 hidden z-50 max-h-[80vh] overflow-y-auto';
                resultsDiv.innerHTML = `
                    <div class="search-loading hidden">
                        <div class="p-8 text-center">
                            <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-indigo-600 border-t-transparent"></div>
                            <p class="mt-2 text-gray-600">Searching...</p>
                        </div>
                    </div>
                    <div class="search-content"></div>
                `;
                
                // Make parent relative if not already
                if (parent) {
                    parent.style.position = 'relative';
                    parent.appendChild(resultsDiv);
                }
                
                input.setAttribute('autocomplete', 'off');
                input.dataset.searchEnhanced = 'true';
            });
        }

        attachEventListeners() {
            // Handle all search inputs
            document.addEventListener('input', (e) => {
                if (e.target.matches('input[type="search"][name="s"]') && e.target.dataset.searchEnhanced) {
                    this.handleSearchInput(e);
                }
            });

            // Handle focus
            document.addEventListener('focus', (e) => {
                if (e.target.matches('input[type="search"][name="s"]') && e.target.dataset.searchEnhanced) {
                    this.handleSearchFocus(e);
                }
            }, true);

            // Handle blur (with delay for clicking results)
            document.addEventListener('blur', (e) => {
                if (e.target.matches('input[type="search"][name="s"]') && e.target.dataset.searchEnhanced) {
                    setTimeout(() => this.handleSearchBlur(e), 200);
                }
            }, true);

            // Handle keyboard navigation
            document.addEventListener('keydown', (e) => {
                if (e.target.matches('input[type="search"][name="s"]') && e.target.dataset.searchEnhanced) {
                    this.handleKeyboard(e);
                }
            });

            // Close on outside click
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.search-results-dropdown') && !e.target.matches('input[type="search"][name="s"]')) {
                    this.hideAllDropdowns();
                }
            });
        }

        handleSearchInput(e) {
            const input = e.target;
            const query = input.value.trim();
            const dropdown = input.closest('form, div').querySelector('.search-results-dropdown');

            if (!dropdown) return;

            // Clear previous timer
            clearTimeout(this.debounceTimer);

            if (query.length < 2) {
                this.showRecentAndTrending(dropdown);
                return;
            }

            // Show loading
            this.showLoading(dropdown);

            // Debounce search
            this.debounceTimer = setTimeout(() => {
                this.performSearch(query, dropdown, input);
            }, 300);
        }

        handleSearchFocus(e) {
            const input = e.target;
            const dropdown = input.closest('form, div').querySelector('.search-results-dropdown');
            
            if (!dropdown) return;

            const query = input.value.trim();
            
            if (query.length < 2) {
                this.showRecentAndTrending(dropdown);
            } else {
                dropdown.classList.remove('hidden');
            }
        }

        handleSearchBlur(e) {
            const input = e.target;
            const dropdown = input.closest('form, div').querySelector('.search-results-dropdown');
            
            if (!dropdown) return;
            
            // Only hide if not clicking inside dropdown
            if (!dropdown.matches(':hover')) {
                dropdown.classList.add('hidden');
            }
        }

        handleKeyboard(e) {
            const dropdown = e.target.closest('form, div').querySelector('.search-results-dropdown');
            if (!dropdown || dropdown.classList.contains('hidden')) return;

            const items = dropdown.querySelectorAll('[data-search-item]');
            const currentIndex = Array.from(items).findIndex(item => item.matches('.bg-gray-100'));

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                const nextIndex = Math.min(currentIndex + 1, items.length - 1);
                this.highlightItem(items, nextIndex);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                const prevIndex = Math.max(currentIndex - 1, 0);
                this.highlightItem(items, prevIndex);
            } else if (e.key === 'Enter' && currentIndex >= 0) {
                e.preventDefault();
                items[currentIndex].click();
            } else if (e.key === 'Escape') {
                dropdown.classList.add('hidden');
            }
        }

        highlightItem(items, index) {
            items.forEach(item => item.classList.remove('bg-gray-100'));
            if (items[index]) {
                items[index].classList.add('bg-gray-100');
                items[index].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            }
        }

        async performSearch(query, dropdown, input) {
            // Cancel previous request
            if (this.currentRequest) {
                this.currentRequest.abort();
            }

            const controller = new AbortController();
            this.currentRequest = controller;

            try {
                const response = await fetch(nexmartObj.ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'nexmart_live_search',
                        nonce: nexmartObj.nonce,
                        query: query
                    }),
                    signal: controller.signal
                });

                const data = await response.json();

                if (data.success) {
                    this.displayResults(data.data, dropdown, query);
                    this.saveRecentSearch(query);
                } else {
                    this.showError(dropdown, data.data?.message || 'Search failed');
                }
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error('Search error:', error);
                    this.showError(dropdown, 'An error occurred while searching');
                }
            } finally {
                this.currentRequest = null;
            }
        }

        displayResults(data, dropdown, query) {
            const content = dropdown.querySelector('.search-content');
            
            let html = '';

            // Show query summary
            const totalResults = (data.products?.length || 0) + (data.categories?.length || 0) + (data.vendors?.length || 0);
            
            if (totalResults === 0) {
                html = this.getNoResultsHTML(query);
            } else {
                html += `
                    <div class="p-4 bg-gradient-to-r from-indigo-50 to-purple-50 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <p class="text-sm text-gray-700">
                                Found <strong class="text-indigo-600">${totalResults} results</strong> for "${query}"
                            </p>
                            <a href="/?s=${encodeURIComponent(query)}" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium">
                                View all →
                            </a>
                        </div>
                    </div>
                `;

                // Categories
                if (data.categories?.length > 0) {
                    html += this.getCategoriesHTML(data.categories);
                }

                // Vendors
                if (data.vendors?.length > 0) {
                    html += this.getVendorsHTML(data.vendors);
                }

                // Products
                if (data.products?.length > 0) {
                    html += this.getProductsHTML(data.products);
                }

                // View all link
                html += `
                    <div class="p-4 border-t border-gray-200 bg-gray-50">
                        <a href="/?s=${encodeURIComponent(query)}" 
                           class="flex items-center justify-center gap-2 text-indigo-600 hover:text-indigo-700 font-medium text-sm">
                            <span>View all ${totalResults} results</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    </div>
                `;
            }

            content.innerHTML = html;
            this.hideLoading(dropdown);
            dropdown.classList.remove('hidden');
        }

        getCategoriesHTML(categories) {
            let html = `
                <div class="p-4 border-b border-gray-200">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase mb-3 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                        </svg>
                        Categories
                    </h3>
                    <div class="space-y-1">
            `;

            categories.forEach(cat => {
                html += `
                    <a href="/shop?category=${cat.id}" 
                       data-search-item
                       class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-100 transition-colors group">
                        <span class="text-sm text-gray-700 group-hover:text-indigo-600">${this.escapeHtml(cat.name)}</span>
                        <span class="text-xs text-gray-500">${cat.product_count || 0} products</span>
                    </a>
                `;
            });

            html += `</div></div>`;
            return html;
        }

        getVendorsHTML(vendors) {
            let html = `
                <div class="p-4 border-b border-gray-200">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase mb-3 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                        Stores
                    </h3>
                    <div class="space-y-1">
            `;

            vendors.forEach(vendor => {
                const initials = vendor.store_name.substring(0, 2).toUpperCase();
                html += `
                    <a href="/vendor/${vendor.store_slug}" 
                       data-search-item
                       class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-100 transition-colors group">
                        <div class="w-10 h-10 bg-gradient-to-br from-indigo-100 to-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <span class="text-sm font-bold text-indigo-600">${initials}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate group-hover:text-indigo-600">${this.escapeHtml(vendor.store_name)}</p>
                            <p class="text-xs text-gray-500">${vendor.product_count || 0} products</p>
                        </div>
                        <svg class="w-4 h-4 text-gray-400 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                `;
            });

            html += `</div></div>`;
            return html;
        }

        getProductsHTML(products) {
            let html = `
                <div class="p-4">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase mb-3 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                        Products
                    </h3>
                    <div class="space-y-2">
            `;

            products.forEach(product => {
                const price = product.sale_price > 0 && product.sale_price < product.price ? product.sale_price : product.price;
                const hasDiscount = product.sale_price > 0 && product.sale_price < product.price;
                const discountPercent = hasDiscount ? Math.round(((product.price - product.sale_price) / product.price) * 100) : 0;
                
                html += `
                    <a href="/product/${product.slug}" 
                       data-search-item
                       class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-100 transition-colors group">
                        <div class="w-16 h-16 bg-gray-100 rounded-lg overflow-hidden flex-shrink-0">
                            ${product.primary_image ? 
                                `<img src="${product.primary_image}" alt="${this.escapeHtml(product.name)}" class="w-full h-full object-cover">` :
                                `<div class="w-full h-full flex items-center justify-center text-gray-400">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>`
                            }
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate group-hover:text-indigo-600">${this.escapeHtml(product.name)}</p>
                            ${product.vendor_name ? `<p class="text-xs text-gray-500">${this.escapeHtml(product.vendor_name)}</p>` : ''}
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-sm font-bold text-indigo-600">$${parseFloat(price).toFixed(2)}</span>
                                ${hasDiscount ? `
                                    <span class="text-xs text-gray-500 line-through">$${parseFloat(product.price).toFixed(2)}</span>
                                    <span class="text-xs text-red-600 font-medium">-${discountPercent}%</span>
                                ` : ''}
                            </div>
                        </div>
                        <svg class="w-4 h-4 text-gray-400 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                `;
            });

            html += `</div></div>`;
            return html;
        }

        getNoResultsHTML(query) {
            return `
                <div class="p-8 text-center">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">No results found</h3>
                    <p class="text-sm text-gray-600 mb-4">Try different keywords or browse our categories</p>
                    <a href="/shop" class="inline-flex items-center gap-2 bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors text-sm font-medium">
                        Browse All Products
                    </a>
                </div>
            `;
        }

        showRecentAndTrending(dropdown) {
            const content = dropdown.querySelector('.search-content');
            
            let html = '';

            // Recent searches
            if (this.recentSearches.length > 0) {
                html += `
                    <div class="p-4 border-b border-gray-200">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-xs font-semibold text-gray-500 uppercase flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Recent Searches
                            </h3>
                            <button onclick="advancedSearch.clearRecentSearches()" class="text-xs text-gray-500 hover:text-indigo-600">Clear</button>
                        </div>
                        <div class="space-y-1">
                `;

                this.recentSearches.slice(0, 5).forEach(search => {
                    html += `
                        <a href="/?s=${encodeURIComponent(search)}" 
                           data-search-item
                           class="flex items-center gap-2 p-2 rounded-lg hover:bg-gray-100 transition-colors group">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <span class="text-sm text-gray-700 group-hover:text-indigo-600">${this.escapeHtml(search)}</span>
                        </a>
                    `;
                });

                html += `</div></div>`;
            }

            // Trending searches
            html += `
                <div class="p-4">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase mb-3 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                        </svg>
                        Trending
                    </h3>
                    <div class="flex flex-wrap gap-2">
                        ${['Headphones', 'Laptop', 'Smartphone', 'Camera', 'Watch'].map(term => `
                            <a href="/?s=${encodeURIComponent(term)}" 
                               class="px-3 py-1.5 bg-gray-100 hover:bg-indigo-100 hover:text-indigo-700 rounded-full text-xs font-medium transition-colors">
                                ${term}
                            </a>
                        `).join('')}
                    </div>
                </div>
            `;

            content.innerHTML = html;
            this.hideLoading(dropdown);
            dropdown.classList.remove('hidden');
        }

        showLoading(dropdown) {
            dropdown.querySelector('.search-loading').classList.remove('hidden');
            dropdown.querySelector('.search-content').classList.add('hidden');
            dropdown.classList.remove('hidden');
        }

        hideLoading(dropdown) {
            dropdown.querySelector('.search-loading').classList.add('hidden');
            dropdown.querySelector('.search-content').classList.remove('hidden');
        }

        showError(dropdown, message) {
            const content = dropdown.querySelector('.search-content');
            content.innerHTML = `
                <div class="p-8 text-center">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <p class="text-sm text-gray-600">${this.escapeHtml(message)}</p>
                </div>
            `;
            this.hideLoading(dropdown);
            dropdown.classList.remove('hidden');
        }

        hideAllDropdowns() {
            document.querySelectorAll('.search-results-dropdown').forEach(dropdown => {
                dropdown.classList.add('hidden');
            });
        }

        saveRecentSearch(query) {
            if (!this.recentSearches.includes(query)) {
                this.recentSearches.unshift(query);
                this.recentSearches = this.recentSearches.slice(0, 10);
                localStorage.setItem('nexmart_recent_searches', JSON.stringify(this.recentSearches));
            }
        }

        loadRecentSearches() {
            try {
                return JSON.parse(localStorage.getItem('nexmart_recent_searches') || '[]');
            } catch {
                return [];
            }
        }

        clearRecentSearches() {
            this.recentSearches = [];
            localStorage.removeItem('nexmart_recent_searches');
            this.hideAllDropdowns();
        }

        loadTrendingSearches() {
            // Could be loaded from server via AJAX
            // For now, using static trending terms
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.advancedSearch = new AdvancedSearch();
        });
    } else {
        window.advancedSearch = new AdvancedSearch();
    }
})();
