(function ($) {
    'use strict';

    var publishModalHtml =
        '<div id="scmp-publish-modal" class="scmp-modal-overlay" style="display:none;">' +
            '<div class="scmp-modal-container scmp-publish-container">' +
                '<div class="scmp-modal-header">' +
                    '<div class="scmp-modal-header-content">' +
                        '<h2>Push to SuperVault</h2>' +
                        '<p class="scmp-modal-subtitle">Publish this element to the central Supercraft library.</p>' +
                    '</div>' +
                    '<button type="button" class="scmp-modal-close scmp-publish-close">&times;</button>' +
                '</div>' +
                '<div class="scmp-modal-body">' +
                    '<form id="scmp-publish-form">' +
                        '<div class="scmp-publish-col">' +
                            '<div class="scmp-publish-field">' +
                                '<label for="scmp-publish-title">Title</label>' +
                                '<input type="text" id="scmp-publish-title" class="scmp-publish-input" placeholder="Element title" required />' +
                            '</div>' +
                            '<div class="scmp-publish-field">' +
                                '<label for="scmp-publish-image">Preview Image</label>' +
                                '<div class="scmp-publish-image-row">' +
                                    '<input type="text" id="scmp-publish-image" class="scmp-publish-input" placeholder="Select or enter image URL" />' +
                                    '<button type="button" id="scmp-publish-image-btn" class="scmp-publish-image-btn">Select</button>' +
                                '</div>' +
                                '<div id="scmp-publish-image-preview" class="scmp-publish-image-preview" style="display:none;"></div>' +
                            '</div>' +
                        '</div>' +
                        '<div class="scmp-publish-col">' +
                            '<div class="scmp-publish-field">' +
                                '<label>Categories</label>' +
                                '<div id="scmp-publish-categories-list" class="scmp-publish-categories-list"></div>' +
                                '<input type="text" id="scmp-publish-new-category" class="scmp-publish-input" placeholder="Or create new category" />' +
                            '</div>' +
                            '<div class="scmp-publish-field">' +
                                '<label>Tags</label>' +
                                '<div id="scmp-publish-tags-list" class="scmp-publish-checkbox-list"></div>' +
                                '<input type="text" id="scmp-publish-new-tags" class="scmp-publish-input" placeholder="Or create new tags (comma-separated)" />' +
                            '</div>' +
                            '<div class="scmp-publish-field">' +
                                '<label>Requirements</label>' +
                                '<div id="scmp-publish-reqs-list" class="scmp-publish-checkbox-list"></div>' +
                                '<input type="text" id="scmp-publish-new-reqs" class="scmp-publish-input" placeholder="Or create new requirements (comma-separated)" />' +
                            '</div>' +
                        '</div>' +
                        '<div id="scmp-publish-message" class="scmp-publish-message" style="display:none; grid-column: span 2;"></div>' +
                    '</form>' +
                '</div>' +
                '<div class="scmp-modal-footer scmp-publish-footer">' +
                    '<button type="button" id="scmp-publish-submit" class="scmp-publish-submit">Publish to SuperVault</button>' +
                '</div>' +
            '</div>' +
        '</div>';

    var modalHtml =
        '<div id="scmp-supervault-modal" class="scmp-modal-overlay" style="display:none;">' +
            '<div class="scmp-modal-container">' +
                '<div class="scmp-modal-header">' +
                    '<div class="scmp-modal-header-content">' +
                        '<h2>Supervault</h2>' +
                        '<p class="scmp-modal-subtitle">Your central library of reusable sections, patterns and layouts for Supercraft projects.</p>' +
                    '</div>' +
                    '<button type="button" class="scmp-modal-close">&times;</button>' +
                '</div>' +
                '<div class="scmp-modal-body">' +
                    '<div class="scmp-modal-toolbar">' +
                        '<input type="text" id="scmp-search-input" class="scmp-search-input" placeholder="Search components..." />' +
                        '<button type="button" id="scmp-refresh-library" class="scmp-refresh-btn" title="Refresh library cache">' +
                            '<span class="eicon-sync"></span>' +
                        '</button>' +
                    '</div>' +
                    '<div class="scmp-filter-rows">' +
                        '<div class="scmp-filter-row">' +
                            '<span class="scmp-filter-label">Filter by Category:</span>' +
                            '<div id="scmp-categories" class="scmp-inline-filters"></div>' +
                        '</div>' +
                        '<div class="scmp-filter-row">' +
                            '<span class="scmp-filter-label">Filter by Tag:</span>' +
                            '<div id="scmp-tags" class="scmp-inline-filters"></div>' +
                        '</div>' +
                    '</div>' +
                    '<div id="scmp-component-grid" class="scmp-component-grid"></div>' +
                '</div>' +
                '<div class="scmp-modal-footer">' +
                    '<span class="scmp-status-text">Loaded from SuperVault</span>' +
                '</div>' +
            '</div>' +
        '</div>';

    var previewHtml =
        '<div id="scmp-preview-overlay" class="scmp-preview-overlay" style="display:none;">' +
            '<div class="scmp-preview-wrapper">' +
                '<div class="scmp-preview-header">' +
                    '<h3 id="scmp-preview-title">Preview</h3>' +
                    '<button type="button" class="scmp-preview-close">&times;</button>' +
                '</div>' +
                '<iframe id="scmp-preview-iframe" class="scmp-preview-iframe" src="about:blank"></iframe>' +
            '</div>' +
        '</div>';

    var successHtml =
        '<div id="scmp-copy-success" class="scmp-copy-success" style="display:none;">' +
            '<span class="scmp-copy-check">&#10003;</span> Copied to clipboard!' +
        '</div>';

    var state = {
        currentCategory: 'all',
        currentTag: '',
        searchTerm: '',
        allComponents: [],
        categories: [],
        page: 1,
        pageSize: 12,
        loadingMore: false,
        pendingElementJson: '',
        pendingView: null
    };

    function init() {
        injectButton();
        $(document.body).append(modalHtml + previewHtml + successHtml + publishModalHtml);
        bindEvents();
        initContextMenu();
    }

    function injectButton() {
        if (!scmp.is_validated) {
            return;
        }
        if ($('#scmp-vault-fab').length) {
            return;
        }

        var $fab = $(
            '<button type="button" id="scmp-vault-fab" class="scmp-vault-fab" title="SuperVault Library">' +
                '<span class="scmp-vault-fab-text">S</span>' +
            '</button>'
        );

        $(document.body).append($fab);

        $fab.on('click', openModal);
    }

    function bindEvents() {
        $(document).on('click', '.scmp-modal-close', closeModal);

        $(document).on('click', '.scmp-preview-close', closePreview);

        var searchTimer;
        $(document).on('input', '#scmp-search-input', function () {
            clearTimeout(searchTimer);
            state.searchTerm = $(this).val();
            state.currentTag = '';
            state.page = 1;
            searchTimer = setTimeout(loadComponents, 300);
        });

        $(document).on('click', '.scmp-cat-tab', function () {
            state.currentCategory = $(this).data('cat');
            state.currentTag = '';
            state.page = 1;
            $('.scmp-cat-tab').removeClass('active');
            $(this).addClass('active');
            loadComponents();
        });

        $(document).on('click', '.scmp-tag-btn', function () {
            var tag = $(this).data('tag');
            state.currentTag = state.currentTag === tag ? '' : tag;
            state.page = 1;
            $('.scmp-tag-btn').removeClass('active');
            if (state.currentTag) {
                $(this).addClass('active');
            }
            renderComponents();
        });

        $(document).on('click', '.scmp-card-preview', function () {
            var previewUrl = $(this).data('preview-url');
            if (previewUrl) {
                openPreview(previewUrl);
            }
        });

        $(document).on('click', '.scmp-card-copy', function () {
            var $btn = $(this);
            var $card = $btn.closest('.scmp-component-card');
            var id = $card.data('id');
            if (id) {
                copyComponent(id, $card);
            }
        });

        $(document).on('click', '#scmp-preview-overlay', function (e) {
            if ($(e.target).is('#scmp-preview-overlay')) {
                closePreview();
            }
        });

        $(document).on('click', '#scmp-supervault-modal', function (e) {
            if ($(e.target).is('#scmp-supervault-modal')) {
                closeModal();
            }
        });

        $(document).on('click', '#scmp-refresh-library', function () {
            var $btn = $(this);
            if ($btn.hasClass('loading')) {
                return;
            }
            $btn.addClass('loading');
            loadCategories(true);
            loadComponents();
            setTimeout(function () {
                $btn.removeClass('loading');
            }, 600);
        });

        // ── Publish Modal Events ──
        $(document).on('click', '.scmp-publish-close', closePublishForm);

        $(document).on('click', '#scmp-publish-modal', function (e) {
            if ($(e.target).is('#scmp-publish-modal')) {
                closePublishForm();
            }
        });

        $(document).on('click', '#scmp-publish-submit', submitPublishForm);

        $(document).on('keydown', '#scmp-publish-form input', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                submitPublishForm();
            }
        });

        $(document).on('click', '#scmp-publish-image-btn', function (e) {
            e.preventDefault();
            if (typeof wp === 'undefined' || !wp.media) {
                alert('WordPress Media Library is not available.');
                return;
            }
            var frame = wp.media({
                title: 'Select Preview Image',
                button: {
                    text: 'Use this image'
                },
                multiple: false
            });
            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();
                $('#scmp-publish-image').val(attachment.url);
                $('#scmp-publish-image-preview').html('<img src="' + attachment.url + '" />').show();
            });
            frame.open();
        });

        $(document).on('input change', '#scmp-publish-image', function () {
            var url = $(this).val().trim();
            if (url) {
                $('#scmp-publish-image-preview').html('<img src="' + url + '" />').show();
            } else {
                $('#scmp-publish-image-preview').hide().empty();
            }
        });
    }

    function openModal() {
        $('#scmp-supervault-modal').fadeIn(200);
        $('body').css('overflow', 'hidden');
        loadCategories();
        loadComponents();

        // Bind scroll event directly to the container since 'scroll' does not bubble
        $('.scmp-modal-body').off('scroll').on('scroll', function () {
            var $body = $(this);
            if ($body.scrollTop() + $body.innerHeight() >= $body[0].scrollHeight - 100) {
                if (state.loadingMore) {
                    return;
                }
                var total = getFilteredComponents().length;
                if (state.page * state.pageSize < total) {
                    state.loadingMore = true;
                    // Append spinner dynamically to the bottom of the grid
                    var $loader = $('<div class="scmp-infinite-loader"><div class="scmp-spinner"></div></div>');
                    $('#scmp-component-grid').append($loader);

                    setTimeout(function () {
                        state.page++;
                        renderComponents();
                        state.loadingMore = false;
                    }, 400); // 400ms loading animation delay
                }
            }
        });
    }

    function closeModal() {
        $('#scmp-supervault-modal').fadeOut(200);
        $('body').css('overflow', '');
    }

    function openPreview(url) {
        // Fix HTML entity encoding issues (like &#038; instead of &) in preview URL
        var previewUrl = url.replace(/&#038;/g, '&');
        previewUrl = previewUrl + (previewUrl.indexOf('?') === -1 ? '?' : '&') + 'supervault_preview=1';
        
        $('#scmp-preview-iframe').attr('src', previewUrl);
        $('#scmp-preview-title').text('Preview');
        $('#scmp-preview-overlay').fadeIn(200);
    }

    function closePreview() {
        $('#scmp-preview-overlay').fadeOut(200);
        setTimeout(function () {
            $('#scmp-preview-iframe').attr('src', 'about:blank');
        }, 300);
    }

    function proxyRequest(actionType, data, callback, failCallback) {
        var payload = $.extend({
            action: 'scmp_supervault_proxy',
            nonce: scmp.nonce,
            action_type: actionType
        }, data);

        $.post(scmp.ajax_url, payload, function (res) {
            if (res.success) {
                callback(res.data);
            } else {
                console.error('SuperVault proxy error:', res.data.message);
                if (typeof failCallback === 'function') {
                    failCallback(res.data.message || 'Unknown error');
                }
            }
        }).fail(function () {
            console.error('SuperVault proxy request failed.');
            if (typeof failCallback === 'function') {
                failCallback('Request failed. Check connection.');
            }
        });
    }

    function loadCategories(forceRefresh) {
        var params = {};
        if (forceRefresh) {
            params.force_refresh = 'true';
        }
        proxyRequest('categories', params, function (data) {
            state.categories = data;
            var html = '';
            if (Array.isArray(data)) {
                html += '<button type="button" class="scmp-cat-tab active" data-cat="all">All</button>';
                data.forEach(function (cat) {
                    var name = typeof cat === 'string' ? cat : (cat.name || cat.slug || cat);
                    var slug = typeof cat === 'string' ? cat : (cat.slug || cat.name || cat);
                    html += '<button type="button" class="scmp-cat-tab" data-cat="' + slug + '">' + name + '</button>';
                });
            }
            $('#scmp-categories').html(html);
        });
    }

    function loadComponents() {
        var data = {};
        if (state.currentCategory && state.currentCategory !== 'all') {
            data.category = state.currentCategory;
        }
        if (state.searchTerm) {
            data.search = state.searchTerm;
        }

        var $grid = $('#scmp-component-grid');
        $grid.addClass('loading');

        proxyRequest('components', data, function (components) {
            $grid.removeClass('loading');
            state.allComponents = Array.isArray(components) ? components : [];
            state.page = 1;
            renderTags();
            renderComponents();
        }, function (errorMsg) {
            $grid.removeClass('loading');
            $grid.html('<div class="scmp-empty-state">Error: ' + errorMsg + '</div>');
        });
    }

    function renderTags() {
        var tags = [];
        state.allComponents.forEach(function (comp) {
            if (comp.tags && Array.isArray(comp.tags)) {
                comp.tags.forEach(function (tag) {
                    if (tags.indexOf(tag) === -1) {
                        tags.push(tag);
                    }
                });
            }
        });

        var html = '';
        tags.forEach(function (tag) {
            var activeClass = state.currentTag === tag ? ' active' : '';
            html += '<button type="button" class="scmp-tag-btn' + activeClass + '" data-tag="' + tag + '">' + tag + '</button>';
        });
        $('#scmp-tags').html(html);
    }

    function getFilteredComponents() {
        var components = state.allComponents;
        if (state.currentTag) {
            components = components.filter(function (comp) {
                return comp.tags && comp.tags.indexOf(state.currentTag) !== -1;
            });
        }
        return components;
    }

    function renderComponents() {
        var components = getFilteredComponents();
        var $grid = $('#scmp-component-grid');
        
        if (!components.length) {
            $grid.html('<div class="scmp-empty-state">No components found.</div>');
            return;
        }

        // Apply client-side pagination (slice up to current page's elements)
        var slicedComponents = components.slice(0, state.page * state.pageSize);

        var html = '';
        slicedComponents.forEach(function (comp) {
            var title = comp.title || comp.name || 'Untitled';
            
            // Build the subtitle containing categories only
            var catInfo = [];
            if (comp.categories && Array.isArray(comp.categories)) {
                comp.categories.forEach(function (cat) {
                    catInfo.push(cat.replace('-', ' '));
                });
            }
            var categoryText = catInfo.join(', ');

            // Build the requirements HTML (separate pill labels)
            var requirementsHtml = '';
            if (comp.requirements && Array.isArray(comp.requirements) && comp.requirements.length) {
                requirementsHtml += '<div class="scmp-card-requirements-wrap">';
                requirementsHtml += '<span class="scmp-card-req-label">Requires:</span> ';
                comp.requirements.forEach(function (req) {
                    requirementsHtml += '<span class="scmp-card-req-pill">' + req + '</span>';
                });
                requirementsHtml += '</div>';
            }
 
            var thumbnail = comp.thumbnail || comp.image || '';
            var previewUrl = comp.preview_url || comp.url || '';
            var id = comp.id || '';
            var tags = comp.tags || [];
            var firstTag = tags.length ? tags[0] : '';
 
            html += '<div class="scmp-component-card" data-id="' + id + '" data-preview-url="' + previewUrl + '">';
            html += '<div class="scmp-card-image-wrap">';
            if (firstTag) {
                html += '<span class="scmp-card-badge">' + firstTag + '</span>';
            }
            if (thumbnail) {
                html += '<div class="scmp-card-thumb" style="background-image:url(' + thumbnail + ');"></div>';
            } else {
                html += '<div class="scmp-card-thumb scmp-card-thumb-placeholder"><span class="eicon-device-desktop"></span></div>';
            }
            html += '<div class="scmp-card-overlay">';
            if (previewUrl) {
                html += '<button type="button" class="scmp-card-btn scmp-card-preview" data-action="preview" data-preview-url="' + previewUrl + '">Preview</button>';
            }
            html += '<button type="button" class="scmp-card-btn scmp-card-copy" data-action="copy" data-id="' + id + '">Copy</button>';
            html += '</div>';
            html += '</div>';
            html += '<div class="scmp-card-info">';
            html += '<span class="scmp-card-title">' + title + '</span>';
            if (categoryText) {
                html += '<span class="scmp-card-category">' + categoryText + '</span>';
            }
            if (requirementsHtml) {
                html += requirementsHtml;
            }
            html += '</div>';
            html += '</div>';
        });
        $grid.html(html);
    }

    function copyComponent(id, $card) {
        $card.addClass('scmp-copying');

        proxyRequest('json', { id: id }, function (data) {
            var jsonStr = typeof data === 'object' ? JSON.stringify(data) : data;

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(jsonStr).then(function () {
                    showCopySuccess($card);
                }).catch(function () {
                    fallbackCopy(jsonStr, $card);
                });
            } else {
                fallbackCopy(jsonStr, $card);
            }
        });
    }

    function fallbackCopy(text, $card) {
        var $textarea = $('<textarea style="position:fixed;left:-9999px;top:-9999px;">');
        $('body').append($textarea);
        $textarea.val(text).select();
        try {
            document.execCommand('copy');
            showCopySuccess($card);
        } catch (e) {
            console.error('Copy failed:', e);
        }
        $textarea.remove();
    }

    function showCopySuccess($card) {
        $card.removeClass('scmp-copying').addClass('scmp-copied');
        var $success = $('#scmp-copy-success');
        var offset = $card.offset();
        $success.css({
            top: (offset.top - 10) + 'px',
            left: (offset.left + $card.outerWidth() / 2 - $success.outerWidth() / 2) + 'px'
        }).fadeIn(200);

        setTimeout(function () {
            $success.fadeOut(300);
            $card.removeClass('scmp-copied');
        }, 2000);
    }

    // ── Context Menu: Push to SuperVault ─────────────────────────────────

    function initContextMenu() {
        if (typeof elementor === 'undefined' || !elementor.hooks) {
            setTimeout(initContextMenu, 500);
            return;
        }

        ['section', 'container', 'column', 'widget'].forEach(function (elementType) {
            elementor.hooks.addFilter('elements/' + elementType + '/contextMenuGroups', function (groups, view) {
                groups.push({
                    name: 'supervault',
                    actions: [{
                        name: 'push-to-supervault',
                        title: 'Push to SuperVault',
                        icon: 'eicon-arrow-up',
                        callback: function () {
                            openPublishForm(view);
                        }
                    }]
                });
                return groups;
            });
        });
    }

    // ── Publish Form ─────────────────────────────────────────────────────

    function openPublishForm(view) {
        var model = null;
        if (view && view.model) {
            model = view.model;
            state.pendingView = view;
        } else if (typeof elementor !== 'undefined' && elementor.selection) {
            var selected = elementor.selection.getElements();
            if (selected && selected.length > 0) {
                var container = selected[0];
                model = container.model;
                state.pendingView = container;
            }
        }

        if (!model) {
            console.error('SuperVault: No element model found.');
            return;
        }

        var elementJson = model.toJSON();
        state.pendingElementJson = JSON.stringify(elementJson);

        var title = '';
        if (model.getTitle) {
            title = model.getTitle();
        }
        if (!title && elementJson.widgetType) {
            title = elementJson.widgetType;
        }
        if (!title && elementJson.elType) {
            title = elementJson.elType;
        }
        if (!title) {
            title = 'Untitled';
        }

        $('#scmp-publish-title').val(title);
        $('#scmp-publish-message').removeClass('success error').empty().hide();
        $('#scmp-publish-submit').prop('disabled', false).removeClass('loading');
        $('#scmp-publish-form')[0].reset();
        $('#scmp-publish-title').val(title);
        $('#scmp-publish-image-preview').hide().empty();

        $('#scmp-publish-modal').fadeIn(200);
        $('body').css('overflow', 'hidden');

        loadPublishCategories();
        loadPublishTags();
        loadPublishRequirements();
    }

    function closePublishForm() {
        $('#scmp-publish-modal').fadeOut(200);
        $('body').css('overflow', '');
    }

    function loadPublishCategories() {
        proxyRequest('categories', {}, function (data) {
            state.categories = data;
            var $list = $('#scmp-publish-categories-list');
            $list.empty();
            if (Array.isArray(data)) {
                data.forEach(function (cat) {
                    var name = typeof cat === 'string' ? cat : (cat.name || cat.slug || cat);
                    var slug = typeof cat === 'string' ? cat : (cat.slug || cat.name || cat);
                    $list.append(
                        '<label class="scmp-publish-category-item">' +
                            '<input type="checkbox" class="scmp-publish-category-cb" value="' + slug + '" /> ' + name +
                        '</label>'
                    );
                });
            }
        });
    }

    function loadPublishTags() {
        var $list = $('#scmp-publish-tags-list');
        $list.html('<span class="scmp-publish-loading">Loading...</span>');
        proxyRequest('tags', {}, function (data) {
            $list.empty();
            if (Array.isArray(data)) {
                data.forEach(function (tag) {
                    var name = typeof tag === 'string' ? tag : (tag.name || tag.slug || tag);
                    var val = typeof tag === 'string' ? tag : (tag.slug || tag.name || tag);
                    $list.append(
                        '<label class="scmp-publish-checkbox-item">' +
                            '<input type="checkbox" class="scmp-publish-checkbox-cb" value="' + val + '" /> ' + name +
                        '</label>'
                    );
                });
            }
        });
    }

    function loadPublishRequirements() {
        var $list = $('#scmp-publish-reqs-list');
        $list.html('<span class="scmp-publish-loading">Loading...</span>');
        proxyRequest('requirements', {}, function (data) {
            $list.empty();
            if (Array.isArray(data)) {
                data.forEach(function (req) {
                    var name = typeof req === 'string' ? req : (req.name || req.slug || req);
                    var val = typeof req === 'string' ? req : (req.slug || req.name || req);
                    $list.append(
                        '<label class="scmp-publish-checkbox-item">' +
                            '<input type="checkbox" class="scmp-publish-checkbox-cb" value="' + val + '" /> ' + name +
                        '</label>'
                    );
                });
            }
        });
    }

    function submitPublishForm() {
        var title = $('#scmp-publish-title').val().trim();

        if (!title) {
            $('#scmp-publish-message').addClass('error').text('Please enter a title.').show();
            return;
        }

        var categories = [];
        $('#scmp-publish-categories-list .scmp-publish-category-cb:checked').each(function () {
            categories.push($(this).val());
        });
        var newCategory = $('#scmp-publish-new-category').val().trim();
        if (newCategory) {
            categories.push(newCategory);
        }

        var tags = [];
        $('#scmp-publish-tags-list .scmp-publish-checkbox-cb:checked').each(function () {
            tags.push($(this).val());
        });
        var newTags = $('#scmp-publish-new-tags').val().trim();
        if (newTags) {
            newTags.split(',').forEach(function (t) {
                var trimmed = t.trim();
                if (trimmed) tags.push(trimmed);
            });
        }

        var reqs = [];
        $('#scmp-publish-reqs-list .scmp-publish-checkbox-cb:checked').each(function () {
            reqs.push($(this).val());
        });
        var newReqs = $('#scmp-publish-new-reqs').val().trim();
        if (newReqs) {
            newReqs.split(',').forEach(function (r) {
                var trimmed = r.trim();
                if (trimmed) reqs.push(trimmed);
            });
        }

        var previewImage = $('#scmp-publish-image').val().trim();

        var payload = {
            action: 'scmp_push_to_supervault',
            nonce: scmp.nonce,
            title: title,
            preview_image: previewImage,
            categories: categories,
            tags: tags,
            requirements: reqs,
            elements_json: state.pendingElementJson
        };

        $('#scmp-publish-submit').prop('disabled', true).addClass('loading');
        $('#scmp-publish-message').removeClass('success error').empty().hide();

        $.post(scmp.ajax_url, payload, function (res) {
            if (res.success) {
                $('#scmp-publish-message').addClass('success').text('Pushed to SuperVault successfully!').show();
                setTimeout(closePublishForm, 2000);
            } else {
                $('#scmp-publish-message').addClass('error').text(res.data.message || 'Push failed.').show();
                $('#scmp-publish-submit').prop('disabled', false).removeClass('loading');
            }
        }).fail(function () {
            $('#scmp-publish-message').addClass('error').text('Request failed. Check your connection.').show();
            $('#scmp-publish-submit').prop('disabled', false).removeClass('loading');
        });
    }

    $(document).ready(init);

})(jQuery);
