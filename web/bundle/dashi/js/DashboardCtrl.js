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
        },
        function(error) {
            console.log('error in allWidgets');
        })
        console.log(allWidgets);


        $document.bind('click', function(event) {

           var clickedElement = angular.element(event.target);
            var canvas = angular.element('#canvas');
            var newWidgetElement = $compile('<div widget></div>')($scope)
            canvas.append(newWidgetElement);

            $scope.$apply();
        });


}]);