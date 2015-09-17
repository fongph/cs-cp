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

function getCookie(name) {
    if ($.cookie(name)) {
        return $.cookie(name);
    } else
        return false;
}

//var LC_API = LC_API || {};
//var livechat_chat_started = false;
//
//LC_API.on_before_load = function()
//{
//        // don't hide the chat window only if visitor
//        // is currently chatting with an agent
//        if (LC_API.visitor_engaged() === false && livechat_chat_started === false)
//        {
//                LC_API.hide_chat_window();
//        }
//};
//
//LC_API.on_chat_started = function()
//{
//        livechat_chat_started = true;
//};
/*
var LC_API = LC_API || {};
LC_API.on_before_load = function()
{
    var custom_variables = [
       { name: 'visit', value: '1' }
    ];
    LC_API.set_custom_variables(custom_variables);
};
LC_API.on_after_load = function()
{
	if(LC_API.chat_window_maximized()) {
            // LC_API.hide_chat_window();
            LC_API.minimize_chat_window();
        }
};
*/
$(document).ready(function () {
    $('.anchor').on("click", function (e) {

        var anchor = $(this).attr('data-href').split('#');
        if (anchor.length > 1) {
            $('html, body').stop().animate({
                scrollTop: $('#' + anchor[1]).position('body').top
            }, 1000);
        }

    });

    $('#toggle').on('click touchend', function (e) {
        e.preventDefault();
        $('body').toggleClass('menu-active');
    });
    
    $('.navbar-toggle').on('click', function() {
       $(this).toggleClass('collapsed'); 
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

String.prototype.stripHTML = function () {
    return this.replace(/(<([^>]+)>)/ig, "");
};