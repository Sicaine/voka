<?php

namespace Dashi\DashboardBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction()
    {
        return $this->render('DashiDashboardBundle:Default:index.html.twig');
    }
    
    public function manageAction()
    {
    	$em = $this->getDoctrine()->getManager();
    	$dashboards = $em->getRepository('Dashi\DashboardBundle\Entity\Dashboard')->findAll();
    	return $this->render('DashiDashboardBundle:Default:manage.html.twig', array('dashboards' => $dashboards));
    }
}
