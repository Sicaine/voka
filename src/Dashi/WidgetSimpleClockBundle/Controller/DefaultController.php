<?php

namespace Dashi\WidgetSimpleClockBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction($name)
    {
        return $this->render('DashiWidgetSimpleClockBundle:Default:index.html.twig', array('name' => $name));
    }
}
