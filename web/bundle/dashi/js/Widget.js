'use strict';

angular.module('Library', []).
    directive('widget', function() {

        console.log('widget global');
        return {

            restrict: 'A',

            link: function(scope, element, $linkAttributes) {

                element.bind('click', function(event) {
                    alert('clicked in widget');
                    event.stopPropagation();
                });

                var innerObj = angular.element('<span>i\'m an widget</span>');
                element.append(innerObj);
                scope.$apply();

                console.log('widget link');

            }
        }
    });