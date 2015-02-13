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

            },

            templateUrl: '/bundle/voka/template/vokabel.html'
        }
    }]);