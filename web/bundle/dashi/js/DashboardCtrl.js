'use strict';

angular.module('dashboardApp', []).controller('DashboardCtrl', [
    '$scope',
    function($scope){
    $scope.widgets = ['Widget 1', 'Widget 2', 'Widget 3', 'Widget 4'];
}]);