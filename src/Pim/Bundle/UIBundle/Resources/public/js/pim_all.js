function init() {
    'use strict';
    // Place code that we need to run on every page load here

    // Disable the oro scrollable container
    $('.scrollable-container').removeClass('scrollable-container').css('overflow', 'visible');

    // Move scope filter to the proper location and remove it from the 'Manage filters' selector
    // TODO: Override Oro/Bundle/FilterBundle/Resources/public/js/app/filter/list.js and manage this there
    Oro.Events.once('datagrid_filters:rendered', function () {
        $('.scope-filter').parent().addClass('pull-right').insertBefore($('.actions-panel'));
        $('.scope-filter').find('select').multiselect({classes: 'select-filter-widget scope-filter-select'});

        $('#add-filter-select').find('option[value="scope"]').remove();
        $('#add-filter-select').multiselect('refresh');
    });

    $('.remove-attribute').each(function () {
        var target = $(this).parent().find('.icons-container').first();
        if (target.length) {
            $(this).appendTo(target).attr('tabIndex', -1);
        }
    });

    // Apply Select2
    Pim.initSelect2();

    // Apply bootstrapSwitch
    $('.switch:not(.has-switch)').bootstrapSwitch();

    // Destroy Select2 where it's not necessary
    $('#default_channel').select2('destroy');

    // Activate a form tab
    $('li.tab.active a').each(function () {
        var paneId = $(this).attr('href');
        $(paneId).addClass('active');
    });

    // Toogle accordion icon
    $('.accordion').on('show hide', function (e) {
        $(e.target).siblings('.accordion-heading').find('.accordion-toggle i').toggleClass('fa-icon-collapse-alt fa-icon-expand-alt');
    });

    $('#attribute-buttons .dropdown-menu').click(function (e) {
        e.stopPropagation();
    });

    $('#default_channel').change(function () {
        Oro.Events.trigger('scopablefield:changescope', $(this).val());
    });

    $('.dropdown-menu.channel a').click(function (e) {
        e.preventDefault();
        Oro.Events.trigger('scopablefield:' + $(this).data('action'));
    });

    // Clean up multiselect plugin generated content that is appended to body
    $('body>.ui-multiselect-menu').appendTo($('#container'));

    // DELETE request for delete buttons
    $('[data-dialog]').on('click', function () {
        var $el      = $(this),
            message  = $el.data('message'),
            title    = $el.data('title'),
            doAction = function () {
                $el.off('click');
                var $form = $('<form>', { method: 'POST', action: $el.attr('data-url')});
                $('<input>', { type: 'hidden', name: '_method', value: $el.data('method')}).appendTo($form);
                $form.appendTo('body').submit();
            };
        if ($el.data('dialog') ===  'confirm') {
            PimDialog.confirm(message, title, doAction);
        } else {
            PimDialog.alert(message, title);
        }
    });

    // Save and restore activated form tabs and groups
    function saveFormState() {
        var activeTab   = $('#form-navbar .nav li.active a').attr('href'),
            activeGroup = $('.tab-groups li.tab.active a').attr('href');

        if (activeTab) {
            sessionStorage.activeTab = activeTab;
        }

        if (activeGroup) {
            sessionStorage.activeGroup = activeGroup;
        }
    }

    function restoreFormState() {
        if (sessionStorage.activeTab) {
            var $activeTab = $('[href=' + sessionStorage.activeTab + ']');
            if ($activeTab) {
                $activeTab.tab('show');
            }
            sessionStorage.removeItem('activeTab');
        }

        if (sessionStorage.activeGroup) {
            var $activeGroup = $('[href=' + sessionStorage.activeGroup + ']');
            if ($activeGroup) {
                $activeGroup.tab('show');
            }
            sessionStorage.removeItem('activeGroup');
        }
    }

    if (typeof Storage !== 'undefined') {
        restoreFormState();

        $('form.form-horizontal').on('submit', saveFormState);
        $('#locale-switcher a').on('click', saveFormState);
    }

    // Initialize slimbox
    if (!/android|iphone|ipod|series60|symbian|windows ce|blackberry/i.test(navigator.userAgent)) {
        $("a[rel^='lightbox']").slimbox({
            overlayOpacity: 0.3
        }, null, function (el) {
            return (this === el) || ((this.rel.length > 8) && (this.rel === el.rel));
        });
    }

    var $localizableIcon = $('<i>', {
        'class': 'fa-icon-globe',
        'attr': {
            'data-original-title': _.__('Localized value'),
            'data-toggle': 'tooltip',
            'data-placement': 'right'
        }
    });
    $('.attribute-field.translatable').each(function () {
        $(this).find('div.controls .icons-container').append($localizableIcon.clone());
    });

    $('form').on('change', 'input[type="file"]', function () {
        var $input          = $(this),
            filename        = $input.val().split('\\').pop(),
            $zone           = $input.parent(),
            $info           = $input.siblings('.upload-info').first(),
            $filename       = $info.find('.upload-filename'),
            $removeBtn      = $input.siblings('.remove-upload'),
            $removeCheckbox = $input.siblings('input[type="checkbox"]'),
            $preview        = $info.find('.upload-preview');

        if ($preview.prop('tagName').toLowerCase() !== 'i') {
            var iconClass = $zone.hasClass('image') ? 'fa-icon-camera-retro' : 'fa-icon-file';
            $preview.replaceWith($('<i>', { 'class': iconClass + ' upload-preview'}));
            $preview = $info.find('.upload-preview');
        }

        if (filename) {
            $filename.html(filename);
            $zone.removeClass('empty');
            $preview.removeClass('empty');
            $removeBtn.removeClass('hide');
            $input.attr('disabled', 'disabled').addClass('hide');
            $removeCheckbox.removeAttr('checked');
        } else {
            $filename.html($filename.attr('data-empty-title'));
            $zone.addClass('empty');
            $preview.addClass('empty');
            $removeBtn.addClass('hide');
            $input.removeAttr('disabled').removeClass('hide');
            $removeCheckbox.attr('checked', 'checked');
        }
    });

    $('form').on('submit', function () {
        $('input[type="file"]').removeAttr('disabled');
    });

    $('.remove-upload').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $input = $(this).siblings('input[type="file"]').first();
        $input.wrap('<form>').closest('form').get(0).reset();
        $input.unwrap().trigger('change');
    });

    $('[data-form-toggle]').on('click', function () {
        $('#' + $(this).attr('data-form-toggle')).show();
        $(this).hide();
    });
}

$(function () {
    'use strict';

    $(window).off('beforeunload');

    // Execute the init function on page load
    init();
});
