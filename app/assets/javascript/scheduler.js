(function ($) {
    var SchedulerElement = function (element, opts) {
        var id = element.attr('id'),
                self = this,
                options = $.extend({}, {
                    close: true,
                    sliderStep: 300,
                    errorMessage: "You must select one or more days of the week",
                    defaultDays: ['MO', 'TU', 'WE', 'TH', 'FR'],
                    defaultPeriod: [28800, 72000]
                }, opts);

        element.append('<div id="' + id + '-slider"></div>')
                .append('<div id="' + id + '-humanValue" class="scheduler-humanValue"></div>')
                .append('<table id="' + id + '-weekDays" class="scheduler-days"><tr></tr><tr></tr></table>')
                .append('<p class="text-danger">' + options.errorMessage + '</p>');

        if (options.close) {
            element.prepend('<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>');
        }

        this.getElement = function () {
            return element;
        };

        this.getId = function () {
            return id;
        };

        this.slider = this.getSliderBlock().slider({
            step: options.sliderStep,
            value: options.defaultPeriod,
            min: 0,
            max: 86400,
            tooltip: 'hide'
        }).on('slide', function (e) {
            self.updateRange(e.value[0], e.value[1]);
        }).data('slider');

        this.updateRange(options.defaultPeriod[0], options.defaultPeriod[1]);

        for (var i = moment.localeData()._week.dow; i < moment.localeData()._week.dow + 7; i++) {
            var name = moment().isoWeekday(i).format('ddd');
            var value = moment().locale('en').isoWeekday(i).format('dd').toUpperCase();
            self.getWeekDaysBlock().find("tr:nth-child(1)").append('<td><label for="' + id + value + '">' + name + '</label></td>');
            if (_.indexOf(options.defaultDays, value) !== -1) {
                self.getWeekDaysBlock().find("tr:nth-child(2)").append('<td><input type="checkbox" value="" checked="checked" id="' + id + value + '" /></td>');
            } else {
                self.getWeekDaysBlock().find("tr:nth-child(2)").append('<td><input type="checkbox" value="" id="' + id + value + '" /></td>');
            }
        }

        element.find('input[type=checkbox]').change(function () {
            self.checkDaySelection();
        });

        element.find('.close').click(function () {
            element.remove();
        });

        element.data('schedulerElement', this);
    };

    SchedulerElement.prototype = {
        constructor: Scheduler,
        getSliderBlock: function () {
            return $('#' + this.getId() + '-slider');
        },
        getHumanValueBlock: function () {
            return $('#' + this.getId() + '-humanValue');
        },
        getWeekDaysBlock: function () {
            return $('#' + this.getId() + '-weekDays');
        },
        getHumanValue: function (from, to) {
            return moment.unix(from).utcOffset(0).format("LT") + ' - ' +
                    moment.unix(to).utcOffset(0).format("LT");
        },
        getSelectedDays: function () {
            var list = [],
                    id = this.getId();

            $.each(this.getDaysList(), function () {
                if ($('#' + id + this + ':checked').size()) {
                    list.push(this.toString());
                }
            });

            return list;
        },
        updateRange: function (from, to) {
            this.getHumanValueBlock().html(this.getHumanValue(from, to));
        },
        getDaysList: function () {
            var list = [];
            for (var i = 0; i < 7; i++) {
                list.push(moment().locale('en').isoWeekday(i).format('dd').toUpperCase());
            }
            return list;
        },
        checkDaySelection: function () {
            if (this.getSelectedDays().length === 0) {
                this.getElement().addClass('error');
            } else {
                this.getElement().removeClass('error');
            }
        },
        serialize: function () {
            var days = this.getSelectedDays();

            if (days.length === 0) {
                throw new Error("At least one day must be selected!");
            }

            var sliderValue = this.slider.getValue();
            
            sliderValue = [sliderValue[0], sliderValue[1]];

            return sliderValue.join('|') + '|' + days.join(',');
        },
        deserialize: function (value) {
            var parts = value.split('|');
            if (parts.length !== 3) {
                throw new Error("Invalid value format!");
            }

            var days = parts[2].split(',');

            this.slider.setValue([parseInt(parts[0], 10), parseInt(parts[1], 10)]);
            this.updateRange(parseInt(parts[0], 10), parseInt(parts[1], 10));

            var daysList = this.getDaysList(),
                    id = this.getId();

            $.each(daysList, function () {
                if (days.indexOf(this.toString()) !== -1) {
                    $('#' + id + this).prop("checked", true);
                } else {
                    $('#' + id + this).prop("checked", false);
                }
            });

            this.checkDaySelection();
        }
    };

    var Scheduler = function (element, options) {
        var num = 0,
                id = element.attr('id'),
                self = this,
                options = $.extend({}, {
                    sliderStep: 300,
                    close: true,
                    errorMessage: "You must select one or more days of the week",
                    addMore: "Add more"
                }, options);

        if (id === undefined) {
            throw new Error('Id must be provided');
        }

        this.getId = function () {
            return id;
        };

        this.getOptions = function () {
            return options;
        };

        this.getElement = function () {
            return element;
        };

        this.getNextElementNumber = function () {
            return ++num;
        };

        element.append('<div class="scheduler-container"></div>')
                .append('<div class="text-right"><a href="" class="btn btn-default add-more"><i class="fa fa-plus"></i> ' + options.addMore + '</a></div>');

        element.find('.add-more').click(function (e) {
            e.preventDefault();
            self.addElement();
        });
        
        this.empty();
    };

    Scheduler.prototype = {
        constructor: Scheduler,
        getContainer: function () {
            return this.getElement().find('.scheduler-container');
        },
        getElements: function () {
            return this.getContainer().find('.scheduler-element');
        },
        addElement: function (options) {
            var num = this.getNextElementNumber();
            var item = this.getContainer()
                    .append('<div class="scheduler-element" id="' + this.getId() + num + '"></div>')
                    .find('.scheduler-element:last');

            new SchedulerElement(item, $.extend({}, this.getOptions(), options));
        },
        serialize: function () {
            var data = [];

            $.each(this.getElements(), function () {
                data.push($(this).data('schedulerElement').serialize());
            });

            return data.join('@');
        },
        deserialize: function (value) {
            var items = value.split('@'),
                    elements = this.getElements(),
                    self = this;

            if (items.length > elements.length) {
                for (var i = 0; i < (items.length - elements.length); i++) {
                    this.addElement();
                }
                elements = this.getElements();
            }

            $.each(elements, function (index, element) {
                if (index < items.length) {
                    try {
                        $(element).data('schedulerElement').deserialize(items[index]);
                    } catch (e) {
                        self.empty();
                        throw e;
                    }
                } else {
                    element.remove();
                }
            });
        },
        empty: function () {
            $.each(this.getElements(), function () {
                $(this).remove();
            });

            this.addElement({
                close: false
            });
        }
    };

    window.Scheduler = Scheduler;
})(window.jQuery);