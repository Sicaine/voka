'use strict';

angular.module('Library', []).
    directive('widget', function($document) {

        console.log('widget global');
        return {

            restrict: 'A',

            link: function(scope, element, $attrs) {
                if(angular.isString($attrs.widgetId)){
                    scope.startX = scope.offsetX = $attrs.offsetX;
                    scope.startY = scope.offsetY = $attrs.offsetY;

                    scope.widgetId = $attrs.widgetId;
                    element.addClass('saved');
                } else {
                    scope.startX = scope.offsetX = event.offsetX;
                    scope.startY = scope.offsetY = event.offsetY;

                    // if user doesn't click on canvas, recalculate offset
                    var canvasElement = angular.element('#canvas');

                    if(canvasElement[0] != element[0] && false){
                        scope.offsetX = scope.offsetX - (element[0].getBoundingClientRect().top - canvasElement[0].getBoundingClientRect().top);
                        scope.offsetY = scope.offsetY - (element[0].getBoundingClientRect().left - canvasElement[0].getBoundingClientRect().left);
                    }

                    element.addClass('widget');
                    element.css('top', scope.offsetY);
                    element.css('left', scope.offsetX);
                    element.css('width', 300);
                    element.css('height', 100);
                }



                element.find('.name').bind('click', function(event) {
                    alert('clicked in widget');
                    event.stopPropagation();
                });

                element.find('.closeX').bind('click', function(event) {
                    scope.$destroy();
                    element.remove();
                    event.stopPropagation();
                });




                console.log('widget link');

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

                    event.stopPropagation();
                }

            },

            templateUrl: '/bundle/dashi/template/widget.html'
        }
    });