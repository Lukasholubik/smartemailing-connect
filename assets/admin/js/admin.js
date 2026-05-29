/* SmartEmailing Connect – Admin JS */
/* global smecAdmin, jQuery */

(function ($) {
  'use strict';

  var SMEC = {
    nonce:   smecAdmin.nonce,
    ajaxUrl: smecAdmin.ajaxUrl,

    // ── AJAX helper ──────────────────────────────────────────────
    ajax: function (action, data, done, fail) {
      data = $.extend({ action: 'smec_' + action, nonce: SMEC.nonce }, data);
      $.post(SMEC.ajaxUrl, data)
        .done(function (res) {
          if (res.success) { if (done) done(res.data); }
          else { if (fail) fail(res.data); else SMEC.notify('#smec-global-err', res.data.message || smecAdmin.strings.error, 'error'); }
        })
        .fail(function () { if (fail) fail({ message: smecAdmin.strings.error }); });
    },

    // ── Result helpers ───────────────────────────────────────────
    showResult: function (selector, msg, type) {
      var el = $(selector);
      el.text(msg).removeClass('smec-success smec-failure').addClass(type === 'success' ? 'smec-success' : 'smec-failure').show();
      setTimeout(function () { el.fadeOut(400, function () { el.text(''); }); }, 4000);
    },

    notify: function (selector, msg, type) {
      var el = $(selector);
      el.text(msg).removeClass('success error').addClass(type || 'success').show();
    },

    // ── Collect form data from a table or section ─────────────────
    collectTableForm: function (wrapper) {
      var data = {};
      $(wrapper).find('[name]').each(function () {
        var el    = $(this);
        var name  = el.attr('name');
        var value = el.is(':checkbox') ? (el.is(':checked') ? 1 : 0)
                  : el.is('select[multiple]') ? el.val()
                  : el.val();
        data[name] = value;
      });
      return data;
    },

    // ── Tabs ──────────────────────────────────────────────────────
    initTabs: function (container) {
      $(container).on('click', '.smec-tab', function () {
        var tab = $(this).data('tab');
        $(container).find('.smec-tab').removeClass('active');
        $(this).addClass('active');
        $(container).find('.smec-tab-content').removeClass('active');
        $(container).find('#smec-tab-' + tab).addClass('active');
      });
    },

    // ── Source select: show/hide value vs placeholder select ───────
    initSourceSelects: function (container) {
      $(container).on('change', '.smec-source-select', function () {
        var row = $(this).closest('tr');
        SMEC.updateSourceRow(row, $(this).val());
      });
    },

    updateSourceRow: function (row, source) {
      var valInp  = row.find('.smec-field-value');
      var phSel   = row.find('.smec-placeholder-select');
      if (source === 'placeholder') {
        valInp.hide(); phSel.show();
      } else {
        valInp.show(); phSel.hide();
      }
    },

    // ─────────────────────────────────────────────────────────────
    //  API PAGE
    // ─────────────────────────────────────────────────────────────
    initApiPage: function () {
      $('#smec-save-api').on('click', function () {
        var data = {
          data: JSON.stringify({
            enabled:  $('#smec-api-enabled').is(':checked') ? 1 : 0,
            username: $('#smec-username').val(),
            api_key:  $('#smec-api-key').val(),
            base_url: $('#smec-base-url').val(),
          })
        };
        SMEC.ajax('save_api', data,
          function () { SMEC.showResult('#smec-api-result', smecAdmin.strings.saved, 'success'); },
          function (d) { SMEC.showResult('#smec-api-result', d.message, 'error'); }
        );
      });

      $('#smec-test-api').on('click', function () {
        var btn = $(this).prop('disabled', true).text(smecAdmin.strings.testing);
        SMEC.ajax('test_api', {},
          function (d) {
            btn.prop('disabled', false).text('Otestovat připojení');
            SMEC.showResult('#smec-api-result', d.message, 'success');
            $('#smec-api-test-detail').show();
            $('#smec-api-test-content').html('<span style="color:#007017">✓ ' + SMEC.esc(d.message) + '</span>');
          },
          function (d) {
            btn.prop('disabled', false).text('Otestovat připojení');
            SMEC.showResult('#smec-api-result', d.message, 'error');
            $('#smec-api-test-detail').show();
            $('#smec-api-test-content').html('<span style="color:#c9372c">✗ ' + SMEC.esc(d.message) + '</span>');
          }
        );
      });
    },

    // ─────────────────────────────────────────────────────────────
    //  WEBTRACKING PAGE
    // ─────────────────────────────────────────────────────────────
    initWebtackingPage: function () {
      $('#smec-save-webtracking').on('click', function () {
        var roles = [];
        $('[name="exclude_roles[]"]:checked').each(function () { roles.push($(this).val()); });
        var pages = $('[name="excluded_pages[]"]').val() || [];

        var data = {
          data: JSON.stringify({
            enabled:        $('#wt-enabled').is(':checked') ? 1 : 0,
            guid:           $('#wt-guid').val(),
            position:       $('[name="position"]:checked').val() || 'footer',
            exclude_admins: $('#wt-exclude-admins').is(':checked') ? 1 : 0,
            exclude_roles:  roles,
            excluded_pages: pages,
            custom_code:    $('#wt-custom-code').val(),
          })
        };
        SMEC.ajax('save_webtracking', data,
          function () { SMEC.showResult('#smec-wt-result', smecAdmin.strings.saved, 'success'); },
          function (d) { SMEC.showResult('#smec-wt-result', d.message, 'error'); }
        );
      });
    },

    // ─────────────────────────────────────────────────────────────
    //  LISTS PAGE
    // ─────────────────────────────────────────────────────────────
    initListsPage: function () {
      $('#smec-fetch-lists').on('click', function () {
        var btn = $(this).prop('disabled', true).text(smecAdmin.strings.loading);
        SMEC.ajax('fetch_lists', {},
          function (d) {
            btn.prop('disabled', false).text('↻ Načíst ze SmartEmailingu');
            SMEC.renderListsTable(d.data || []);
          },
          function (d) {
            btn.prop('disabled', false).text('↻ Načíst ze SmartEmailingu');
            alert(d.message);
          }
        );
      });

      $('#smec-create-list').on('click', function () {
        var name = $('#smec-new-list-name').val().trim();
        if (!name) { SMEC.showResult('#smec-create-list-result', 'Zadejte název.', 'error'); return; }
        SMEC.ajax('create_list', { name: name },
          function () {
            SMEC.showResult('#smec-create-list-result', 'Seznam vytvořen.', 'success');
            $('#smec-new-list-name').val('');
          },
          function (d) { SMEC.showResult('#smec-create-list-result', d.message, 'error'); }
        );
      });

      $('#smec-fetch-fields').on('click', function () {
        var btn = $(this).prop('disabled', true).text(smecAdmin.strings.loading);
        SMEC.ajax('fetch_customfields', {},
          function (d) {
            btn.prop('disabled', false).text('↻ Načíst ze SmartEmailingu');
            SMEC.renderFieldsTable(d.data || []);
          },
          function (d) {
            btn.prop('disabled', false).text('↻ Načíst ze SmartEmailingu');
            alert(d.message);
          }
        );
      });

      $('#smec-refresh-cache').on('click', function () {
        SMEC.ajax('refresh_cache', {}, function (d) { alert(d.message); });
      });
    },

    renderListsTable: function (lists) {
      var html = '<table class="wp-list-table widefat striped smec-data-table"><thead><tr><th>ID</th><th>Název</th></tr></thead><tbody>';
      if (!lists.length) { html += '<tr><td colspan="2" class="smec-muted">Žádné seznamy.</td></tr>'; }
      lists.forEach(function (l) {
        html += '<tr><td><code>' + SMEC.esc(String(l.id || '—')) + '</code></td><td>' + SMEC.esc(l.name || l.publicname || '—') + '</td></tr>';
      });
      html += '</tbody></table>';
      $('#smec-lists-container').html(html);
    },

    renderFieldsTable: function (fields) {
      var html = '<table class="wp-list-table widefat striped smec-data-table"><thead><tr><th>ID</th><th>Název</th><th>Typ</th></tr></thead><tbody>';
      if (!fields.length) { html += '<tr><td colspan="3" class="smec-muted">Žádná pole.</td></tr>'; }
      fields.forEach(function (f) {
        html += '<tr><td><code>' + SMEC.esc(String(f.id || '—')) + '</code></td><td>' + SMEC.esc(f.name || '—') + '</td><td>' + SMEC.esc(f.type || '—') + '</td></tr>';
      });
      html += '</tbody></table>';
      $('#smec-fields-container').html(html);
    },

    // ─────────────────────────────────────────────────────────────
    //  FORMS PAGE
    // ─────────────────────────────────────────────────────────────
    currentMapping: null,

    initFormsPage: function () {
      SMEC.initTabs('#smec-mapping-editor');
      SMEC.initSourceSelects('#smec-mapping-editor');

      // Add/Edit mapping
      $('#smec-add-mapping').on('click', function () {
        SMEC.openEditor(null);
      });

      $(document).on('click', '.smec-edit-mapping', function () {
        var id = $(this).data('id');
        SMEC.ajax('get_mappings', {}, function (d) {
          var m = (d.mappings || []).find(function (x) { return x.id === id; });
          if (m) SMEC.openEditor(m);
        });
      });

      // Cancel
      $('#smec-cancel-edit, #smec-cancel-edit2').on('click', function () {
        $('#smec-mapping-editor').hide();
        $('#smec-mappings-list').show();
      });

      // Delete
      $(document).on('click', '.smec-delete-mapping', function () {
        if (!confirm(smecAdmin.strings.confirm_delete)) return;
        var id = $(this).data('id');
        var row = $('tr[data-id="' + id + '"]');
        SMEC.ajax('delete_mapping', { id: id }, function () { row.fadeOut(200, function () { row.remove(); }); });
      });

      // Toggle enabled
      $(document).on('click', '.smec-toggle-mapping', function () {
        var id  = $(this).data('id');
        var btn = $(this);
        SMEC.ajax('toggle_mapping', { id: id }, function (d) {
          btn.text(d.enabled ? 'Deaktivovat' : 'Aktivovat');
          var badge = $('tr[data-id="' + id + '"] .smec-badge');
          badge.removeClass('smec-badge-active smec-badge-inactive')
               .addClass(d.enabled ? 'smec-badge-active' : 'smec-badge-inactive')
               .text(d.enabled ? 'Aktivní' : 'Neaktivní');
        });
      });

      // Duplicate
      $(document).on('click', '.smec-duplicate-mapping', function () {
        var id = $(this).data('id');
        SMEC.ajax('duplicate_mapping', { id: id }, function () { location.reload(); });
      });

      // Save mapping
      $('#smec-save-mapping').on('click', function () {
        var mapping = SMEC.collectMappingFromEditor();
        SMEC.ajax('save_mapping', { mapping: JSON.stringify(mapping) },
          function (d) {
            SMEC.showResult('#smec-mapping-result', smecAdmin.strings.saved, 'success');
            setTimeout(function () { location.reload(); }, 1200);
          },
          function (d) { SMEC.showResult('#smec-mapping-result', d.message, 'error'); }
        );
      });

      // Add CF row
      $('#smec-add-cf-row').on('click', function () {
        var tpl = $('#smec-cf-body .smec-cf-template').clone().removeClass('smec-cf-template').show();
        $('#smec-cf-body').append(tpl);
        SMEC.initSourceSelects('#smec-cf-body');
      });

      // Add tag row
      $('#smec-add-tag-row').on('click', function () {
        var tpl = $('#smec-tags-body .smec-tag-template').clone().removeClass('smec-tag-template').show();
        $('#smec-tags-body').append(tpl);
        SMEC.initSourceSelects('#smec-tags-body');
      });

      // Add condition
      $('#smec-add-condition').on('click', function () {
        var tpl = $('#smec-conditions-body .smec-condition-template').clone().removeClass('smec-condition-template').show();
        $('#smec-conditions-body').append(tpl);
      });

      // Remove rows
      $(document).on('click', '.smec-remove-row', function () {
        $(this).closest('tr').remove();
      });

      // Test contact
      $('#smec-send-test').on('click', function () {
        var email = $('#smec-test-email').val();
        var id    = $('#smec-mapping-id').val();
        if (!id) { SMEC.notify('#smec-test-result', 'Uložte propojení nejdříve.', 'error'); return; }
        SMEC.ajax('test_contact', { mapping_id: id, email: email },
          function (d) { SMEC.notify('#smec-test-result', '✓ Testovací kontakt odeslán.', 'success'); $('#smec-test-result').show(); },
          function (d) { SMEC.notify('#smec-test-result', '✗ ' + (d.message || smecAdmin.strings.error), 'error'); $('#smec-test-result').show(); }
        );
      });
    },

    openEditor: function (mapping) {
      SMEC.currentMapping = mapping;
      $('#smec-mappings-list').hide();
      $('#smec-mapping-editor').show();

      if (!mapping) {
        // New
        $('#smec-editor-title').text('Nové propojení formuláře');
        $('#smec-mapping-id').val('');
        $('#m-name').val('');
        $('#m-form-type').val('elementor');
        $('#m-form-id').val('');
        $('#m-list-id').val('');
        $('#m-contact-status').val('confirmed');
        $('#m-consent-field').val('');
        $('#m-enabled').prop('checked', true);
        // Clear rows
        $('#smec-cf-body tr:not(.smec-cf-template)').remove();
        $('#smec-tags-body tr:not(.smec-tag-template)').remove();
        $('#smec-conditions-body tr:not(.smec-condition-template)').remove();
        // Reset system fields
        $('#smec-system-fields-body .smec-source-select').val('form_field').trigger('change');
        $('#smec-system-fields-body .smec-field-value').val('');
      } else {
        // Edit
        $('#smec-editor-title').text('Upravit propojení: ' + (mapping.name || ''));
        $('#smec-mapping-id').val(mapping.id || '');
        $('#m-name').val(mapping.name || '');
        $('#m-form-type').val(mapping.form_type || 'elementor');
        $('#m-form-id').val(mapping.form_id || '');
        $('#m-list-id').val(String(mapping.list_id || ''));
        $('#m-contact-status').val(mapping.contact_status || 'confirmed');
        $('#m-consent-field').val(mapping.consent_field || '');
        $('#m-enabled').prop('checked', !!mapping.enabled);

        // System fields
        var sf = mapping.system_fields || {};
        $('#smec-system-fields-body tr').each(function () {
          var key  = $(this).data('field');
          var conf = sf[key] || {};
          $(this).find('.smec-source-select').val(conf.source || 'form_field');
          $(this).find('.smec-field-value').val(conf.value || '');
          $(this).find('.smec-placeholder-select').val(conf.value || '');
          SMEC.updateSourceRow($(this), conf.source || 'form_field');
        });

        // Custom fields
        $('#smec-cf-body tr:not(.smec-cf-template)').remove();
        (mapping.custom_field_mapping || []).forEach(function (row) {
          var tpl = $('#smec-cf-body .smec-cf-template').clone().removeClass('smec-cf-template').show();
          tpl.find('.smec-cf-field-id').val(String(row.field_id || ''));
          tpl.find('.smec-source-select').val(row.source || 'form_field');
          tpl.find('.smec-field-value').val(row.value || '');
          SMEC.updateSourceRow(tpl, row.source || 'form_field');
          $('#smec-cf-body').append(tpl);
        });

        // Tags
        $('#smec-tags-body tr:not(.smec-tag-template)').remove();
        (mapping.tags || []).forEach(function (tag) {
          var tpl = $('#smec-tags-body .smec-tag-template').clone().removeClass('smec-tag-template').show();
          tpl.find('.smec-source-select').val(tag.source || 'static');
          tpl.find('.smec-field-value').val(tag.value || '');
          SMEC.updateSourceRow(tpl, tag.source || 'static');
          $('#smec-tags-body').append(tpl);
        });

        // Conditions
        $('#smec-conditions-body tr:not(.smec-condition-template)').remove();
        (mapping.conditions || []).forEach(function (c) {
          var tpl = $('#smec-conditions-body .smec-condition-template').clone().removeClass('smec-condition-template').show();
          tpl.find('.smec-condition-field').val(c.field || '');
          tpl.find('.smec-condition-op').val(c.operator || '==');
          tpl.find('.smec-condition-val').val(c.value || '');
          $('#smec-conditions-body').append(tpl);
        });
      }

      // Scroll to editor
      $('html, body').animate({ scrollTop: $('#smec-mapping-editor').offset().top - 60 }, 300);
    },

    collectMappingFromEditor: function () {
      var systemFields = {};
      $('#smec-system-fields-body tr').each(function () {
        var key    = $(this).data('field');
        var source = $(this).find('.smec-source-select').val();
        var value  = source === 'placeholder'
          ? $(this).find('.smec-placeholder-select').val()
          : $(this).find('.smec-field-value').val();
        systemFields[key] = { source: source, value: value };
      });

      var customFields = [];
      $('#smec-cf-body tr:not(.smec-cf-template):visible').each(function () {
        var fid    = $(this).find('.smec-cf-field-id').val();
        var source = $(this).find('.smec-source-select').val();
        var value  = source === 'placeholder'
          ? $(this).find('.smec-placeholder-select').val()
          : $(this).find('.smec-field-value').val();
        if (fid) customFields.push({ field_id: parseInt(fid, 10), source: source, value: value });
      });

      var tags = [];
      $('#smec-tags-body tr:not(.smec-tag-template):visible').each(function () {
        var source = $(this).find('.smec-source-select').val();
        var value  = source === 'placeholder'
          ? $(this).find('.smec-placeholder-select').val()
          : $(this).find('.smec-field-value').val();
        if (value) tags.push({ source: source, value: value });
      });

      var conditions = [];
      $('#smec-conditions-body tr:not(.smec-condition-template):visible').each(function () {
        conditions.push({
          field:    $(this).find('.smec-condition-field').val(),
          operator: $(this).find('.smec-condition-op').val(),
          value:    $(this).find('.smec-condition-val').val(),
        });
      });

      return {
        id:                   $('#smec-mapping-id').val(),
        name:                 $('#m-name').val(),
        form_type:            $('#m-form-type').val(),
        form_id:              $('#m-form-id').val(),
        list_id:              parseInt($('#m-list-id').val(), 10) || 0,
        contact_status:       $('#m-contact-status').val(),
        consent_field:        $('#m-consent-field').val(),
        enabled:              $('#m-enabled').is(':checked') ? 1 : 0,
        system_fields:        systemFields,
        custom_field_mapping: customFields,
        tags:                 tags,
        conditions:           conditions,
      };
    },

    // ─────────────────────────────────────────────────────────────
    //  LOGS PAGE
    // ─────────────────────────────────────────────────────────────
    logsOffset: 0,
    logsLimit: 50,

    initLogsPage: function () {
      $('#smec-load-logs').on('click', function () {
        SMEC.logsOffset = 0;
        SMEC.loadLogs();
      });

      $('#smec-clear-logs-all').on('click', function () {
        if (!confirm(smecAdmin.strings.confirm_delete)) return;
        SMEC.ajax('clear_logs', { older_than_days: '' }, function (d) { alert(d.message); $('#smec-logs-table-wrap').html(''); });
      });

      $('#smec-clear-logs-old').on('click', function () {
        SMEC.ajax('clear_logs', { older_than_days: 30 }, function (d) { alert(d.message); SMEC.loadLogs(); });
      });

      $('#smec-process-queue').on('click', function () {
        SMEC.ajax('process_queue_now', {}, function (d) {
          SMEC.notify('#smec-queue-result', d.message, 'success');
          $('#smec-queue-result').show();
        });
      });

      $('#smec-retry-failed').on('click', function () {
        SMEC.ajax('retry_failed_queue', {}, function (d) {
          SMEC.notify('#smec-queue-result', d.message, 'success');
          $('#smec-queue-result').show();
        });
      });

      $('#smec-clear-done-queue').on('click', function () {
        SMEC.ajax('clear_queue', { status: 'done' }, function (d) {
          SMEC.notify('#smec-queue-result', d.message, 'success');
          $('#smec-queue-result').show();
        });
      });
    },

    loadLogs: function () {
      var data = {
        type:   $('#log-type').val(),
        level:  $('#log-level').val(),
        limit:  SMEC.logsLimit,
        offset: SMEC.logsOffset,
      };
      SMEC.ajax('get_logs', data, function (d) {
        SMEC.renderLogsTable(d.logs || [], d.total || 0);
      });
    },

    renderLogsTable: function (logs, total) {
      var html = '<table class="wp-list-table widefat striped smec-log-table"><thead><tr><th>Čas</th><th>Typ</th><th>Úroveň</th><th>Zpráva</th></tr></thead><tbody>';
      if (!logs.length) { html += '<tr><td colspan="4" class="smec-muted" style="text-align:center;">Žádné záznamy.</td></tr>'; }
      logs.forEach(function (l) {
        html += '<tr><td>' + SMEC.esc(l.created_at) + '</td><td>' + SMEC.esc(l.type) + '</td>';
        html += '<td><span class="smec-level-' + SMEC.esc(l.level) + '">' + SMEC.esc(l.level) + '</span></td>';
        html += '<td>' + SMEC.esc(l.message);
        if (l.context) { html += ' <small class="smec-muted">[kontext]</small>'; }
        html += '</td></tr>';
      });
      html += '</tbody></table>';
      $('#smec-logs-table-wrap').html(html);

      // Pagination
      var pages = Math.ceil(total / SMEC.logsLimit);
      var curPage = Math.floor(SMEC.logsOffset / SMEC.logsLimit);
      var pagHtml = '';
      for (var i = 0; i < pages; i++) {
        pagHtml += '<button class="button smec-page-btn' + (i === curPage ? ' active' : '') + '" data-page="' + i + '">' + (i + 1) + '</button>';
      }
      $('#smec-logs-pagination').html(pagHtml);
    },

    // ─────────────────────────────────────────────────────────────
    //  SETTINGS PAGE
    // ─────────────────────────────────────────────────────────────
    initSettingsPage: function () {
      $('#smec-save-general').on('click', function () {
        var modules = {};
        $('.smec-module-toggle').each(function () {
          modules[$(this).data('module')] = $(this).is(':checked') ? 1 : 0;
        });
        var data = {
          data: JSON.stringify({
            debug:               $('#gs-debug').is(':checked') ? 1 : 0,
            log_retention_days:  $('#gs-log-retention').val(),
            delete_on_uninstall: $('#gs-delete-on-uninstall').is(':checked') ? 1 : 0,
            modules:             modules,
          })
        };
        SMEC.ajax('save_general_settings', data,
          function () { SMEC.showResult('#smec-general-result', smecAdmin.strings.saved, 'success'); },
          function (d) { SMEC.showResult('#smec-general-result', d.message, 'error'); }
        );
      });

      // Export
      $('#smec-export-settings').on('click', function () {
        var includeKey = $('#export-include-api').is(':checked') ? 1 : 0;
        SMEC.ajax('export_settings', { include_api_key: includeKey }, function (d) {
          $('#smec-export-output').val(d.json).show();
        });
      });

      // Import
      $('#smec-import-settings').on('click', function () {
        var json = $('#smec-import-input').val();
        if (!json) { alert('Vložte JSON.'); return; }
        SMEC.ajax('import_settings', { json: json },
          function (d) { $('#smec-import-result').html('<span style="color:#007017">✓ ' + SMEC.esc(d.message) + '</span>'); },
          function (d) { $('#smec-import-result').html('<span style="color:#c9372c">✗ ' + SMEC.esc(d.message) + '</span>'); }
        );
      });
    },

    // ─────────────────────────────────────────────────────────────
    //  READING TIME PAGE
    // ─────────────────────────────────────────────────────────────
    initReadingTimePage: function () {
      // Přesměrovat na nový systém presetů
      SMEC.initRtPresetsPage();
    },

    // ─────────────────────────────────────────────────────────────
    //  READING TIME PRESETY
    // ─────────────────────────────────────────────────────────────
    // ─────────────────────────────────────────────────────────────
    //  DIAGNOSTIKA (overview page)
    // ─────────────────────────────────────────────────────────────
    initDiagnostics: function () {

      // ── Expand / collapse položek ─────────────────────────────
      $(document).on('click keypress', '.smec-diag-item-header', function (e) {
        if (e.type === 'keypress' && e.which !== 13) return;
        var item = $(this).closest('.smec-diag-item');
        item.toggleClass('open');
      });

      // ── Obnovit diagnostiku přes AJAX ─────────────────────────
      $('#smec-refresh-diag').on('click', function () {
        var btn = $(this).prop('disabled', true).text('Kontroluji…');
        SMEC.ajax('run_diagnostics', {}, function (d) {
          btn.prop('disabled', false).text('↻ Obnovit');
          SMEC.renderDiagnostics(d.issues || [], d.counts || {});
        }, function () {
          btn.prop('disabled', false).text('↻ Obnovit');
        });
      });

      // ── Deaktivovat plugin ────────────────────────────────────
      $(document).on('click', '.smec-action-deactivate-plugin', function () {
        var plugin = $(this).data('plugin');
        var label  = $(this).data('label');
        if (!confirm('Opravdu chcete deaktivovat plugin?\n\n' + label + '\n\nPlugin se deaktivuje, vaše nastavení SmartEmailing Connect zůstane nedotčeno.')) return;

        var btn = $(this).prop('disabled', true).text('Deaktiváce…');
        SMEC.ajax('deactivate_plugin', { plugin: plugin },
          function (d) {
            btn.closest('.smec-diag-item').fadeOut(300, function () {
              $(this).remove();
              // Zobrazit potvrzení
              $('<div class="notice notice-success is-dismissible"><p>✓ ' + SMEC.esc(d.message) + ' Stránka se obnoví…</p></div>')
                .insertBefore('#smec-diag-issues').hide().fadeIn(300);
              setTimeout(function () { location.reload(); }, 2000);
            });
          },
          function (d) {
            btn.prop('disabled', false).text(label);
            alert('Chyba: ' + (d.message || smecAdmin.strings.error));
          }
        );
      });

      // ── Retry queue z diagnostiky ─────────────────────────────
      $(document).on('click', '#diag-retry-queue', function () {
        var btn = $(this).prop('disabled', true).text('Zpracovávám…');
        SMEC.ajax('retry_failed_queue', {}, function (d) {
          btn.prop('disabled', false).text('Opakovat chybné odeslání');
          alert(d.message);
        });
      });

      $(document).on('click', '#diag-process-queue', function () {
        var btn = $(this).prop('disabled', true).text('Zpracovávám…');
        SMEC.ajax('process_queue_now', {}, function (d) {
          btn.prop('disabled', false).text('Zpracovat frontu nyní');
          alert(d.message);
        });
      });

      // ── Automaticky otevřít critical položky ─────────────────
      $('.smec-diag-item-critical').addClass('open');

      // ── Rescan formulářů ──────────────────────────────────────
      $('#smec-rescan-forms').on('click', function () {
        var btn = $(this).prop('disabled', true).text('Skenuji…');
        SMEC.ajax('scan_forms', {}, function (d) {
          btn.prop('disabled', false).text('↻ Znovu skenovat');
          location.reload(); // jednoduché – přenačtení obnoví tabulku
        }, function () {
          btn.prop('disabled', false).text('↻ Znovu skenovat');
        });
      });

      // ── Ignorovat formulář ────────────────────────────────────
      $(document).on('click', '.smec-ignore-form', function () {
        var type = $(this).data('type');
        var id   = $(this).data('id');
        var row  = $(this).closest('tr');

        // Volitelný důvod
        var reason = prompt('Důvod ignorování (volitelné):', '');
        if (reason === null) return; // zrušeno

        SMEC.ajax('ignore_form', { form_type: type, form_id: id, reason: reason },
          function () {
            row.addClass('smec-ignored-row').hide();
            // Aktualizovat badge
            var missing = $('#smec-form-monitor-table tbody tr.smec-row-warn:visible').length;
            SMEC.updateFormMonitorBadge(missing);
          },
          function (d) { alert(d.message || smecAdmin.strings.error); }
        );
      });

      // ── Odignorovat formulář ──────────────────────────────────
      $(document).on('click', '.smec-unignore-form', function () {
        var type = $(this).data('type');
        var id   = $(this).data('id');
        var row  = $(this).closest('tr');

        SMEC.ajax('unignore_form', { form_type: type, form_id: id },
          function () { location.reload(); },
          function (d) { alert(d.message || smecAdmin.strings.error); }
        );
      });

      // ── Toggle zobrazení ignorovaných ─────────────────────────
      $('#smec-show-ignored').on('change', function () {
        if ($(this).is(':checked')) {
          $('.smec-ignored-row').show();
        } else {
          $('.smec-ignored-row').hide();
        }
      });
    },

    // ─────────────────────────────────────────────────────────────
    //  NOTIFICATIONS PAGE
    // ─────────────────────────────────────────────────────────────
    initNotificationsPage: function () {

      // ── Uložit nastavení ──────────────────────────────────────
      $('#smec-save-notifications').on('click', function () {
        var events     = {};
        var thresholds = {};

        $('.notif-event-checkbox').each(function () {
          var ev = $(this).data('event');
          events[ev] = $(this).is(':checked') ? 1 : 0;
        });
        $('.notif-threshold-input').each(function () {
          var ev = $(this).data('event');
          thresholds[ev] = parseInt($(this).val(), 10) || 3;
        });

        var emails = $('#notif-email').val().split(/[\n,;]+/)
          .map(function (e) { return e.trim(); })
          .filter(Boolean);

        var data = {
          data: JSON.stringify({
            enabled:        $('#notif-enabled').is(':checked') ? 1 : 0,
            email:          emails,
            slack_webhook:  $('#notif-slack').val(),
            webhook_url:    $('#notif-webhook').val(),
            cooldown_hours: parseInt($('#notif-cooldown').val(), 10) || 24,
            events:         events,
            thresholds:     thresholds,
          })
        };

        SMEC.ajax('save_notifications', data,
          function () { SMEC.showResult('#smec-notif-result', smecAdmin.strings.saved, 'success'); },
          function (d) { SMEC.showResult('#smec-notif-result', d.message, 'error'); }
        );
      });

      // ── Testovací zpráva ──────────────────────────────────────
      $('#smec-test-notification').on('click', function () {
        var btn = $(this).prop('disabled', true).text('Odesilam…');

        // Sestavit aktuální cfg z formuláře
        var events = {}, thresholds = {};
        $('.notif-event-checkbox').each(function () {
          events[$(this).data('event')] = $(this).is(':checked') ? 1 : 0;
        });
        $('.notif-threshold-input').each(function () {
          thresholds[$(this).data('event')] = parseInt($(this).val(), 10) || 3;
        });
        var emails = $('#notif-email').val().split(/[\n,;]+/).map(function(e){return e.trim();}).filter(Boolean);

        var cfg = {
          enabled:        $('#notif-enabled').is(':checked') ? 1 : 0,
          email:          emails,
          slack_webhook:  $('#notif-slack').val().trim(),
          webhook_url:    $('#notif-webhook').val().trim(),
          cooldown_hours: parseInt($('#notif-cooldown').val(), 10) || 24,
          events:         events,
          thresholds:     thresholds,
        };

        SMEC.ajax('test_notification', { cfg: JSON.stringify(cfg) },
          function (d) {
            btn.prop('disabled', false).text('Odeslat testovaci zpravu');
            SMEC.renderTestResult(d);
          },
          function (d) {
            btn.prop('disabled', false).text('Odeslat testovaci zpravu');
            SMEC.renderTestResult(d, true);
          }
        );
      });

      // ── Reset stavu ───────────────────────────────────────────
      $('#smec-reset-notif-state').on('click', function () {
        if (!confirm('Opravdu vynulovat stav notifikatoru? Cooldown bude smazan.')) return;
        SMEC.ajax('reset_notifier_state', {}, function (d) {
          alert(d.message);
          location.reload();
        });
      });
    },

    renderTestResult: function (d, isAjaxError) {
      var $wrap = $('#smec-test-result-wrap');
      if (!$wrap.length) {
        $wrap = $('<div id="smec-test-result-wrap" style="margin-top:16px;"></div>');
        $('#smec-test-notification').after($wrap);
      }

      var msg  = d.message || (isAjaxError ? smecAdmin.strings.error : 'Hotovo.');
      var ok   = d.success && !isAjaxError;
      var diag = d.diagnostics || {};

      var html = '<div class="smec-notice ' + (ok ? 'success' : 'error') + '" style="white-space:pre-line;">';
      html += SMEC.esc(msg);
      html += '</div>';

      // SMTP diagnostika
      if (diag) {
        html += '<div class="smec-help-note" style="margin-top:8px;font-size:0.8rem;">';
        html += '<strong>Diagnostika e-mailu:</strong> ';

        if (diag.smtp_plugin) {
          html += '✓ SMTP plugin: <strong>' + SMEC.esc(diag.smtp_plugin) + '</strong>. ';
        } else if (diag.is_localhost) {
          html += '⚠️ Bezite na <strong>localhostu</strong> bez SMTP pluginu – e-mail pravdepodobne neodejde. '
            + 'Nainstalujte plugin <strong>WP Mail SMTP</strong> nebo <strong>FluentSMTP</strong> a nastavte SMTP server. ';
        } else {
          html += '⚠️ SMTP plugin nebyl detekovan. Pokud e-mail neprichazi, nainstalujte WP Mail SMTP. ';
        }

        if (diag.admin_email) {
          html += 'Admin e-mail: ' + SMEC.esc(diag.admin_email) + '.';
        }

        html += '</div>';
      }

      $wrap.html(html);
    },

    updateFormMonitorBadge: function (missing) {
      var $badge = $('#smec-form-monitor .smec-badge');
      if (!$badge.length) return;
      if (missing > 0) {
        $badge.removeClass('smec-badge-active').addClass('smec-badge-inactive').text(missing + ' bez napojení');
      } else {
        $badge.removeClass('smec-badge-inactive').addClass('smec-badge-active').text('✓ vše napojeno');
      }
    },

    renderFormMonitor: function (forms, stats) {
      var $table  = $('#smec-form-monitor-table tbody');
      var $badge  = $('#smec-form-monitor .smec-badge');
      if (!$table.length) return;

      $table.empty();
      forms.filter(function (f) { return f.detectable && f.connected !== null; }).forEach(function (f) {
        var connected = !!f.connected;
        var statusHtml = connected
          ? '<span class="smec-form-ok">✓ ' + SMEC.esc(f.mapping_name || 'Napojeno') + '</span>'
          : '<span class="smec-form-warn">✗ Bez napojení</span>';

        var addUrl = smecAdmin.ajaxUrl.replace('admin-ajax.php', 'admin.php?page=smec-forms');
        var actionHtml = connected
          ? '<a href="' + SMEC.esc(addUrl) + '" class="button-link">Upravit →</a>'
          : '<a href="' + SMEC.esc(addUrl) + '" class="button button-small button-primary">+ Přidat napojení</a>';

        $table.append('<tr class="' + (connected ? '' : 'smec-row-warn') + '">'
          + '<td><strong>' + SMEC.esc(f.name) + '</strong></td>'
          + '<td><span class="smec-plugin-badge">' + SMEC.esc(f.plugin) + '</span></td>'
          + '<td><code>' + SMEC.esc(f.id) + '</code></td>'
          + '<td>' + statusHtml + '</td>'
          + '<td class="smec-row-actions">' + actionHtml + '</td>'
          + '</tr>');
      });

      // Aktualizovat badge
      if (stats.missing > 0) {
        $badge.removeClass('smec-badge-active').addClass('smec-badge-inactive').text(stats.missing + ' bez napojení');
      } else {
        $badge.removeClass('smec-badge-inactive').addClass('smec-badge-active').text('✓ vše napojeno');
      }
    },

    renderDiagnostics: function (issues, counts) {
      var hasCritical = (counts.critical || 0) > 0;
      var hasIssues   = (counts.critical || 0) + (counts.warning || 0) > 0;

      // Přepsat banner
      var bannerClass = hasIssues ? (hasCritical ? 'smec-diag-critical' : 'smec-diag-warning') : 'smec-diag-ok';
      var bannerIcon  = hasIssues ? (hasCritical ? '🔴' : '⚠️') : '✅';
      var bannerText  = hasIssues ? SMEC.diagSummaryText(counts) : '<strong>Vše v pořádku.</strong> Žádné problémy nebyly nalezeny.';

      var $banner = $('.smec-diag-banner');
      $banner.removeClass('smec-diag-critical smec-diag-warning smec-diag-ok').addClass(bannerClass);
      $banner.find('.smec-diag-banner-header > div').html(bannerText);
      $banner.find('.smec-diag-icon').text(bannerIcon);

      // Přepsat položky
      var $container = $('#smec-diag-issues');
      $container.empty();

      issues.forEach(function (issue) {
        var levelClass = 'smec-diag-item-' + issue.level;
        var icon = { critical: '🔴', warning: '⚠️', info: 'ℹ️' }[issue.level] || 'ℹ️';
        var actionsHtml = '';

        if (issue.link) {
          actionsHtml += '<a href="' + SMEC.esc(issue.link) + '" class="button button-small">' + SMEC.esc(issue.link_text || 'Opravit') + ' →</a>';
        }
        if (issue.action) {
          var a = issue.action;
          if (a.type === 'deactivate_plugin') {
            actionsHtml += '<button type="button" class="button button-small smec-action-deactivate-plugin smec-danger-btn" data-plugin="' + SMEC.esc(a.plugin) + '" data-label="' + SMEC.esc(a.label) + '">' + SMEC.esc(a.label) + '</button>';
          } else if (a.type === 'retry_queue') {
            actionsHtml += '<button type="button" class="button button-small" id="diag-retry-queue">' + SMEC.esc(a.label) + '</button>';
          } else if (a.type === 'process_queue') {
            actionsHtml += '<button type="button" class="button button-small" id="diag-process-queue">' + SMEC.esc(a.label) + '</button>';
          }
        }

        var html = '<div class="smec-diag-item ' + levelClass + (issue.level === 'critical' ? ' open' : '') + '" data-id="' + SMEC.esc(issue.id) + '">'
          + '<div class="smec-diag-item-header" role="button" tabindex="0">'
          + '<span class="smec-diag-item-icon">' + icon + '</span>'
          + '<span class="smec-diag-item-title">' + SMEC.esc(issue.title) + '</span>'
          + '<span class="smec-diag-item-toggle">▼</span>'
          + '</div>'
          + '<div class="smec-diag-item-body" ' + (issue.level !== 'critical' ? 'style="display:none"' : '') + '>'
          + '<p class="smec-diag-message">' + SMEC.esc(issue.message) + '</p>'
          + '<div class="smec-diag-solution"><span class="smec-diag-solution-label">💡 Doporučené řešení:</span><p>' + SMEC.esc(issue.solution) + '</p></div>'
          + '<div class="smec-diag-actions">' + actionsHtml + '</div>'
          + '</div></div>';

        $container.append(html);
      });
    },

    diagSummaryText: function (counts) {
      var parts = [];
      if (counts.critical) parts.push('<strong>' + counts.critical + ' kritický problém' + (counts.critical > 1 ? 'y' : '') + '</strong>');
      if (counts.warning)  parts.push(counts.warning + ' varování');
      if (counts.info)     parts.push(counts.info + ' informace');
      return parts.join(' &amp; ') + ' <span class="smec-muted">– kliknutím zobrazíte řešení</span>';
    },

    initRtPresetsPage: function () {
      if (!$('#rt-presets-list').length) return;

      SMEC.initTabs('#rt-preset-editor');
      SMEC.initRtColorSync();

      // ── Otevřít editor ───────────────────────────────────────
      $('#rt-add-preset').on('click', function () {
        SMEC.rtOpenEditor(null);
      });

      $(document).on('click', '.rt-edit-preset', function () {
        var id = $(this).data('id');
        SMEC.ajax('get_rt_presets', {}, function (d) {
          var p = (d.presets || []).find(function (x) { return x.id === id; });
          if (p) SMEC.rtOpenEditor(p);
        });
      });

      // ── Zavřít editor ─────────────────────────────────────────
      $('#rt-cancel-edit, #rt-cancel-edit2').on('click', function () {
        $('#rt-preset-editor').hide();
        $('#rt-presets-list').show();
      });

      // ── Smazat preset ─────────────────────────────────────────
      $(document).on('click', '.rt-delete-preset', function () {
        if (!confirm(smecAdmin.strings.confirm_delete)) return;
        var id  = $(this).data('id');
        var row = $('tr[data-id="' + id + '"]');
        SMEC.ajax('delete_rt_preset', { id: id },
          function () { row.fadeOut(200, function () { row.remove(); }); },
          function (d) { alert(d.message); }
        );
      });

      // ── Duplikovat preset ─────────────────────────────────────
      $(document).on('click', '.rt-duplicate-preset', function () {
        var id = $(this).data('id');
        SMEC.ajax('duplicate_rt_preset', { id: id }, function () { location.reload(); });
      });

      // ── Uložit preset ─────────────────────────────────────────
      $('#rt-save-preset').on('click', function () {
        var preset = SMEC.rtCollectPreset();
        SMEC.ajax('save_rt_preset', { preset: JSON.stringify(preset) },
          function (d) {
            SMEC.showResult('#rt-save-result', smecAdmin.strings.saved, 'success');
            setTimeout(function () { location.reload(); }, 1000);
          },
          function (d) { SMEC.showResult('#rt-save-result', d.message, 'error'); }
        );
      });

      // ── Slug auto-generování z názvu ──────────────────────────
      $('#rt-name').on('input', function () {
        var slug = $(this).val()
          .toLowerCase()
          .replace(/[^a-z0-9\s\-]/g, '')
          .trim()
          .replace(/[\s]+/g, '-');
        if (!$('#rt-slug').data('manual')) {
          $('#rt-slug').val(slug);
          $('.rt-slug-preview').text(slug || 'default');
        }
      });
      $('#rt-slug').on('input', function () {
        $(this).data('manual', true);
        $('.rt-slug-preview').text($(this).val() || 'default');
      });

      // ── Přepínání ikony ───────────────────────────────────────
      $('[name="rt-icon-type"]').on('change', function () {
        SMEC.rtUpdateIconUI($(this).val());
      });

      // ── SVG náhled ────────────────────────────────────────────
      $('#rt-custom-svg').on('input', function () {
        SMEC.rtUpdateSvgPreview($(this).val());
      });

      // ── Live preview (label, suffix) ──────────────────────────
      $('#rt-label, #rt-suffix').on('input', function () {
        $('#rt-live-preview .smec-rt-label').text($('#rt-label').val() + ' ');
        $('#rt-live-preview .smec-rt-suffix').text(' ' + $('#rt-suffix').val());
      });
    },

    rtOpenEditor: function (preset) {
      $('#rt-presets-list').hide();
      $('#rt-preset-editor').show();

      if (!preset) {
        $('#rt-editor-title').text('Nový preset');
        $('#rt-preset-id').val('');
        $('#rt-name').val('').removeData('manual');
        $('#rt-slug').val('').removeData('manual');
        $('.rt-slug-preview').text('default');
        $('#rt-wpm').val(200);
        $('#rt-label').val('Doba čtení:');
        $('#rt-suffix').val('min');
        $('#rt-show-icon').prop('checked', true);
        $('[name="rt-icon-type"][value="clock"]').prop('checked', true);
        SMEC.rtUpdateIconUI('clock');
        $('#rt-custom-svg').val('');
        $('#rt-wrapper-tag').val('span');
        $('#rt-display').val('inline');
        $('#rt-css-class').val('');
        SMEC.rtClearVisual();
        $('#rt-auto-insert').prop('checked', false);
        $('[name="rt-auto-position"][value="before"]').prop('checked', true);
        $('.rt-pt-checkbox').prop('checked', false);
      } else {
        $('#rt-editor-title').text('Upravit preset: ' + (preset.name || ''));
        $('#rt-preset-id').val(preset.id || '');
        $('#rt-name').val(preset.name || '');
        $('#rt-slug').val(preset.slug || '').data('manual', true);
        $('.rt-slug-preview').text(preset.slug || 'default');
        $('#rt-wpm').val(preset.wpm || 200);
        $('#rt-label').val(preset.label || 'Doba čtení:');
        $('#rt-suffix').val(preset.suffix || 'min');
        $('#rt-show-icon').prop('checked', !!preset.show_icon);

        var iconType = preset.icon === 'custom' ? 'custom' : 'clock';
        $('[name="rt-icon-type"][value="' + iconType + '"]').prop('checked', true);
        SMEC.rtUpdateIconUI(iconType);
        $('#rt-custom-svg').val(preset.custom_svg || '');
        if (preset.custom_svg) SMEC.rtUpdateSvgPreview(preset.custom_svg);

        $('#rt-wrapper-tag').val(preset.wrapper_tag || 'span');
        $('#rt-display').val(preset.display || 'inline');
        $('#rt-css-class').val(preset.css_class || '');

        // Vizuální pole
        SMEC.rtFillColor('rt-v-color',      'rt-v-color-hex',      preset.color || '');
        SMEC.rtFillColor('rt-v-icon-color',  'rt-v-icon-color-hex', preset.icon_color || '');
        SMEC.rtFillColor('rt-v-bg',          'rt-v-bg-hex',         preset.background || '');
        $('#rt-v-font-size').val(preset.font_size || '');
        $('#rt-v-font-weight').val(preset.font_weight || '');
        $('#rt-v-padding').val(preset.padding || '');
        $('#rt-v-border-radius').val(preset.border_radius || '');
        $('#rt-v-text-align').val(preset.text_align || '');
        $('#rt-v-icon-size').val(preset.icon_size || '');
        $('#rt-v-custom-css').val(preset.custom_css || '');

        // Auto-insert
        $('#rt-auto-insert').prop('checked', !!preset.auto_insert);
        $('[name="rt-auto-position"][value="' + (preset.auto_position || 'before') + '"]').prop('checked', true);
        $('.rt-pt-checkbox').prop('checked', false);
        (preset.post_types || []).forEach(function (pt) {
          $('.rt-pt-checkbox[value="' + pt + '"]').prop('checked', true);
        });
      }

      // Update live preview
      $('#rt-live-preview .smec-rt-label').text($('#rt-label').val() + ' ');
      $('#rt-live-preview .smec-rt-suffix').text(' ' + $('#rt-suffix').val());

      $('html, body').animate({ scrollTop: $('#rt-preset-editor').offset().top - 60 }, 300);
    },

    rtCollectPreset: function () {
      var postTypes = [];
      $('.rt-pt-checkbox:checked').each(function () { postTypes.push($(this).val()); });

      return {
        id:            $('#rt-preset-id').val(),
        name:          $('#rt-name').val(),
        slug:          $('#rt-slug').val(),
        wpm:           parseInt($('#rt-wpm').val(), 10) || 200,
        label:         $('#rt-label').val(),
        suffix:        $('#rt-suffix').val(),
        show_icon:     $('#rt-show-icon').is(':checked') ? 1 : 0,
        icon:          $('[name="rt-icon-type"]:checked').val() || 'clock',
        custom_svg:    $('#rt-custom-svg').val(),
        wrapper_tag:   $('#rt-wrapper-tag').val(),
        display:       $('#rt-display').val(),
        css_class:     $('#rt-css-class').val(),
        color:         $('#rt-v-color-hex').val(),
        icon_color:    $('#rt-v-icon-color-hex').val(),
        background:    $('#rt-v-bg-hex').val(),
        font_size:     $('#rt-v-font-size').val(),
        font_weight:   $('#rt-v-font-weight').val(),
        padding:       $('#rt-v-padding').val(),
        border_radius: $('#rt-v-border-radius').val(),
        text_align:    $('#rt-v-text-align').val(),
        icon_size:     $('#rt-v-icon-size').val(),
        custom_css:    $('#rt-v-custom-css').val(),
        auto_insert:   $('#rt-auto-insert').is(':checked') ? 1 : 0,
        auto_position: $('[name="rt-auto-position"]:checked').val() || 'before',
        post_types:    postTypes,
      };
    },

    rtUpdateIconUI: function (type) {
      if (type === 'custom') {
        $('#rt-builtin-preview-row').hide();
        $('#rt-custom-svg-row').show();
      } else {
        $('#rt-builtin-preview-row').show();
        $('#rt-custom-svg-row').hide();
      }
    },

    rtUpdateSvgPreview: function (svg) {
      if (!svg.trim()) { $('#rt-svg-preview').hide(); return; }
      var $preview = $('#rt-svg-preview');
      $preview.show();
      // Bezpečné zobrazení: pouze pro admin, strippujeme script tagy
      var safe = svg.replace(/<script[\s\S]*?<\/script>/gi, '');
      $preview.html('<strong>Náhled:</strong> ' + safe);
    },

    rtClearVisual: function () {
      ['rt-v-color','rt-v-icon-color','rt-v-bg'].forEach(function (id) {
        $('#' + id).val('');
        $('#' + id + '-hex').val('');
      });
      $('#rt-v-font-size, #rt-v-font-weight, #rt-v-padding, #rt-v-border-radius, #rt-v-icon-size').val('');
      $('#rt-v-text-align').val('');
      $('#rt-v-custom-css').val('');
    },

    rtFillColor: function (colorId, hexId, value) {
      if (value) { $('#' + colorId).val(value); $('#' + hexId).val(value); }
      else { $('#' + colorId).val(''); $('#' + hexId).val(''); }
    },

    initRtColorSync: function () {
      // Synchronizace color picker ↔ hex input
      $(document).on('input', '[data-hex]', function () {
        var hexId = $(this).data('hex');
        $('#' + hexId).val($(this).val());
      });
      $(document).on('input', '.smec-hex-input', function () {
        var id  = $(this).attr('id').replace('-hex', '');
        var val = $(this).val();
        if (/^#[0-9a-fA-F]{6}$/.test(val)) {
          $('#' + id).val(val);
        }
      });
    },

    // ─────────────────────────────────────────────────────────────
    //  WOOCOMMERCE PAGE
    // ─────────────────────────────────────────────────────────────
    initWoocommercePage: function () {
      $('#smec-save-woocommerce').on('click', function () {
        var statuses = [];
        $('[name="import_on_status[]"]:checked').each(function () { statuses.push($(this).val()); });

        var cfMap = [];
        $('#woo-cf-body tr:visible').each(function () {
          var fid    = $(this).find('.smec-cf-field-id').val();
          var source = $(this).find('.smec-source-select').val();
          var value  = $(this).find('.smec-field-value').val();
          if (fid) cfMap.push({ field_id: parseInt(fid, 10), source: source, value: value });
        });

        var tagsRaw = $('#woo-tags-input').val().split(',').map(function (t) { return t.trim(); }).filter(Boolean);

        var data = {
          data: JSON.stringify({
            enabled:              $('#woo-enabled').is(':checked') ? 1 : 0,
            list_id:              parseInt($('#woo-list-id').val(), 10) || 0,
            require_optin:        $('#woo-require-optin').is(':checked') ? 1 : 0,
            optin_label:          $('#woo-optin-label').val(),
            import_on_status:     statuses,
            status:               $('#woo-status').val(),
            tags:                 tagsRaw,
            custom_field_mapping: cfMap,
          })
        };
        SMEC.ajax('save_woocommerce', data,
          function () { SMEC.showResult('#smec-woo-result', smecAdmin.strings.saved, 'success'); },
          function (d) { SMEC.showResult('#smec-woo-result', d.message, 'error'); }
        );
      });

      $('#woo-add-cf-row').on('click', function () {
        var tpl = $('#woo-cf-body tr:last').clone();
        tpl.find('input, select').val('');
        $('#woo-cf-body').append(tpl);
      });

      $(document).on('click', '.smec-remove-row', function () { $(this).closest('tr').remove(); });
    },

    // ─────────────────────────────────────────────────────────────
    //  Pagination – generic
    // ─────────────────────────────────────────────────────────────
    initPagination: function () {
      $(document).on('click', '.smec-page-btn', function () {
        var page = parseInt($(this).data('page'), 10);
        SMEC.logsOffset = page * SMEC.logsLimit;
        SMEC.loadLogs();
      });
    },

    // ─────────────────────────────────────────────────────────────
    //  Utility
    // ─────────────────────────────────────────────────────────────
    esc: function (str) {
      return String(str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    },

    // ─────────────────────────────────────────────────────────────
    //  Init
    // ─────────────────────────────────────────────────────────────
    init: function () {
      SMEC.initPagination();

      // API page
      if ($('#smec-save-api').length)          SMEC.initApiPage();
      // Webtracking page
      if ($('#smec-save-webtracking').length)  SMEC.initWebtackingPage();
      // Lists page
      if ($('#smec-fetch-lists').length)       SMEC.initListsPage();
      // Forms page
      if ($('#smec-mappings-list').length)     SMEC.initFormsPage();
      // Logs page
      if ($('#smec-load-logs').length)         SMEC.initLogsPage();
      // Settings page
      if ($('#smec-save-general').length)      SMEC.initSettingsPage();
      // Reading time page (presety)
      if ($('#rt-presets-list').length || $('#rt-preset-editor').length) SMEC.initReadingTimePage();
      // Overview / diagnostics
      if ($('#smec-diag-issues').length || $('.smec-diag-banner').length) SMEC.initDiagnostics();
      // Notifications page
      if ($('#smec-save-notifications').length) SMEC.initNotificationsPage();
      // WooCommerce page
      if ($('#smec-save-woocommerce').length)  SMEC.initWoocommercePage();
    }
  };

  $(document).ready(function () {
    SMEC.init();
  });

})(jQuery);
