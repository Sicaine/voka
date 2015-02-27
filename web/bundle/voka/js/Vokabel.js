'use strict';

angular.module('Library', []).
    directive('vokabel', [
        '$document',
        '$resource',
        '$timeout',
        '$compile',
        function($document, $resource, $timeout, $compile) {

        console.log('vokabel global');
        return {

            restrict: 'A',
            scope: {},
            link: function(scope, element, $attrs) {

                var loadVokabel = function() {
                    var vokabel = $resource('/vokabel', {}, {});

                    scope.vokabel = vokabel.get({}, function (value) {
                            console.log(value);
                            console.log('success in vokabel');
                        },
                        function (error) {
                            console.log('error in vokabel');
                        });
                }

                // initial
                loadVokabel();

                scope.$on('voka.next', function(event) {
                    loadVokabel();
                })
            },

            templateUrl: '/bundle/voka/template/vokabel.html'
        }
    }]);