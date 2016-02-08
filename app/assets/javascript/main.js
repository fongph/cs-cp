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

function isset(element) {
    if (typeof element != 'undefined') {
        return element;
    } else {
        return false;
    }
}

function htmlEscape(text) {
    if (typeof text == 'object') {
        for (var i in text) if (text.hasOwnProperty(i)) {
            text[i] = htmlEscape(text[i]);
        }
    } else if (typeof text == 'string') {
        text = _.escape(text);
    }
    return text;
}

function htmlStrip(text) {

    if (typeof text == 'object') {
        for (var i in text) if (text.hasOwnProperty(i)) {
            text[i] = htmlStrip(text[i]);
        }
    } else if (typeof text == 'string') {
        text = String(text).stripHTML();
    }
    return text;
}

function getDateAJAX(perPage, page, url, search, doStrip) {
    if(!url)  {
        console.log('Error: set variable "url"'); return false;
    }
    var $_result = {};
    $.ajax({
        type: 'POST',
        url: url,
        async: false,
        data: {'perPage': perPage, 'currPage': page, 'search': search},
        dataType: 'json',
        beforeSend: function () {
            $('.panel-body').html('loading..');
        },
        success: function (result) {
            $_result = (result) ? result : false;
        }
    });

    if (doStrip) {
        return htmlStrip($_result);
    } else {
        return htmlEscape($_result);
    }
}

function getDateAJAXWithoutStrip(perPage, page, url, search) {
    if(!url)  {
        console.log('Error: set variable "url"'); return false;
    }
    var $_result = {};
    $.ajax({
        type: 'POST',
        url: url,
        async: false,
        data: {'perPage': perPage, 'currPage': page, 'search': search},
        dataType: 'json',
        beforeSend: function () {
            $('.panel-body').html('loading..');
        },
        success: function (result) {
            $_result = (result) ? result : false;
        }
    });

    return $_result;
}

function search() {
    var _objSearch = $('.panel-heading .blockSearch');

    _objSearch.on("keydown", function (event) {
        if (event.which == 13) {
            _objSearch.find('.input-group-btn button').trigger('click');
        }
    });

    _objSearch.find('.input-group-btn button').on('click', function () {
        var _input = _objSearch.find('input');
        if (!_input.val())
            return false;

        if (_input.attr('disabled')) {
            _input.removeAttr('disabled');
            if (_objSearch.find('.fa').hasClass('fa-times'))
                _objSearch.find('.fa').removeClass('fa-times').addClass('fa-search');
            if (_input.val())
                _input.val('');
        } else {
            _input.attr('disabled', 'disabled');
            if (_objSearch.find('.fa').hasClass('fa-search'))
                _objSearch.find('.fa').removeClass('fa-search').addClass('fa-times');
        }
        $("select#panel_paginate_per_pages").trigger('change');
    });
}

function clearCookie(name) {
    if ($.cookie(name)) {
        $.removeCookie(name, {path: '/'});
    }
}

function setCookie(name, value) {
    $.cookie(name, value, {expires: 7, path: '/'});
}

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

function TrackEventGA(Category, Action, Label, Value) {
    "use strict";
    if (typeof (_gaq) !== "undefined") {
        _gaq.push(['_trackEvent', Category, Action, Label, Value]);
    } else if (typeof (ga) !== "undefined") {
        ga('send', 'event', Category, Action, Label, Value);
    }
}

$(document).ready(function () {
    $('.ga-action-click').on('click', function () {
        var _ga_action = ($(this).attr('ga-action')) ? $(this).attr('ga-action') : false,
            _ga_category = ($(this).attr('ga-category')) ? $(this).attr('ga-category') : false,
            _ga_label = ($(this).attr('ga-label')) ? $.trim($(this).attr('ga-label').toLowerCase()).replace(/\s/g, '-') : false;

        if (_ga_action && _ga_category && _ga_label) {
            TrackEventGA(_ga_category, _ga_action, _ga_label);
        }
    });

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

    $('.navbar-toggle').on('click', function () {
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

// two parameters: string, max length
escapeAndTruncate = function () {
    var maxLength = 200;
    
    if (!arguments.length || !_.isString(arguments[0])) {
        return '';
    }
    
    var value = arguments[0];
    
    if (arguments.length > 1) {
        maxLength = arguments.length;
    }
    
    if (value.length > maxLength) {
        value.substr(0, maxLength) + '...';
    }
    
    return _.escape(value);
};