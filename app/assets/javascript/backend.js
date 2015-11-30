(function ($) {
    $.fn.timeRange = function (selectCallback) {
        var el = this;

        var ranges = {
            "Today": [moment().startOf('day'), moment().endOf('day')],
            "Yesterday": [moment().subtract('days', 1).startOf('day'), moment().subtract('days', 1).endOf('day')],
            "Last 7 Days": [moment().subtract('days', 6).startOf('day'), moment().endOf('day')],
            "Last 30 Days": [moment().subtract('days', 29).startOf('day'), moment().endOf('day')],
            "This Month": [moment().startOf('month'), moment().endOf('month')],
            "Last Month": [moment().subtract('month', 1).startOf('month'), moment().subtract('month', 1).endOf('month')],
            "All Time": [null, null]
        };

        var allTimeRangeName = "All Time";

        var getMonthNames = function () {
            var res = [];
            for (var i = 0; i < 12; i++) {
                res.push(moment([0, i]).format('MMM'));
            }
            return res;
        };

        var locale = {
            customRangeLabel: "Custom Range",
            daysOfWeek: moment.langData()._weekdaysMin.slice(),
            monthNames: getMonthNames(),
            firstDay: moment.langData()._week.dow
        };

        var dateFormat = 'LL';

        var updateLabel = function (start, end) {
            if (start === null && end === null) {
                el.find('span').html(allTimeRangeName);
            } else {
                el.find('span').html(start.format(dateFormat) + ' - ' + end.format(dateFormat));

                _.find(ranges, function (period, name) {
                    if ((moment(period[0]).format('l') === start.format('l')) && (moment(period[1]).format('l') === end.format('l'))) {
                        el.find('span').html(name);
                        return true;
                    }
                    return false;
                });
            }
        };

        el.daterangepicker({
            showDropdowns: true,
            autoApply: true,
            ranges: ranges,
            startDate: null,
            endDate: null,
            format: dateFormat,
            locale: locale
        }, function (start, end) {
            updateLabel(start, end);
            selectCallback(start, end);
        });

        updateLabel(null, null);
    };
})(jQuery);