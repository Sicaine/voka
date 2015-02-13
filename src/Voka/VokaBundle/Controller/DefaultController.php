<?php

namespace Voka\VokaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller {

    public function indexAction() {
        return $this->render('VokaBundle:Default:index.html.twig');
    }

    public function getVokabelAction() {
        $vokabel = [
            "type" => "person",
            "forname" => "Martin",
            "surname" => "Fowler"
        ];
        return new JsonResponse($vokabel);
    }
}
