'use strict';

angular.module('Voka').controller('VokaCtrl', [
    '$scope',
    '$document',
    '$compile',
    '$resource',
    function($scope, $document, $compile, $resource){

        console.log("working");

        $document.bind('keypress', function(event) {

            console.log(event.keyCode);

        });

}]);