import './bootstrap';

// Dashboard Search and Filtering
class DashboardSearch {
    constructor() {
        this.searchInput = document.querySelector('input[name="search"]');
        this.searchForm = document.querySelector('.search-container').closest('form');
        this.searchContainer = document.querySelector('.search-container');
        this.searchTimeout = null;
        this.suggestionsContainer = null;
        
        if (this.searchInput) {
            this.init();
        }
    }

    init() {
        // Create suggestions container
        this.createSuggestionsContainer();
        
        // Add event listeners
        this.searchInput.addEventListener('input', (e) => this.handleSearchInput(e));
        this.searchInput.addEventListener('focus', (e) => this.handleSearchFocus(e));
        this.searchInput.addEventListener('blur', (e) => this.handleSearchBlur(e));
        this.searchInput.addEventListener('keydown', (e) => this.handleKeyDown(e));
        
        // Prevent form submission on enter if suggestions are visible
        this.searchForm.addEventListener('submit', (e) => this.handleFormSubmit(e));
    }

    createSuggestionsContainer() {
        this.suggestionsContainer = document.createElement('div');
        this.suggestionsContainer.className = 'search-suggestions absolute top-full left-0 right-0 bg-white border border-gray-300 rounded-b-md shadow-lg z-10 hidden max-h-60 overflow-y-auto';
        this.searchContainer.appendChild(this.suggestionsContainer);
        this.searchContainer.style.position = 'relative';
    }

