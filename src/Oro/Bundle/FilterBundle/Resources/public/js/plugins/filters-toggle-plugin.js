define(function(require) {
    'use strict';

    var FiltersTogglePlugin;
    var _ = require('underscore');
    var __ = require('orotranslation/js/translator');
    var BasePlugin = require('oroui/js/app/plugins/base/plugin');
    var ToggleFiltersAction = require('orofilter/js/actions/toggle-filters-action');

    var config = require('module').config();
    config.launcherOptions = _.extend({
        icon: 'filter',
        label: __('oro.filter.datagrid-toolbar.filters')
    }, config.launcherOptions);

    FiltersTogglePlugin = BasePlugin.extend({
        enable: function() {
            this.listenTo(this.main, 'beforeToolbarInit', this.onBeforeToolbarInit);
            FiltersTogglePlugin.__super__.enable.call(this);
        },

        onBeforeToolbarInit: function(toolbarOptions) {
            var options = {
                datagrid: this.main,
                launcherOptions: _.extend({
                    className: 'btn'
                }, config.launcherOptions),
                order: config.order || 50
            };

            toolbarOptions.addToolbarAction(new ToggleFiltersAction(options));
        }
    });

    return FiltersTogglePlugin;
});
