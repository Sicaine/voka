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

                var vokabel = $resource('/vokabel', {}, {get: {}});

                scope.vokabel = vokabel.get({}, function(value) {
                        console.log(value);
                        console.log('success in vokabel');
                    },
                    function(error) {
                        console.log('error in vokabel');
                    });

                scope.$on('voka.next', function(event) { console.log('blub n pressed?!'); })
            },

            templateUrl: '/bundle/voka/template/vokabel.html'
        }
    }]);