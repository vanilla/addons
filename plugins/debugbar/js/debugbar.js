(function($) {
    var csscls = PhpDebugBar.utils.makecsscls('phpdebugbar-widgets-');
    var createCodeBlock = PhpDebugBar.Widgets.createCodeBlock;

    /**
     * Widget for the MessagesCollector
     *
     * Uses ListWidget under the hood
     *
     * Options:
     *  - data
     */
    var VanillaLoggerWidget = PhpDebugBar.Widgets.VanillaLoggerWidget = PhpDebugBar.Widget.extend({

        className: csscls('messages'),

        render: function() {
            var self = this;

            this.$list = new PhpDebugBar.Widgets.ListWidget({
                itemRenderer: function(li, value) {
                    li.addClass(csscls('log'));

                    if (value.label) {
                        li.addClass(csscls(value.label));
                        $('<span />').addClass(csscls('label')).text(value.label).appendTo(li);
                    }

                    var m = value.message;
                    var message = $('<div />').addClass(csscls('message')).text(m).appendTo(li);

                    if (value.event) {
                        $('<b />').addClass(csscls('event')).text(value.event+':').prependTo(message);
                    }

                    if (value.context) {
                        var json = createCodeBlock(value.context, 'json');
                        json.addClass(csscls('context')).appendTo(li)
                    }

                    if (value.collector) {
                        $('<span />').addClass(csscls('collector')).text(value.collector).appendTo(li);
                    }

                    li.click(function() {
                        $(this).toggleClass(csscls('expanded'));
                    });
                }
            });

            this.$list.$el.appendTo(this.$el);
            this.$toolbar = $('<div><i class="phpdebugbar-fa phpdebugbar-fa-search"></i></div>').addClass(csscls('toolbar')).appendTo(this.$el);

            $('<input type="text" />')
                .on('change', function() {
                    self.set('search', this.value);
                })
                .appendTo(this.$toolbar);

            this.bindAttr('data', function(data) {
                this.set({exclude: [], search: ''});
                this.$toolbar.find(csscls('.filter')).remove();

                var filters = [], self = this;
                for (var i = 0; i < data.length; i++) {
                    if (!data[i].label || $.inArray(data[i].label, filters) > -1) {
                        continue;
                    }
                    filters.push(data[i].label);
                    $('<a />')
                        .addClass(csscls('filter'))
                        .text(data[i].label)
                        .attr('rel', data[i].label)
                        .on('click', function() {
                            self.onFilterClick(this);
                        })
                        .appendTo(this.$toolbar);
                }
            });

            this.bindAttr(['exclude', 'search'], function() {
                var data = this.get('data'),
                    exclude = this.get('exclude'),
                    search = this.get('search'),
                    caseless = false,
                    fdata = [];

                if (search && search === search.toLowerCase()) {
                    caseless = true;
                }

                for (var i = 0; i < data.length; i++) {
                    var message = caseless ? data[i].message.toLowerCase() : data[i].message;

                    if ((!data[i].label || $.inArray(data[i].label, exclude) === -1) && (!search || message.indexOf(search) > -1)) {
                        fdata.push(data[i]);
                    }
                }

                this.$list.set('data', fdata);
            });
        },

        onFilterClick: function(el) {
            $(el).toggleClass(csscls('excluded'));

            var excludedLabels = [];
            this.$toolbar.find(csscls('.filter') + csscls('.excluded')).each(function() {
                excludedLabels.push(this.rel);
            });

            this.set('exclude', excludedLabels);
        }

    });
})(PhpDebugBar.$);