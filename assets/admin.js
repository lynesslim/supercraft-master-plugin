(function ($) {
    'use strict';

    // ── Step 1: License Verification ──────────────────────────────────

    var $form = $('#scmp-license-form');
    if ($form.length) {
        $form.on('submit', function (e) {
            e.preventDefault();

            var key = $('#scmp-license-key').val();
            var $msg = $('#scmp-license-message');
            var $btn = $form.find('button');

            $btn.prop('disabled', true).text('Verifying...');
            $msg.hide().removeClass('success error');

            $.post(scmp.ajax_url, {
                action: 'scmp_verify_license',
                nonce: scmp.nonce,
                license_key: key
            }).done(function (res) {
                if (res.success) {
                    $msg.addClass('success').text(res.data.message).show();
                    setTimeout(function () {
                        window.location.href = 'admin.php?page=supercraft-onboarding&step=2';
                    }, 1200);
                } else {
                    $msg.addClass('error').text(res.data.message).show();
                    $btn.prop('disabled', false).text('Verify & Next');
                }
            }).fail(function () {
                $msg.addClass('error').text('Request failed. Please try again.').show();
                $btn.prop('disabled', false).text('Verify & Next');
            });
        });
    }

    // ── Step 2: Plugin Selection ──────────────────────────────────────

    var $selection = $('#scmp-plugin-selection');
    if ($selection.length) {
        var html = '';

        function buildCategory(label, plugins) {
            html += '<div class="scmp-plugin-category"><h3>' + label + '</h3>';
            plugins.forEach(function (p) {
                var disabled = p.installed ? ' disabled checked' : ' checked';
                var badge = p.installed ? ' <span class="scmp-installed-badge" style="color: #43a047; font-size: 12px; margin-left: 8px;">(Already Installed)</span>' : '';
                html += '<label class="scmp-plugin-checkbox" style="' + (p.installed ? 'opacity: 0.7; cursor: not-allowed;' : '') + '">';
                html += '<input type="checkbox" value="' + p.slug + '"' + disabled + '> ' + p.name + badge;
                html += '</label>';
            });
            html += '</div>';
        }

        if (scmp.plugin_data.premium && scmp.plugin_data.premium.length) {
            buildCategory('Supercraft Premium Plugins', scmp.plugin_data.premium);
        }
        if (scmp.plugin_data.standard && scmp.plugin_data.standard.length) {
            buildCategory('Standard Companion Plugins', scmp.plugin_data.standard);
        }

        $selection.html(html);

        $('#scmp-install-selected').on('click', function () {
            var selected = [];
            $selection.find('input[type=checkbox]:checked').each(function () {
                selected.push($(this).val());
            });
            if (!selected.length) {
                alert('Please select at least one plugin to install.');
                return;
            }
            localStorage.setItem('scmp_selected_plugins', JSON.stringify(selected));
            window.location.href = 'admin.php?page=supercraft-onboarding&step=3';
        });
    }

    // ── Step 3: Plugin Installation ───────────────────────────────────

    var pluginQueue = [];
    var pluginIndex = 0;
    var $list = $('#scmp-plugin-list');
    var $progressBar = $('#scmp-progress-bar');
    var $progressText = $('#scmp-progress-text');
    var $complete = $('#scmp-install-complete');

    function addPluginItem(slug, name) {
        $list.append('<li id="plugin-' + slug + '"><span class="dashicons dashicons-minus"></span> ' + name + '</li>');
    }

    function getPluginName(slug) {
        var categories = scmp.plugin_data.premium || [];
        for (var i = 0; i < categories.length; i++) {
            if (categories[i].slug === slug) return categories[i].name;
        }
        categories = scmp.plugin_data.standard || [];
        for (var i = 0; i < categories.length; i++) {
            if (categories[i].slug === slug) return categories[i].name;
        }
        return slug;
    }

    function setPluginStatus(slug, status, msg) {
        var $li = $('#plugin-' + slug);
        $li.removeClass('done fail loading').addClass(status);
        var icon = status === 'done' ? 'yes' : (status === 'fail' ? 'no' : 'update');
        $li.find('.dashicons').attr('class', 'dashicons dashicons-' + icon);
        if (msg) {
            $li.append(' — ' + msg);
        }
    }

    function updateProgress(current, total) {
        var pct = Math.round((current / total) * 100);
        $progressBar.css('width', pct + '%');
        $progressText.text('Installing plugin ' + current + ' of ' + total + '...');
    }

    function installNext() {
        if (pluginIndex >= pluginQueue.length) {
            $progressText.text('All plugins installed!');
            $complete.show();
            return;
        }

        var slug = pluginQueue[pluginIndex];
        var name = getPluginName(slug);
        pluginIndex++;

        addPluginItem(slug, name);
        updateProgress(pluginIndex, pluginQueue.length);
        setPluginStatus(slug, 'loading', 'Installing...');

        $.post(scmp.ajax_url, {
            action: 'scmp_install_plugin',
            nonce: scmp.nonce,
            slug: slug
        }).done(function (res) {
            if (res.success) {
                setPluginStatus(slug, 'done', res.data.message || 'Installed');
            } else {
                setPluginStatus(slug, 'fail', res.data.message || 'Failed');
            }
            installNext();
        }).fail(function () {
            setPluginStatus(slug, 'fail', 'Request failed');
            installNext();
        });
    }

    // Kick off plugin installation when step 3 is visible
    if ($('#scmp-step-3').length) {
        var stored = localStorage.getItem('scmp_selected_plugins');
        if (stored) {
            pluginQueue = JSON.parse(stored);
            // Clear it so a refresh doesn't re-run
            localStorage.removeItem('scmp_selected_plugins');
        }
        if (pluginQueue.length) {
            installNext();
        } else {
            $progressText.text('No plugins selected.');
        }
    }

    // ── Dashboard Install Actions ─────────────────────────────────────
    $(document).on('click', '.scmp-dash-install', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var slug = $btn.data('slug');
        var $container = $('#scmp-status-' + slug);
        var $dot = $container.find('.scmp-status-dot');
        var $text = $container.find('.scmp-status-text');

        $btn.prop('disabled', true).text('Installing...');
        $dot.attr('class', 'scmp-status-dot loading');
        $text.text('Installing...');

        $.post(scmp.ajax_url, {
            action: 'scmp_install_plugin',
            nonce: scmp.nonce,
            slug: slug
        }).done(function (res) {
            if (res.success) {
                $dot.attr('class', 'scmp-status-dot installed');
                $text.text('Installed');
                $btn.remove();
            } else {
                $dot.attr('class', 'scmp-status-dot missing');
                $text.text('Failed');
                $btn.prop('disabled', false).text('Retry');
                alert(res.data.message || 'Installation failed.');
            }
        }).fail(function () {
            $dot.attr('class', 'scmp-status-dot missing');
            $text.text('Failed');
            $btn.prop('disabled', false).text('Retry');
            alert('Request failed. Please try again.');
        });
    });

})(jQuery);