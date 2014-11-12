$.fn.tableScroll = function() {
    var scrollStartPos = 0,
            start = false;

    $(this).on("touchstart", function(event) {
        if (event.originalEvent && event.originalEvent.touches && event.originalEvent.touches.length === 1) {
            start = true;
            scrollStartPos = this.scrollLeft + event.originalEvent.touches[0].pageX;
        } else {
            start = false;
        }
    });

    $(this).on("touchmove", function(event) {
        if (start) {
            this.scrollLeft = scrollStartPos - event.originalEvent.touches[0].pageX;
            //event.preventDefault();
        }
    });
};

$(document).ready(function() {
    $('#toggle').on('click touchend', function(e) {
        e.preventDefault();
        $('body').toggleClass('menu-active');
    });
});