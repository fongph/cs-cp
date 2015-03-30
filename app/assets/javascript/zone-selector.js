zoneSelector = function (id, options) {
    var options = $.extend({
        circleCallback: null,
        minRadius: 150,
        maxRadius: 10000,
        zoom: 14,
        circleOptions: {
            fillColor: '#0000ff',
            fillOpacity: 0.1,
            strokeWeight: 3,
            clickable: true,
            editable: true,
            draggable: true,
            zIndex: 1
        },
        defaultMapCenter: new google.maps.LatLng(0, 0),
        defaultMapZoom: 1,
        useGeolocation: true
    }, options);

    var element = document.getElementById(id);

    var map = new google.maps.Map(element, {
        mapTypeId: google.maps.MapTypeId.ROADMAP
    });

    var circle;

    var self = this;

    this.getCircleOptions = function () {
        return circleOptions;
    };

    this.setZone = function (circle) {
        if (circle instanceof google.maps.Circle) {
            circle.setOptions(options.circleOptions)
            circle.setMap(map);
            drawingManager.setDrawingMode(null);
            addCircleListeners(circle);
            callback(circle);
        }
    };

    this.setMapLocation = function (circle) {
        if (circle instanceof google.maps.Circle) {
            map.fitBounds(circle.getBounds());
        } else if (options.useGeolocation && navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function (position) {
                map.setCenter(new google.maps.LatLng(position.coords.latitude, position.coords.longitude));
                map.setZoom(options.zoom);
            });
        } else {
            map.setZoom(options.defaultMapZoom);
            map.setCenter(options.defaultMapCenter);
        }
    };

    var callback = function (circle) {
        if (typeof options.circleCallback === 'function') {
            return options.circleCallback(self.serializeCircle(circle));
        }
    };

    var checkRadius = function (circle) {
        if (circle.getRadius() < options.minRadius) {
            circle.setRadius(options.minRadius);
        } else if (circle.getRadius() > options.maxRadius) {
            circle.setRadius(options.maxRadius);
        }
    };

    var addCircleListeners = function(circle) {
        google.maps.event.addListener(circle, 'radius_changed', function () {
            checkRadius(circle);
            callback(circle);
        });

        google.maps.event.addListener(circle, 'center_changed', function () {
            callback(circle);
        });
    };

    var getDrawingManager = function () {
        return new google.maps.drawing.DrawingManager({
            drawingMode: google.maps.drawing.OverlayType.CIRCLE,
            drawingControl: true,
            drawingControlOptions: {
                position: google.maps.ControlPosition.TOP_LEFT,
                drawingModes: [
                    google.maps.drawing.OverlayType.CIRCLE
                ]
            },
            circleOptions: options.circleOptions
        });
    };

    var drawingManager = getDrawingManager();

    drawingManager.setMap(map);

    google.maps.event.addListener(drawingManager, 'overlaycomplete', function (event) {
        if (event.type !== google.maps.drawing.OverlayType.CIRCLE) {
            event.overlay.setMap(null);
        } else {
            if (circle && $.isFunction(circle.getMap) && circle.getMap() !== null) {
                circle.setMap(null);
            }

            circle = event.overlay;
            drawingManager.setDrawingMode(null);
            checkRadius(circle);

            addCircleListeners(circle);

            callback(circle);
        }
    });

    map.setZoom(options.defaultMapZoom);
    map.setCenter(options.defaultMapCenter);
};

zoneSelector.prototype.serializeCircle = function (circle) {
    return circle.getCenter().lat() + '|' + circle.getCenter().lng() + '|' + parseInt(circle.getRadius());
};

zoneSelector.prototype.deserializeCircle = function (string) {
    var params = string.split('|');
    if (params.length !== 3) {
        return null;
    }

    return new google.maps.Circle({
        center: new google.maps.LatLng(parseFloat(params[0]), parseFloat(params[1])),
        radius: parseInt(params[2], 10),
    });
};