    handleSearchInput(event) {
        const query = event.target.value.trim();
        
        // Clear previous timeout
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout);
        }

        // Hide suggestions if query is too short
        if (query.length < 2) {
            this.hideSuggestions();
            return;
        }

        // Debounce the search
        this.searchTimeout = setTimeout(() => {
            this.fetchSuggestions(query);
        }, 300);
    }

    handleSearchFocus(event) {
        const query = event.target.value.trim();
        if (query.length >= 2) {
            this.showSuggestions();
        }
    }

    handleSearchBlur(event) {
        // Delay hiding to allow clicking on suggestions
        setTimeout(() => {
            this.hideSuggestions();
        }, 150);
    }

    handleKeyDown(event) {
        const suggestions = this.suggestionsContainer.querySelectorAll('.suggestion-item');
        const activeSuggestion = this.suggestionsContainer.querySelector('.suggestion-item.active');
        
        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();
                this.navigateSuggestions(suggestions, activeSuggestion, 'down');
                break;
            case 'ArrowUp':
                event.preventDefault();
                this.navigateSuggestions(suggestions, activeSuggestion, 'up');
                break;
            case 'Enter':
                if (activeSuggestion) {
                    event.preventDefault();
                    this.selectSuggestion(activeSuggestion);
                }
                break;
            case 'Escape':
                this.hideSuggestions();
                break;
        }
    }

    handleFormSubmit(event) {
        const activeSuggestion = this.suggestionsContainer.querySelector('.suggestion-item.active');
        if (activeSuggestion) {
            event.preventDefault();
            this.selectSuggestion(activeSuggestion);
        }
    }

    async fetchSuggestions(query) {
        try {
            // Show loading state
            this.showLoadingState();

            const response = await fetch(`/dashboard/suggestions?query=${encodeURIComponent(query)}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error('Failed to fetch suggestions');
            }

            const suggestions = await response.json();
            this.displaySuggestions(suggestions, query);
        } catch (error) {
            console.error('Error fetching suggestions:', error);
            this.hideSuggestions();
        }
    }

    showLoadingState() {
        this.suggestionsContainer.innerHTML = `
            <div class="p-3 text-center text-gray-500">
                <div class="inline-flex items-center">
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Searching...
                </div>
            </div>
        `;
        this.showSuggestions();
    }

    displaySuggestions(suggestions, query) {
        if (!suggestions || suggestions.length === 0) {
            this.suggestionsContainer.innerHTML = `
                <div class="p-3 text-center text-gray-500">
                    No suggestions found for "${query}"
                </div>
            `;
        } else {
            const suggestionsHtml = suggestions.map(suggestion => {
                const highlightedText = this.highlightMatch(suggestion.text, query);
                return `
                    <div class="suggestion-item p-3 hover:bg-gray-100 cursor-pointer border-b border-gray-100 last:border-b-0" 
                         data-value="${suggestion.value}" 
                         data-type="${suggestion.type}">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center mr-3">
                                ${this.getSuggestionIcon(suggestion.type)}
                            </div>
                            <div class="flex-1">
                                <div class="text-sm font-medium text-gray-900">${highlightedText}</div>
                                ${suggestion.subtitle ? `<div class="text-xs text-gray-500">${suggestion.subtitle}</div>` : ''}
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            this.suggestionsContainer.innerHTML = suggestionsHtml;

            // Add click listeners to suggestions
            this.suggestionsContainer.querySelectorAll('.suggestion-item').forEach(item => {
                item.addEventListener('click', () => this.selectSuggestion(item));
            });
        }

        this.showSuggestions();
    }

    highlightMatch(text, query) {
        const regex = new RegExp(`(${query})`, 'gi');
        return text.replace(regex, '<mark class="bg-yellow-200">$1</mark>');
    }

    getSuggestionIcon(type) {
        switch (type) {
            case 'customer':
                return '<svg class="w-4 h-4 text-gray-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path></svg>';
            case 'organization':
                return '<svg class="w-4 h-4 text-gray-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 110 2h-3a1 1 0 01-1-1v-6a1 1 0 00-1-1H9a1 1 0 00-1 1v6a1 1 0 01-1 1H4a1 1 0 110-2V4zm3 1h2v2H7V5zm2 4H7v2h2V9zm2-4h2v2h-2V5zm2 4h-2v2h2V9z" clip-rule="evenodd"></path></svg>';
            case 'email':
                return '<svg class="w-4 h-4 text-gray-500" fill="currentColor" viewBox="0 0 20 20"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"></path><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"></path></svg>';
            case 'phone':
                return '<svg class="w-4 h-4 text-gray-500" fill="currentColor" viewBox="0 0 20 20"><path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"></path></svg>';
            default:
                return '<svg class="w-4 h-4 text-gray-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path></svg>';
        }
    }

    navigateSuggestions(suggestions, activeSuggestion, direction) {
        // Remove current active state
        if (activeSuggestion) {
            activeSuggestion.classList.remove('active', 'bg-blue-100');
        }

        let newActiveIndex = -1;
        
        if (activeSuggestion) {
            const currentIndex = Array.from(suggestions).indexOf(activeSuggestion);
            newActiveIndex = direction === 'down' 
                ? Math.min(currentIndex + 1, suggestions.length - 1)
                : Math.max(currentIndex - 1, 0);
        } else {
            newActiveIndex = direction === 'down' ? 0 : suggestions.length - 1;
        }

        if (newActiveIndex >= 0 && newActiveIndex < suggestions.length) {
            const newActive = suggestions[newActiveIndex];
            newActive.classList.add('active', 'bg-blue-100');
            newActive.scrollIntoView({ block: 'nearest' });
        }
    }

    selectSuggestion(suggestionElement) {
        const value = suggestionElement.dataset.value;
        const type = suggestionElement.dataset.type;
        
        this.searchInput.value = value;
        this.hideSuggestions();
        
        // Submit the form to perform the search
        this.searchForm.submit();
    }

    showSuggestions() {
        this.suggestionsContainer.classList.remove('hidden');
    }

    hideSuggestions() {
        this.suggestionsContainer.classList.add('hidden');
    }
}

// Dynamic Table Filtering - Only for Dashboard
class TableFilter {
    constructor() {
        // Only initialize on dashboard page
        if (window.location.pathname !== '/dashboard') {
            return;
        }
        
        this.filterInputs = document.querySelectorAll('[data-filter]');
        this.filteredTable = document.querySelector('[data-filterable]');
        
        if (this.filterInputs.length > 0 && this.filteredTable) {
            this.init();
        }
    }

    init() {
        this.filterInputs.forEach(input => {
            input.addEventListener('input', () => this.applyFilters());
            input.addEventListener('change', () => this.applyFilters());
        });
    }

