'use strict';

angular.module('Plugin', []).
    directive('simpleClock', [
        '$document',
        '$resource',
        '$timeout',
        function($document, $resource, $timeout) {

        console.log('simpleClock');
        return {

            restrict: 'A',
            scope: {},
            link: function(scope, element, $attrs) {

                scope.dashboardId = parseInt($attrs.dashboardId);
                scope.clock;

                var clock = function(){
                    var today=new Date();
                    var h=today.getHours();
                    var m=today.getMinutes();
                    var s=today.getSeconds();
                    m=checkTime(m);
                    s=checkTime(s);

                    scope.clock = h+":"+m+":"+s;


                    function checkTime(i)
                    {
                        if (i<10)
                        {
                           i="0" + i;
                        }

                        return i;
                    }

                    $timeout(clock, 500);
                }

                clock();
            },

            templateUrl: '/bundle/simpleClock/template/simpleClock.html'
        }
    }]);