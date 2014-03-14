angular.module('dashboardApp',
    [
        'Dashboard',
        'ngResource',
        'ngRoute',
        'Library'
    ])
    .config(['$routeProvider', function($routeProvider) {
    $routeProvider.when('/dashboard/show/:id', {templateUrl: 'blub.html', controller: 'DashboardCtrl'});
}]);
