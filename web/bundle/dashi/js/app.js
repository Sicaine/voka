angular.module('dashboardApp',
    [
        'Dashboard',
        'ngResource',
        'ngRoute',
        'Library',
        'Plugin'
    ]);

angular.module('Dashboard', [], function($locationProvider) {
    $locationProvider.html5Mode(true);
});