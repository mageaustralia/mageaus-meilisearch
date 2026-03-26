/**
 * Meilisearch Search — Vanilla JS
 * Replaces: meilisearchBundle.js, common.js, mustache.min.js,
 *           autocomplete-templates.js, instantsearch.js
 *
 * Requires: meilisearch-client.js (official SDK) + window.meilisearchConfig
 */
(function() {
    'use strict';

    var config, client;

    // ── Helpers ──────────────────────────────────────────────────────

    function esc(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function formatPrice(amount) {
        if (!amount && amount !== 0) return '';
        var p = config.priceFormat || {};
        var n = parseFloat(amount).toFixed(p.precision || 2);
        var parts = n.split('.');
        // Add group separator
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, p.groupSymbol || ',');
        var formatted = parts.join(p.decimalSymbol || '.');
        return (p.pattern || '$%s').replace('%s', formatted);
    }

    function getPrice(hit) {
        var key = 'price' + (config.priceKey || '');
        var parts = key.split('.');
        var val = hit;
        for (var i = 0; i < parts.length; i++) {
            if (!val) return null;
            val = val[parts[i]];
        }
        return val;
    }

    function bestImage(hit) {
        var urls = [hit.small_image_url, hit.image_url, hit.thumbnail_url];
        for (var i = 0; i < urls.length; i++) {
            var u = urls[i];
            if (u) {
                if (u.indexOf('//') === 0) u = 'https:' + u;
                if (u.indexOf('placeholder') === -1) return u;
            }
        }
        return hit.image_url ? (hit.image_url.indexOf('//') === 0 ? 'https:' + hit.image_url : hit.image_url) : '';
    }

    function highlight(hit, field) {
        if (hit._formatted && hit._formatted[field]) return hit._formatted[field];
        return esc(hit[field] || '');
    }

    function debounce(fn, ms) {
        var t;
        return function() {
            var args = arguments, ctx = this;
            clearTimeout(t);
            t = setTimeout(function() { fn.apply(ctx, args); }, ms);
        };
    }

    function createClient() {
        if (typeof MeiliSearch !== 'undefined') {
            return new MeiliSearch({ host: config.serverUrl });
        }
        console.error('MeiliSearch client not loaded');
        return null;
    }

    // ── Autocomplete ────────────────────────────────────────────────

    function initAutocomplete() {
        if (!config.autocomplete || !config.autocomplete.enabled) return;

        var inputs = document.querySelectorAll(config.autocomplete.selector);
        if (!inputs.length) return;

        inputs.forEach(function(input) { setupAutocomplete(input); });
    }

    function setupAutocomplete(input) {
        var dropdown = document.createElement('div');
        dropdown.className = 'meilisearch-autocomplete';
        dropdown.style.cssText = 'z-index:99999;display:none';

        var modal = input.closest('#search_modal') || input.closest('dialog');
        if (modal) {
            // Inside modal: append near input so it flows inside the dialog
            var inputContainer = input.parentElement;
            inputContainer.style.position = 'relative';
            inputContainer.parentElement.appendChild(dropdown);
        } else {
            document.body.appendChild(dropdown);
        }

        var isOpen = false;
        var searchTimeout;

        function position() {
            if (modal) {
                dropdown.style.cssText = 'z-index:99999;position:relative;width:100%';
            } else {
                var top = window.innerWidth <= 1199 ? 62 : 100;
                dropdown.style.cssText = 'z-index:99999;position:absolute;top:' + top + 'px;left:50%;right:auto;width:100%;max-width:1200px;transform:translateX(-50%)';
            }
        }

        function show() { position(); dropdown.style.display = ''; isOpen = true; }
        function hide() { dropdown.style.display = 'none'; isOpen = false; }

        function search(query) {
            if (!query || query.length < 2) { hide(); return; }
            if (!isOpen) { dropdown.innerHTML = '<div class="meilisearch-autocomplete-loading">Searching...</div>'; show(); }

            var promises = [];
            var idx = config.indexName;

            // Products
            var nProducts = parseInt(config.autocomplete.nbOfProductsSuggestions) || 10;
            if (nProducts > 0) {
                promises.push(
                    client.index(idx + '_products').search(query, {
                        limit: nProducts,
                        attributesToHighlight: ['name']
                    }).then(function(r) { return { type: 'products', hits: r.hits }; })
                );
            }

            // Categories
            var nCats = parseInt(config.autocomplete.nbOfCategoriesSuggestions) || 5;
            if (nCats > 0) {
                promises.push(
                    client.index(idx + '_categories').search(query, {
                        limit: nCats,
                        attributesToHighlight: ['name']
                    }).then(function(r) { return { type: 'categories', hits: r.hits }; })
                );
            }

            // Pages
            var nPages = parseInt(config.autocomplete.nbOfPagesSuggestions) || 2;
            if (nPages > 0) {
                promises.push(
                    client.index(idx + '_pages').search(query, {
                        limit: nPages,
                        attributesToHighlight: ['name', 'content']
                    }).then(function(r) { return { type: 'pages', hits: r.hits }; })
                );
            }

            // Failsafe per-promise
            Promise.all(promises.map(function(p) {
                return p.catch(function(e) { console.warn('Meilisearch index error:', e.message); return null; });
            })).then(function(results) {
                results = results.filter(Boolean);
                renderAutocomplete(results, query);
            });
        }

        function renderAutocomplete(results, query) {
            var products = results.find(function(r) { return r.type === 'products'; });
            var sidebar = results.filter(function(r) { return r.type !== 'products'; })
                .sort(function(a, b) {
                    var order = { categories: 1, pages: 2, suggestions: 3 };
                    return (order[a.type] || 9) - (order[b.type] || 9);
                });

            var html = '<div class="meilisearch-autocomplete-wrapper">';

            // Left column
            if (sidebar.length) {
                html += '<div class="meilisearch-autocomplete-left-column">';
                sidebar.forEach(function(section) {
                    var label = config.translations[section.type] || section.type;
                    html += '<div class="meilisearch-autocomplete-section meilisearch-autocomplete-section-' + section.type + '">';
                    html += '<div class="meilisearch-autocomplete-section-title">' + esc(label) + '</div>';
                    html += '<div class="meilisearch-autocomplete-hits">';
                    if (!section.hits.length) {
                        html += '<div class="meilisearch-no-hits">No ' + esc(label).toLowerCase() + ' found for "' + esc(query) + '"</div>';
                        html += '</div></div>';
                        return;
                    }
                    section.hits.forEach(function(hit) {
                        var url = hit.url || '#';
                        html += '<div class="meilisearch-autocomplete-hit" data-type="' + section.type + '">';
                        if (section.type === 'categories') {
                            html += '<a href="' + url + '">';
                            html += '<span>' + highlight(hit, 'name') + '</span>';
                            if (hit.product_count) html += '<span class="badge">' + hit.product_count + '</span>';
                            html += '</a>';
                        } else {
                            html += '<a href="' + url + '">';
                            html += '<span class="page-title">' + highlight(hit, 'name') + '</span>';
                            if (hit._formatted && hit._formatted.content) {
                                html += '<span class="page-snippet">' + hit._formatted.content + '</span>';
                            }
                            html += '</a>';
                        }
                        html += '</div>';
                    });
                    html += '</div></div>';
                });
                html += '</div>';
            }

            // Right column — products
            html += '<div class="meilisearch-autocomplete-right-column">';
            html += '<div class="meilisearch-autocomplete-section meilisearch-autocomplete-section-products">';
            html += '<div class="meilisearch-autocomplete-section-title">' + esc(config.translations.products || 'Products') + '</div>';
            if (products && products.hits.length) {
                html += '<div class="meilisearch-autocomplete-hits">';
                products.hits.forEach(function(hit) {
                    var url = hit.url || config.baseUrl + '/catalog/product/view/id/' + hit.objectID;
                    var img = bestImage(hit);
                    var price = getPrice(hit);
                    html += '<div class="meilisearch-autocomplete-hit" data-type="products">';
                    html += '<a href="' + url + '" class="meilisearch-autocomplete-product">';
                    html += '<div class="meilisearch-autocomplete-product-image">';
                    if (img) html += '<img src="' + img + '" alt="' + esc(hit.name) + '" loading="lazy">';
                    html += '</div>';
                    html += '<div class="meilisearch-autocomplete-product-details">';
                    html += '<div class="meilisearch-autocomplete-product-name">' + highlight(hit, 'name') + '</div>';
                    if (price) html += '<div class="meilisearch-autocomplete-product-price">' + formatPrice(price) + '</div>';
                    html += '</div></a></div>';
                });
                html += '</div>';
            } else {
                html += '<div class="meilisearch-no-hits">No products found for "' + esc(query) + '"</div>';
            }
            html += '</div></div>';

            html += '</div>'; // wrapper
            html += '<div class="meilisearch-autocomplete-footer">';
            html += '<a href="' + config.baseUrl + '/catalogsearch/result/?q=' + encodeURIComponent(query) + '">';
            html += esc(config.translations.seeAll || 'See all products') + ' (' + esc(query) + ')</a></div>';

            dropdown.innerHTML = html;
            if (!isOpen) show();
        }

        // Events
        input.addEventListener('keyup', debounce(function(e) {
            if ([13, 27, 38, 40].indexOf(e.keyCode) !== -1) return;
            search(input.value);
        }, 300));

        input.addEventListener('focus', function() {
            if (input.value.length >= 2) search(input.value);
        });

        document.addEventListener('click', function(e) {
            if (!input.contains(e.target) && !dropdown.contains(e.target)) hide();
        });

        window.addEventListener('resize', function() { if (isOpen) position(); });

        // Keyboard nav
        input.addEventListener('keydown', function(e) {
            if (!isOpen) return;
            var hits = Array.from(dropdown.querySelectorAll('.meilisearch-autocomplete-hit'));
            var active = dropdown.querySelector('.meilisearch-autocomplete-hit.active');
            var idx = active ? hits.indexOf(active) : -1;

            if (e.keyCode === 38 || e.keyCode === 40) {
                e.preventDefault();
                if (active) active.classList.remove('active');
                if (e.keyCode === 38) idx = idx > 0 ? idx - 1 : hits.length - 1;
                else idx = idx < hits.length - 1 ? idx + 1 : 0;
                hits[idx].classList.add('active');
            } else if (e.keyCode === 13 && active) {
                e.preventDefault();
                var link = active.querySelector('a');
                if (link) window.location.href = link.href;
            } else if (e.keyCode === 27) {
                hide();
            }
        });
    }

    // ── Instant Search (full results page) ──────────────────────────

    function initInstantSearch() {
        if (!config.instant || !config.instant.enabled) return;
        if (!config.isSearchPage && !config.isCategoryPage) return;

        var container = document.querySelector(config.instant.selector);
        if (!container) return;

        var state = {
            query: '',
            page: 0,
            filters: {},
            sort: ''
        };

        // Parse URL
        var params = new URLSearchParams(window.location.search);
        if (params.has('q')) state.query = params.get('q');
        if (params.has('page')) state.page = parseInt(params.get('page')) - 1;
        if (params.has('index')) state.sort = params.get('index');
        (config.facets || []).forEach(function(f) {
            var vals = params.getAll('attribute:' + f.attribute);
            if (vals.length) state.filters[f.attribute] = vals;
        });

        // Build UI skeleton
        container.innerHTML = '<div class="meilisearch-instantsearch-container">' +
            '<div class="meilisearch-instantsearch-left"><div class="ms-facets"></div></div>' +
            '<div class="meilisearch-instantsearch-right">' +
                '<div class="ms-header"><div class="ms-stats"></div>' +
                '<div class="ms-sort"><select class="ms-sort-select">' +
                    '<option value="">' + esc(config.translations.relevance || 'Relevance') + '</option>' +
                    (config.sortingIndices || []).map(function(s) { return '<option value="' + s.name + '">' + esc(s.label) + '</option>'; }).join('') +
                '</select></div></div>' +
                '<div class="ms-refinements"></div>' +
                '<div class="ms-results"></div>' +
                '<div class="ms-pagination"></div>' +
            '</div></div>';

        var resultsEl = container.querySelector('.ms-results');
        var facetsEl = container.querySelector('.ms-facets');
        var statsEl = container.querySelector('.ms-stats');
        var paginationEl = container.querySelector('.ms-pagination');
        var refinementsEl = container.querySelector('.ms-refinements');

        // Sort select
        var sortSelect = container.querySelector('.ms-sort-select');
        if (state.sort) sortSelect.value = state.sort;
        sortSelect.addEventListener('change', function() {
            state.sort = sortSelect.value;
            state.page = 0;
            doSearch();
        });

        // Search input binding
        var searchInput = document.getElementById('search');
        if (searchInput) {
            searchInput.addEventListener('keyup', debounce(function() {
                state.query = searchInput.value;
                state.page = 0;
                doSearch();
            }, 300));
        }

        // Back/forward
        window.addEventListener('popstate', function() {
            var p = new URLSearchParams(window.location.search);
            state.query = p.get('q') || '';
            state.page = p.has('page') ? parseInt(p.get('page')) - 1 : 0;
            state.sort = p.get('index') || '';
            state.filters = {};
            doSearch();
        });

        function updateUrl() {
            var p = new URLSearchParams();
            if (state.query) p.set('q', state.query);
            if (state.page > 0) p.set('page', state.page + 1);
            if (state.sort) p.set('index', state.sort);
            Object.keys(state.filters).forEach(function(attr) {
                state.filters[attr].forEach(function(v) { p.append('attribute:' + attr, v); });
            });
            history.pushState({}, '', location.pathname + '?' + p.toString());
        }

        function doSearch() {
            resultsEl.innerHTML = '<div class="meilisearch-loading">Loading...</div>';

            var searchParams = {
                limit: config.hitsPerPage || 10,
                offset: state.page * (config.hitsPerPage || 10),
                facets: (config.facets || []).map(function(f) { return f.attribute; }),
                attributesToHighlight: ['name']
            };

            // Build filters
            var filters = [];
            Object.keys(state.filters).forEach(function(attr) {
                var vals = state.filters[attr];
                if (vals.length) {
                    var parts = vals.map(function(v) { return attr + ' = "' + v + '"'; });
                    filters.push(vals.length > 1 ? '(' + parts.join(' OR ') + ')' : parts[0]);
                }
            });
            if (config.isCategoryPage && config.request.path) {
                filters.push('categories.level' + config.request.level + ' = "' + config.request.path + '"');
            }
            if (filters.length) searchParams.filter = filters.join(' AND ');

            var indexName = state.sort || (config.indexName + '_products');
            client.index(indexName).search(state.query, searchParams).then(function(res) {
                renderResults(res);
                renderFacets(res);
                renderStats(res);
                renderPagination(res);
                renderRefinements();
                updateUrl();
            }).catch(function(err) {
                console.error('Meilisearch error:', err);
                resultsEl.innerHTML = '<div class="meilisearch-error">Search error. Please try again.</div>';
            });
        }

        function renderResults(res) {
            if (!res.hits || !res.hits.length) {
                resultsEl.innerHTML = '<div class="meilisearch-no-results"><p>' +
                    esc(config.translations.noProducts || 'No products for query') +
                    ' "' + esc(state.query) + '"</p></div>';
                return;
            }
            var html = '<div class="meilisearch-instantsearch-hits">';
            res.hits.forEach(function(hit) {
                var img = bestImage(hit);
                var price = getPrice(hit);
                var url = hit.url || config.baseUrl + '/catalog/product/view/id/' + hit.objectID;
                html += '<div class="meilisearch-instantsearch-hit">';
                html += '<div class="hit-image"><a href="' + url + '">';
                if (img) html += '<img src="' + img + '" alt="' + esc(hit.name) + '" loading="lazy">';
                html += '</a></div>';
                html += '<div class="hit-content">';
                html += '<h3 class="hit-name"><a href="' + url + '">' + highlight(hit, 'name') + '</a></h3>';
                if (price) html += '<div class="hit-price">' + formatPrice(price) + '</div>';
                html += '</div></div>';
            });
            html += '</div>';
            resultsEl.innerHTML = html;
        }

        function renderFacets(res) {
            if (!res.facetDistribution) return;
            var html = '<div class="block-title"><strong>' + esc(config.translations.refine || 'Refine') + '</strong></div>';
            (config.facets || []).forEach(function(fc) {
                var data = res.facetDistribution[fc.attribute];
                if (!data || !Object.keys(data).length) return;
                var selected = state.filters[fc.attribute] || [];
                var entries = Object.entries(data).sort(function(a, b) { return b[1] - a[1]; });
                var shown = entries.slice(0, config.maxValuesPerFacet || 10);

                html += '<div class="meilisearch-facet" data-attribute="' + fc.attribute + '">';
                html += '<div class="facet-title expanded">' + esc(fc.label) + '</div>';
                html += '<div class="facet-content">';
                shown.forEach(function(entry) {
                    var val = entry[0], count = entry[1];
                    var checked = selected.indexOf(val) > -1;
                    html += '<div class="facet-value' + (checked ? ' selected' : '') + '" data-value="' + esc(val) + '">';
                    html += '<input type="checkbox"' + (checked ? ' checked' : '') + '> ';
                    html += '<label><span class="facet-value-name">' + esc(val) + '</span>';
                    html += ' <span class="facet-value-count">(' + count + ')</span></label></div>';
                });
                html += '</div></div>';
            });
            facetsEl.innerHTML = html;

            // Facet click events
            facetsEl.querySelectorAll('.facet-value').forEach(function(el) {
                el.addEventListener('click', function(e) {
                    e.preventDefault();
                    var attr = el.closest('.meilisearch-facet').dataset.attribute;
                    var val = el.dataset.value;
                    if (!state.filters[attr]) state.filters[attr] = [];
                    var i = state.filters[attr].indexOf(val);
                    if (i > -1) { state.filters[attr].splice(i, 1); if (!state.filters[attr].length) delete state.filters[attr]; }
                    else state.filters[attr].push(val);
                    state.page = 0;
                    doSearch();
                });
            });

            // Facet collapse
            facetsEl.querySelectorAll('.facet-title').forEach(function(el) {
                el.addEventListener('click', function() {
                    el.classList.toggle('expanded');
                    var content = el.nextElementSibling;
                    content.style.display = content.style.display === 'none' ? '' : 'none';
                });
            });
        }

        function renderStats(res) {
            var start = state.page * (config.hitsPerPage || 10) + 1;
            var end = Math.min(start + (config.hitsPerPage || 10) - 1, res.estimatedTotalHits);
            var html = 'Showing ' + start + '-' + end + ' of ' + res.estimatedTotalHits + ' results';
            if (state.query) html += ' for "<strong>' + esc(state.query) + '</strong>"';
            statsEl.innerHTML = html;
        }

        function renderPagination(res) {
            var total = Math.ceil(res.estimatedTotalHits / (config.hitsPerPage || 10));
            if (total <= 1) { paginationEl.innerHTML = ''; return; }
            var html = '<div class="pages"><ol>';
            if (state.page > 0) html += '<li><a href="#" data-page="' + (state.page - 1) + '">&laquo; Prev</a></li>';
            for (var i = 0; i < total; i++) {
                if (i === 0 || i === total - 1 || (i >= state.page - 2 && i <= state.page + 2)) {
                    html += i === state.page ? '<li class="current">' + (i + 1) + '</li>' :
                        '<li><a href="#" data-page="' + i + '">' + (i + 1) + '</a></li>';
                } else if (i === state.page - 3 || i === state.page + 3) {
                    html += '<li>...</li>';
                }
            }
            if (state.page < total - 1) html += '<li><a href="#" data-page="' + (state.page + 1) + '">Next &raquo;</a></li>';
            html += '</ol></div>';
            paginationEl.innerHTML = html;

            paginationEl.querySelectorAll('a').forEach(function(a) {
                a.addEventListener('click', function(e) {
                    e.preventDefault();
                    state.page = parseInt(a.dataset.page);
                    doSearch();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
            });
        }

        function renderRefinements() {
            var items = [];
            Object.keys(state.filters).forEach(function(attr) {
                var fc = (config.facets || []).find(function(f) { return f.attribute === attr; });
                state.filters[attr].forEach(function(val) {
                    items.push({ attribute: attr, label: fc ? fc.label : attr, value: val });
                });
            });
            if (!items.length) { refinementsEl.innerHTML = ''; return; }
            var html = '<div class="current-refinements"><span class="label">' +
                esc(config.translations.selectedFilters || 'Selected Filters') + ':</span> ';
            items.forEach(function(r) {
                html += '<span class="refinement" data-attribute="' + r.attribute + '" data-value="' + esc(r.value) + '">' +
                    esc(r.label) + ': ' + esc(r.value) + ' <span class="remove">&times;</span></span> ';
            });
            html += '<a href="#" class="clear-all">' + esc(config.translations.clearAll || 'Clear all') + '</a></div>';
            refinementsEl.innerHTML = html;

            refinementsEl.querySelectorAll('.refinement .remove').forEach(function(el) {
                el.addEventListener('click', function() {
                    var r = el.parentElement;
                    var attr = r.dataset.attribute, val = r.dataset.value;
                    if (state.filters[attr]) {
                        var i = state.filters[attr].indexOf(val);
                        if (i > -1) state.filters[attr].splice(i, 1);
                        if (!state.filters[attr].length) delete state.filters[attr];
                    }
                    state.page = 0;
                    doSearch();
                });
            });

            var clearAll = refinementsEl.querySelector('.clear-all');
            if (clearAll) clearAll.addEventListener('click', function(e) {
                e.preventDefault();
                state.filters = {};
                state.page = 0;
                doSearch();
            });
        }

        // Initial search
        doSearch();
    }

    // ── Init ────────────────────────────────────────────────────────

    function init() {
        config = window.meilisearchConfig;
        if (!config) return;

        client = createClient();
        if (!client) return;

        initAutocomplete();
        initInstantSearch();
    }

    // Wait for DOM + config
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        // Small delay to ensure config script has run
        setTimeout(init, 10);
    }

})();
