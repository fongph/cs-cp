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

        var allTimeRangeLabel = "All Time";

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

        var updateLabel = function (start, end, label) {
            if (label === allTimeRangeLabel) {
                el.find('span').html(allTimeRangeLabel);
            } else if (label !== locale.customRangeLabel) {
                el.find('span').html(label);
            } else {
                el.find('span').html(start.format(dateFormat) + ' - ' + end.format(dateFormat));
            }
        };

        el.daterangepicker({
            showDropdowns: true,
            autoApply: true,
            linkedCalendars: false,
            ranges: ranges,
            startDate: null,
            endDate: null,
            format: dateFormat,
            locale: locale
        }, function (start, end, label) {
            updateLabel(start, end, label);

            if (label === allTimeRangeLabel) {
                selectCallback(null, null);
            } else {
                selectCallback(start, end);
            }
        });

        updateLabel(null, null, allTimeRangeLabel);
    };
})(jQuery);