    applyFilters() {
        const filters = {};
        
        // Collect all filter values
        this.filterInputs.forEach(input => {
            const filterKey = input.dataset.filter;
            const value = input.value.toLowerCase().trim();
            if (value) {
                filters[filterKey] = value;
            }
        });

        // Apply filters to table rows
        const rows = this.filteredTable.querySelectorAll('[data-filterable-row]');
        let visibleCount = 0;

        rows.forEach(row => {
            let isVisible = true;

            // Check each filter
            Object.entries(filters).forEach(([filterKey, filterValue]) => {
                let cellData = '';
                
                // Map filter keys to actual data attributes
                switch(filterKey) {
                    case 'organization':
                        cellData = row.dataset.organization || '';
                        break;
                    case 'jobTitle':
                        cellData = row.dataset.jobTitle || '';
                        break;
                    case 'email':
                        cellData = row.dataset.email || '';
                        break;
                    case 'fullName':
                        cellData = row.dataset.fullName || '';
                        break;
                    default:
                        cellData = row.dataset[filterKey] || '';
                }
                
                if (!cellData.includes(filterValue)) {
                    isVisible = false;
                }
            });

            // Show/hide row
            if (isVisible) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        // Update results count if element exists
        this.updateResultsCount(visibleCount, rows.length);
    }

    updateResultsCount(visible, total) {
        const countElement = document.querySelector('[data-results-count]');
        if (countElement) {
            countElement.textContent = `Showing ${visible} customers`;
        }
    }
}

// Live Search for Customer Lists
class LiveSearch {
    constructor() {
        this.searchInput = document.querySelector('[data-live-search]');
        this.searchResults = document.querySelector('[data-search-results]');
        this.searchTimeout = null;
        
        if (this.searchInput && this.searchResults) {
            this.init();
        }
    }

    init() {
        this.searchInput.addEventListener('input', (e) => this.handleInput(e));
    }

    handleInput(event) {
        const query = event.target.value.trim();
        
        // Clear previous timeout
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout);
        }

        // Debounce the search
        this.searchTimeout = setTimeout(() => {
            if (query.length >= 2) {
                this.performSearch(query);
            } else {
                this.clearResults();
            }
        }, 300);
    }

    async performSearch(query) {
        try {
            this.showLoadingState();

            const url = this.searchInput.dataset.searchUrl || '/customers/search';
            const response = await fetch(`${url}?q=${encodeURIComponent(query)}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error('Search failed');
            }

            const results = await response.json();
            this.displayResults(results);
        } catch (error) {
            console.error('Search error:', error);
            this.showErrorState();
        }
    }

    showLoadingState() {
        this.searchResults.innerHTML = `
            <div class="p-4 text-center">
                <div class="inline-flex items-center text-gray-500">
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Searching...
                </div>
            </div>
        `;
    }

    displayResults(results) {
        if (!results || results.length === 0) {
            this.searchResults.innerHTML = `
                <div class="p-4 text-center text-gray-500">
                    No customers found
                </div>
            `;
            return;
        }

        const resultsHtml = results.map(customer => `
            <div class="p-4 border-b border-gray-100 hover:bg-gray-50">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="font-medium text-gray-900">${customer.full_name}</h4>
                        <p class="text-sm text-gray-500">${customer.email}</p>
                        ${customer.organization ? `<p class="text-xs text-gray-400">${customer.organization}</p>` : ''}
                    </div>
                    <a href="/customers/${customer.slug}" class="text-blue-600 hover:text-blue-800 text-sm">View</a>
                </div>
            </div>
        `).join('');

        this.searchResults.innerHTML = resultsHtml;
    }

    showErrorState() {
        this.searchResults.innerHTML = `
            <div class="p-4 text-center text-red-500">
                Search failed. Please try again.
            </div>
        `;
    }

    clearResults() {
        this.searchResults.innerHTML = '';
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    new DashboardSearch();
    new TableFilter();
    new LiveSearch();
});

// Export for use in other modules
window.DashboardSearch = DashboardSearch;
window.TableFilter = TableFilter;
window.LiveSearch = LiveSearch;
