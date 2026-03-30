jQuery(document).ready(function ($) {

    // Check if we're on the dashboard
    if (smartinlinksData.is_dashboard) {
        initDashboard();
    } else {
        initMetaBox();
    }

    // ===== META BOX FUNCTIONALITY =====
    function initMetaBox() {
        $('#sil-analyze').on('click', function () {
            const btn = $(this);
            const postId = $('#sil-post-id').val() || $('#post_ID').val();

            if (!postId) {
                return;
            }

            btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Analyzing...');

            $.post(smartinlinksData.ajax_url, {
                action: 'smartinlinks_analyze',
                nonce: smartinlinksData.nonce,
                post_id: postId
            }, function (response) {
                if (response.success) {
                    displaySuggestions(response.data);
                }
                btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Analyze Content');
            }).fail(function() {
                btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Analyze Content');
                showNotice('Analysis failed. Please try again.', 'error');
            });
        });

        $(document).on('click', '.sil-add-link', function () {
            const btn = $(this);
            const row = btn.closest('tr');
            const anchor = btn.data('anchor');
            const url = btn.data('url');
            const postId = $('#sil-post-id').val() || $('#post_ID').val();

            btn.prop('disabled', true).text('Adding...');

            $.post(smartinlinksData.ajax_url, {
                action: 'smartinlinks_add_link',
                nonce: smartinlinksData.nonce,
                post_id: postId,
                anchor: anchor,
                target_url: url
            }, function (response) {
                if (response.success) {
                    if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                        tinymce.get('content').setContent(response.data.content);
                    } else {
                        $('#content').val(response.data.content);
                    }

                    row.find('.column-action').html('<span class="link-success"><span class="dashicons dashicons-yes-alt"></span> Linked</span>');

                    showNotice('Internal Link Added Successfully');

                    setTimeout(function () {
                        location.reload();
                    }, 2000);
                } else {
                    btn.prop('disabled', false).text('Add Link');
                    showNotice('Failed to add link. Please try again.', 'error');
                }
            }).fail(function() {
                btn.prop('disabled', false).text('Add Link');
                showNotice('Failed to add link. Please try again.', 'error');
            });
        });
    }

    function displaySuggestions(suggestions) {
        const tbody = $('#sil-tbody');
        tbody.empty();

        if (suggestions.length === 0) {
            tbody.append('<tr><td colspan="4" style="text-align:center;padding:20px;color:#666;">No internal linking opportunities found</td></tr>');
            return;
        }

        suggestions.forEach(function (s) {
            const strengthClass = s.strength === 'Strong' ? 'strength-strong' : 'strength-normal';
            const actionContent = s.is_linked ?
                '<span class="link-success"><span class="dashicons dashicons-yes-alt"></span> Linked</span>' :
                `<button class="sil-add-link" data-anchor="${s.anchor}" data-url="${s.target_url}">Add Link</button>`;

            const row = $('<tr>').attr('data-anchor', s.anchor);
            
            const anchorCell = $('<td>').addClass('column-anchor').append($('<strong>').text(s.anchor));
            const strengthCell = $('<td>').addClass('column-strength').append(
                $('<span>').addClass(`strength-badge ${strengthClass}`).text(s.strength)
            );
            const targetCell = $('<td>').addClass('column-target').append(
                $('<a>').attr({
                    href: s.target_url,
                    target: '_blank'
                }).addClass('target-link').text(s.target_title)
            );
            
            let actionCell;
            if (s.is_linked) {
                actionCell = $('<td>').addClass('column-action').append(
                    $('<span>').addClass('link-success').append(
                        $('<span>').addClass('dashicons dashicons-yes-alt'),
                        ' Linked'
                    )
                );
            } else {
                actionCell = $('<td>').addClass('column-action').append(
                    $('<button>').addClass('sil-add-link')
                        .attr('data-anchor', s.anchor)
                        .attr('data-url', s.target_url)
                        .text('Add Link')
                );
            }
            
            row.append(anchorCell, strengthCell, targetCell, actionCell);
            tbody.append(row);
        });
    }

    // ===== DASHBOARD FUNCTIONALITY =====
    function initDashboard() {
        let currentTab = 'available';
        let availablePage = 1;
        let addedPage = 1;
        let availablePerPage = 25;
        let addedPerPage = 25;

        // Load initial stats
        loadStats();

        // Tab switching
        $('.sil-tab-button').on('click', function () {
            const tab = $(this).data('tab');
            switchTab(tab);
        });

        function switchTab(tab) {
            currentTab = tab;
            $('.sil-tab-button').removeClass('active');
            $(`.sil-tab-button[data-tab="${tab}"]`).addClass('active');
            $('.sil-tab-content').hide();
            $(`#tab-${tab}`).show();

            if (tab === 'available') {
                loadAvailableLinks(availablePage, availablePerPage);
            } else {
                loadAddedLinks(addedPage, addedPerPage);
            }
        }

        // Bulk analyze
        $('#sil-bulk-analyze').on('click', function () {
            const btn = $(this);
            const select = $('#sil-analyze-limit');
            const limit = select.val();

            btn.prop('disabled', true);
            select.prop('disabled', true);

            $('#sil-progress-container').show();
            $('#sil-progress-fill').css('width', '0%');
            $('#sil-progress-text').text('Starting analysis...');

            $.post(smartinlinksData.ajax_url, {
                action: 'smartinlinks_bulk_analyze',
                nonce: smartinlinksData.nonce,
                limit: limit
            }, function (response) {
                if (response.success) {
                    const data = response.data;
                    const percent = (data.progress / data.total * 100).toFixed(0);
                    $('#sil-progress-fill').css('width', percent + '%');
                    $('#sil-progress-text').text(`Analyzing post ${data.progress} of ${data.total}... Found ${data.found} opportunities`);

                    if (data.complete) {
                        setTimeout(function () {
                            $('#sil-progress-container').hide();
                            btn.prop('disabled', false);
                            select.prop('disabled', false);
                            showNotice(`Analysis complete! Found ${data.found} linking opportunities.`);

                            // Reset to page 1 and reload
                            availablePage = 1;
                            loadStats();
                            loadAvailableLinks(availablePage, availablePerPage);
                        }, 500);
                    }
                } else {
                    btn.prop('disabled', false);
                    select.prop('disabled', false);
                    $('#sil-progress-container').hide();
                    showNotice('Analysis failed. Please try again.', 'error');
                }
            }).fail(function (xhr, status, error) {
                btn.prop('disabled', false);
                select.prop('disabled', false);
                $('#sil-progress-container').hide();
                showNotice('Analysis failed. Please try again.', 'error');
            });
        });

        // Per page change handlers
        $('#available-per-page').on('change', function () {
            availablePerPage = parseInt($(this).val());
            availablePage = 1;
            loadAvailableLinks(availablePage, availablePerPage);
        });

        $('#added-per-page').on('change', function () {
            addedPerPage = parseInt($(this).val());
            addedPage = 1;
            loadAddedLinks(addedPage, addedPerPage);
        });

        // Load available links
        function loadAvailableLinks(page, perPage) {
            $.post(smartinlinksData.ajax_url, {
                action: 'smartinlinks_get_suggestions',
                nonce: smartinlinksData.nonce,
                page: page,
                per_page: perPage
            }, function (response) {
                if (response.success) {
                    const data = response.data;
                    displayAvailableLinks(data.suggestions);
                    updatePagination('available', data.total, data.page, data.per_page);

                    const start = (data.page - 1) * data.per_page + 1;
                    const end = Math.min(data.page * data.per_page, data.total);
                    $('#available-showing').text(data.total > 0 ? `${start}-${end}` : '0');
                    $('#available-total').text(data.total);
                }
            }).fail(function() {
                showNotice('Failed to load suggestions. Please try again.', 'error');
            });
        }

        // Load added links
        function loadAddedLinks(page, perPage) {
            $.post(smartinlinksData.ajax_url, {
                action: 'smartinlinks_get_added_links',
                nonce: smartinlinksData.nonce,
                page: page,
                per_page: perPage
            }, function (response) {
                if (response.success) {
                    const data = response.data;
                    displayAddedLinks(data.links);
                    updatePagination('added', data.total, data.page, data.per_page);

                    const start = (data.page - 1) * data.per_page + 1;
                    const end = Math.min(data.page * data.per_page, data.total);
                    $('#added-showing').text(data.total > 0 ? `${start}-${end}` : '0');
                    $('#added-total').text(data.total);
                }
            });
        }

        // Display available links
        function displayAvailableLinks(suggestions) {
            const tbody = $('#available-tbody');
            tbody.empty();

            if (suggestions.length === 0) {
                tbody.append(`
                    <tr>
                        <td colspan="5" class="sil-empty-state">
                            <span class="dashicons dashicons-admin-links"></span>
                            <p>No available links found. Click "Analyze Website" to find linking opportunities.</p>
                        </td>
                    </tr>
                `);
                return;
            }

            suggestions.forEach(function (s) {
                const strengthClass = s.strength === 'Strong' ? 'strength-strong' : 'strength-normal';
                
                const row = $('<tr>');
                
                const sourceCell = $('<td>').addClass('column-source').append(
                    $('<a>').attr({
                        href: s.source_url,
                        target: '_blank'
                    }).text(s.source_title)
                );
                
                const keywordCell = $('<td>').addClass('column-keyword').append(
                    $('<strong>').text(s.anchor)
                );
                
                const strengthCell = $('<td>').addClass('column-strength').append(
                    $('<span>').addClass(`strength-badge ${strengthClass}`).text(s.strength)
                );
                
                const targetCell = $('<td>').addClass('column-target').append(
                    $('<a>').attr({
                        href: s.target_url,
                        target: '_blank'
                    }).addClass('target-link').text(s.target_title)
                );
                
                const actionCell = $('<td>').addClass('column-action').append(
                    $('<button>').addClass('sil-add-link')
                        .attr('data-id', s.id)
                        .text('Add Link')
                );
                
                row.append(sourceCell, keywordCell, strengthCell, targetCell, actionCell);
                tbody.append(row);
            });
        }

        // Display added links
        function displayAddedLinks(links) {
            const tbody = $('#added-tbody');
            tbody.empty();

            if (links.length === 0) {
                tbody.append(`
                    <tr>
                        <td colspan="5" class="sil-empty-state">
                            <span class="dashicons dashicons-admin-links"></span>
                            <p>No links have been added yet.</p>
                        </td>
                    </tr>
                `);
                return;
            }

            links.forEach(function (link) {
                const strengthClass = link.strength === 'Strong' ? 'strength-strong' : 'strength-normal';
                const date = new Date(link.added_at);
                const formattedDate = date.toLocaleDateString() + ' ' + date.toLocaleTimeString();

                const row = $('<tr>');
                
                const sourceCell = $('<td>').addClass('column-source').append(
                    $('<a>').attr({
                        href: link.source_url,
                        target: '_blank'
                    }).text(link.source_title)
                );
                
                const keywordCell = $('<td>').addClass('column-keyword').append(
                    $('<strong>').text(link.anchor)
                );
                
                const strengthCell = $('<td>').addClass('column-strength').append(
                    $('<span>').addClass(`strength-badge ${strengthClass}`).text(link.strength)
                );
                
                const targetCell = $('<td>').addClass('column-target').append(
                    $('<a>').attr({
                        href: link.target_url,
                        target: '_blank'
                    }).addClass('target-link').text(link.target_title)
                );
                
                const dateCell = $('<td>').addClass('column-date').text(formattedDate);
                
                row.append(sourceCell, keywordCell, strengthCell, targetCell, dateCell);
                tbody.append(row);
            });
        }

        // Update pagination
        function updatePagination(type, total, currentPage, perPage) {
            const totalPages = Math.ceil(total / perPage);
            const container = $(`#${type}-pagination`);
            container.empty();

            if (totalPages <= 1) return;

            // Previous button
            const prevDisabled = currentPage === 1 ? 'disabled' : '';
            const prevBtn = $('<button>').addClass('sil-page-btn')
                .attr('data-page', currentPage - 1)
                .prop('disabled', currentPage === 1)
                .text('« Previous');
            container.append(prevBtn);

            // Page numbers
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
                    const activeClass = i === currentPage ? 'active' : '';
                    const pageBtn = $('<button>').addClass(`sil-page-btn ${activeClass}`)
                        .attr('data-page', i)
                        .text(i);
                    container.append(pageBtn);
                } else if (i === currentPage - 3 || i === currentPage + 3) {
                    container.append($('<span>').css('padding', '6px').text('...'));
                }
            }

            // Next button
            const nextBtn = $('<button>').addClass('sil-page-btn')
                .attr('data-page', currentPage + 1)
                .prop('disabled', currentPage === totalPages)
                .text('Next »');
            container.append(nextBtn);

            // Pagination click handlers
            container.find('.sil-page-btn').on('click', function () {
                if ($(this).prop('disabled')) return;
                const page = parseInt($(this).data('page'));

                if (type === 'available') {
                    availablePage = page;
                    loadAvailableLinks(page, availablePerPage);
                } else {
                    addedPage = page;
                    loadAddedLinks(page, addedPerPage);
                }
            });
        }

        // Add link from dashboard
        $(document).on('click', '#available-tbody .sil-add-link', function () {
            const btn = $(this);
            const suggestionId = btn.data('id');

            btn.prop('disabled', true).text('Adding...');

            $.post(smartinlinksData.ajax_url, {
                action: 'smartinlinks_add_link_bulk',
                nonce: smartinlinksData.nonce,
                suggestion_id: suggestionId
            }, function (response) {
                if (response.success) {
                    showNotice('Link added successfully!');
                    loadStats();
                    loadAvailableLinks(availablePage, availablePerPage);
                } else {
                    btn.prop('disabled', false).text('Add Link');
                    showNotice('Failed to add link. Please try again.', 'error');
                }
            }).fail(function() {
                btn.prop('disabled', false).text('Add Link');
                showNotice('Failed to add link. Please try again.', 'error');
            });
        });

        // Load stats
        function loadStats() {
            $.post(smartinlinksData.ajax_url, {
                action: 'smartinlinks_get_stats',
                nonce: smartinlinksData.nonce
            }, function (response) {
                if (response.success) {
                    $('#sil-stat-analyzed').text(response.data.analyzed);
                    $('#sil-stat-found').text(response.data.found);
                    $('#sil-stat-available').text(response.data.available);
                    $('#sil-stat-linked').text(response.data.linked);
                }
            });
        }

        // Initial load
        loadAvailableLinks(availablePage, availablePerPage);
    }

    // ===== SHARED FUNCTIONS =====
    function showNotice(message, type = 'success') {
        const noticeClass = type === 'error' ? 'notice-error' : 'notice-success';
        const notice = $('<div>').addClass(`notice ${noticeClass} is-dismissible`)
            .css({
                position: 'fixed',
                top: '32px',
                right: '20px',
                zIndex: '9999',
                maxWidth: '400px'
            });
        
        const messageP = $('<p>').append($('<strong>').text(message));
        const dismissBtn = $('<button>').attr('type', 'button').addClass('notice-dismiss')
            .append($('<span>').addClass('screen-reader-text').text('Dismiss this notice.'));
        
        notice.append(messageP, dismissBtn);
        $('body').append(notice);

        dismissBtn.on('click', function () {
            notice.remove();
        });

        setTimeout(function () {
            notice.fadeOut(function () {
                notice.remove();
            });
        }, 5000);
    }
});
