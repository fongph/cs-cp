$.fn.tableScroll = function () {
    var scrollStartPos = 0,
            start = false;

    $(this).on("touchstart", function (event) {
        if (event.originalEvent && event.originalEvent.touches && event.originalEvent.touches.length === 1) {
            start = true;
            scrollStartPos = this.scrollLeft + event.originalEvent.touches[0].pageX;
        } else {
            start = false;
        }
    });

    $(this).on("touchmove", function (event) {
        if (start) {
            this.scrollLeft = scrollStartPos - event.originalEvent.touches[0].pageX;
            //event.preventDefault();
        }
    });
};

$(document).ready(function () {
    $('#toggle').on('click touchend', function (e) {
        e.preventDefault();
        $('body').toggleClass('menu-active');
    });
});

var languages = {
    "en-GB": {
        "sEmptyTable": "No data available in table",
        "sInfo": "Showing _START_ to _END_ of _TOTAL_ entries",
        "sInfoEmpty": "Showing 0 to 0 of 0 entries",
        "sInfoFiltered": "(filtered from _MAX_ total entries)",
        "sInfoPostFix": "",
        "sInfoThousands": ",",
        "sLengthMenu": "Records per page: _MENU_",
        "sLoadingRecords": "Loading...",
        "sProcessing": "Processing...",
        "sSearch": "Search:",
        "sZeroRecords": "No matching records found",
        "oPaginate": {
            "sFirst": "First",
            "sLast": "Last",
            "sNext": "Next",
            "sPrevious": "Previous"
        },
        "oAria": {
            "sSortAscending": ": activate to sort column ascending",
            "sSortDescending": ": activate to sort column descending"
        }
    }
};

localAsUtc = function (m) {
    return moment.unix(m.unix() + m.utcOffset() * 60).utcOffset(0);
};