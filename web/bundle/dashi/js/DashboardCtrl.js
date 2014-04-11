'use strict';

angular.module('Dashboard').controller('DashboardCtrl', [
    '$scope',
    '$document',
    '$compile',
    '$resource',
    '$location',
    function($scope, $document, $compile, $resource, $location){

        $scope.id = $location.url().substr($location.url().lastIndexOf('/') + 1);

        var allWidgets = $resource('/app_dev.php/widget/dashboard/:id', {id:1}, {get: { isArray: true }});
        allWidgets.get({id: $scope.id}, function(value) {
            $scope.widgets = value;

            angular.forEach($scope.widgets, function(row) {
                row.dashboardId = $scope.id;
            })

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


            var newWidgetElement = $compile('<div widget dashboard-id="'+$scope.id+'" offset-x="'+event.pageX+'" offset-y="'+event.pageY+'"></div>')($scope)
            canvas.append(newWidgetElement);

            $scope.$apply();
        });


}]);