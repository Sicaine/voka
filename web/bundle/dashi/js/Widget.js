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


//                xCord'));
//                $widget->setYCord($request->get('yCord'));
//                $widget->setWidth($request->get('width'));
//                $widget->setHeight($request->get('height'));
//
//
                var addWidgetRes = $resource('/app_dev.php/widget/add/:id', {id:1}, {
                    add: {method:'GET', params:{xCord: '', yCord: '', width: '', height: ''}}
                });

                var delWidgetRes = $resource('/app_dev.php/widget/remove/:id', {id:1}, {
                    del: {method:'GET'}
                });

                var moveWidgetRes = $resource('/app_dev.php/widget/move/:id', {id:1}, {
                    move: {method:'GET', params:{xCord: '', yCord: '', width: '', height: ''}}
                });

            	scope.startX = scope.offsetX = parseInt($attrs.offsetX);
                scope.startY = scope.offsetY = parseInt($attrs.offsetY);

                if(angular.isString($attrs.widgetId)){
                    scope.widgetId = $attrs.widgetId;
                    element.addClass('saved');
                } else {
                    element.addClass('widget');
                   
                    scope.widgetId = 'rand' + Math.random();
                    element.css('top', scope.offsetY);
                    element.css('left', scope.offsetX);
                    element.css('width', 300);
                    element.css('height', 100);

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