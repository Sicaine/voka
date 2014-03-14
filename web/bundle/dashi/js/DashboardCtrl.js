'use strict';

angular.module('Dashboard', []).controller('DashboardCtrl', [
    '$scope',
    '$document',
    '$compile',
    function($scope, $document, $compile){
        $scope.widgets = ['Widget 1', 'Widget 2', 'Widget 3', 'Widget 4'];
        $document.bind('click', function(event) {

           var clickedElement = angular.element(event.target);
            var canvas = angular.element('#canvas');
            var newWidgetElement = $compile('<div widget></div>')($scope)
            canvas.append(newWidgetElement);

            $scope.$apply();
        });


}]);