'use strict';

angular.module('Dashboard', []).controller('DashboardCtrl', [
    '$scope',
    '$document',
    '$compile',
    '$routeParams',
    '$resource',
    function($scope, $document, $compile, $routeParams, $resource){

        $routeParams.id;
        var allWidgets = $resource('/app_dev.php/widget/dashboard/:id', {id:1}, {get: { isArray: true }});
        allWidgets.get({id:1}, function(value) {
            $scope.widgets = value;
            console.log('loaded allWidgets');
        },
        function(error) {
            console.log('error in allWidgets');
        })

        $document.bind('click', function(event) {

           var clickedElement = angular.element(event.target);
           var canvas = angular.element('#canvas');
            if(canvas[0] != clickedElement[0]) {
                return;
            }


            var newWidgetElement = $compile('<div widget offset-x="'+event.pageX+'" offset-y="'+event.pageY+'"></div>')($scope)
            canvas.append(newWidgetElement);

            $scope.$apply();
        });


}]);