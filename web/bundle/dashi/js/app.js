angular.module('dashboardApp',
    [
        'Dashboard',
        'ngResource',
        'ngRoute',
        'Library'
    ]);

angular.module('Dashboard', [], function($locationProvider) {
    $locationProvider.html5Mode(true);
});