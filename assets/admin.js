/* Dynamic Location Menus — admin UI helpers */
(function($){
  'use strict';

  $(document).ready(function(){

    // ---- Tab switching ----
    $('.nav-tab').on('click', function(e){
      e.preventDefault();
      var tab = $(this).data('tab');
      $('.nav-tab').removeClass('nav-tab-active');
      $(this).addClass('nav-tab-active');
      $('.dlm-tab').hide();
      $('.dlm-tab[data-tab="' + tab + '"]').show();
      history.replaceState(null, '', '#tab-' + tab);
    });
    var hash = (window.location.hash || '').replace('#tab-','');
    var urlTab = new URLSearchParams(window.location.search).get('tab');
    var initial = urlTab || hash;
    if (initial) {
      var $t = $('.nav-tab[data-tab="' + initial + '"]');
      if ($t.length) $t.trigger('click');
    }

    // ---- Repeater: add row ----
    $('.dlm-add').on('click', function(){
      var which = $(this).data('template');
      var tpl = $('#dlm-tpl-' + which).html();
      var $table = $(this).closest('p').prev('table.dlm-repeater');
      if (!$table.length) {
        $table = $('.dlm-repeater[data-repeater="' + (which === 'location' ? 'locations' : 'slots') + '"]');
      }
      var nextIdx = $table.find('tbody tr').length;
      var html = tpl.replace(/__i__/g, nextIdx);
      $table.find('tbody').append(html);
    });

    // ---- Repeater: remove row ----
    $(document).on('click', '.dlm-remove', function(){
      var $row = $(this).closest('tr');
      var $tbody = $row.closest('tbody');
      $row.remove();
      $tbody.find('tr').each(function(i){
        $(this).find('input').each(function(){
          var name = $(this).attr('name');
          if (!name) return;
          $(this).attr('name', name.replace(/\[\d+\]/, '[' + i + ']'));
        });
      });
    });

    // ---- Advanced patterns toggle ----
    $('input[name$="[pattern_advanced]"]').on('change', function(){
      $('.dlm-advanced').toggle(this.checked);
    });

    // ---- Clone tab: target selection ----
    $('.dlm-select-all-targets').on('click', function(e){
      e.preventDefault();
      $('.dlm-target-grid input[type=checkbox]').prop('checked', true);
    });
    $('.dlm-clear-targets').on('click', function(e){
      e.preventDefault();
      $('.dlm-target-grid input[type=checkbox]').prop('checked', false);
    });
    $('.dlm-select-missing-targets').on('click', function(e){
      e.preventDefault();
      var slot = $('#dlm-slot').val();
      var missing = (window.dlmAdmin && dlmAdmin.missingBySlot && dlmAdmin.missingBySlot[slot]) || [];
      $('.dlm-target-grid input[type=checkbox]').each(function(){
        $(this).prop('checked', missing.indexOf($(this).val()) !== -1);
      });
    });

    // ---- Consistency tab: force reload when scanning the same location again ----
    $('.dlm-audit-scan-form').on('submit', function(){
      var $form = $(this);
      var location = $form.find('[name="audit_location"]').val();
      if (!location) {
        return;
      }
      var $ts = $form.find('input[name="audit_ts"]');
      if (!$ts.length) {
        $ts = $('<input type="hidden" name="audit_ts">').appendTo($form);
      }
      $ts.val(String(Date.now()));
    });

  });

})(jQuery);