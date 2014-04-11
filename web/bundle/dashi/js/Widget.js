'use strict';

angular.module('Library', []).
    directive('widget', [
        '$document',
        '$resource',
        '$timeout',
        function($document, $resource, $timeout) {

        console.log('widget global');
        return {

            restrict: 'A',
            scope: {},
            link: function(scope, element, $attrs) {

                scope.dashboardId = parseInt($attrs.dashboardId);

                var addWidgetRes = $resource('/app_dev.php/widget/add/:id', {id:scope.dashboardId}, {
                    add: {method:'GET', params:{xCord: '', yCord: '', width: '', height: ''}}
                });

                var delWidgetRes = $resource('/app_dev.php/widget/remove/:id', {id:scope.dashboardId}, {
                    del: {method:'GET'}
                });

                var moveWidgetRes = $resource('/app_dev.php/widget/move/:id', {id:scope.dashboardId}, {
                    move: {method:'GET', params:{xCord: '', yCord: ''}}
                });

                var resizeWidgetRes = $resource('/app_dev.php/widget/resize/:id', {id:scope.dashboardId}, {
                    resize: {method:'GET', params:{width: '', height: ''}}
                });

            	scope.startX = scope.offsetX = parseInt($attrs.offsetX);
                scope.startY = scope.offsetY = parseInt($attrs.offsetY);
                scope.width = parseInt($attrs.width);
                scope.height = parseInt($attrs.height);

                if(angular.isString($attrs.widgetId)){
                    scope.widgetId = $attrs.widgetId;
                    element.addClass('saved');
                } else {
                    scope.width = 300;
                    scope.height = 100;
                    element.addClass('widget');
                   
                    scope.widgetId = 'rand' + Math.random();
                    element.css('top', scope.offsetY);
                    element.css('left', scope.offsetX);
                    element.css('width', scope.width);
                    element.css('height', scope.height);

                    addWidgetRes.add( { xCord: scope.offsetX, yCord: scope.offsetY, width: 300, height: 100} , function(data){
                        console.log('saved widget, new id: ' + data.id);
                        scope.widgetId = data.id;
                        element.addClass('saved');
                    } );
                }

                element.find('.name').bind('click', function(event) {
                    alert('clicked in widget');
                    event.stopPropagation();
                });

                element.find('.closeX').bind('click', function(event) {
                    scope.$destroy();
                    delWidgetRes.del( {id: scope.widgetId }, function() {
                            element.addClass('saved');
                            $timeout( function() {
                                element.remove();
                            } , 500);
                        },
                    function() {
                        alert('couldn\'t delete widget; error in backend');
                    })

                    event.stopPropagation();
                });

                // Resize Widget
                element.find('.resizeIcon').bind('mousedown', function(event) {

                    event.preventDefault();
                    scope.resizeX = event.pageX;
                    scope.resizeY = event.pageY;
                    $document.on('mousemove', mousemoveResize);
                    $document.on('mouseup', mouseupResize);
                    event.stopPropagation();
                });

                function mousemoveResize(event) {
                    element.removeClass('saved');
                    scope.diffY = event.pageY - scope.resizeY;
                    scope.diffX = event.pageX - scope.resizeX;
                    element.css('width', scope.width + scope.diffX);
                    element.css('height', scope.height + scope.diffY);

                    event.stopPropagation();
                }

                function mouseupResize(event) {
                    $document.unbind('mousemove', mousemoveResize);
                    $document.unbind('mouseup', mouseupResize);

                    scope.width += scope.diffX;
                    scope.height += scope.diffY;
                    event.stopPropagation();

                    resizeWidgetRes.resize({ id: scope.widgetId, width: scope.width, height: scope.height}, function() {
                        element.addClass('saved');
                    })
                }


                // Move Widget
                element.find('.dragbar').on('mousedown', function(event) {
                    var xElement = element.find('.closeX');
                    if(xElement[0] == event.target[0]){
                        return;
                    }

                    // Prevent default dragging of selected content
                    event.preventDefault();
                    scope.startX = event.pageX - scope.offsetX;
                    scope.startY = event.pageY - scope.offsetY;
                    $document.on('mousemove', mousemove);
                    $document.on('mouseup', mouseup);
                    event.stopPropagation();
                });

                function mousemove(event) {
                    element.removeClass('saved');
                    scope.offsetY = event.pageY - scope.startY;
                    scope.offsetX = event.pageX - scope.startX;
                    element.css({
                        top: scope.offsetY + 'px',
                        left:  scope.offsetX + 'px'
                    });

                    event.stopPropagation();
                }

                function mouseup(event) {
                    $document.unbind('mousemove', mousemove);
                    $document.unbind('mouseup', mouseup);

                    moveWidgetRes.move({ id: scope.widgetId, xCord: scope.offsetX, yCord: scope.offsetY}, function() {
                        element.addClass('saved');
                    })

                    event.stopPropagation();
                }
            },

            templateUrl: '/bundle/dashi/template/widget.html'
        }
    }]);