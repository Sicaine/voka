<?php

namespace Dashi\DashboardBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction()
    {
        return $this->render('DashiDashboardBundle:Default:index.html.twig');
    }
}
