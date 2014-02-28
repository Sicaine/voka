<?php

namespace Dashi\DashboardBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Dashi\DashboardBundle\Entity\Dashboard;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller {

    public function indexAction() {
        return $this->render('DashiDashboardBundle:Default:index.html.twig');
    }

    public function manageAction() {
        $em = $this->getDoctrine()->getManager();
        $dashboards = $em->getRepository('Dashi\DashboardBundle\Entity\Dashboard')->findAll();

        return $this->render('DashiDashboardBundle:Default:manage.html.twig', array('dashboards' => $dashboards));
    }

    public function newAction(Request $request) {
        $dashboard = new Dashboard();
        $form = $this->createFormBuilder($dashboard)
            ->add('name', 'text')
            ->add('submit', 'submit')
            ->getForm();

        $form->handleRequest($request);

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($form->getData());
            $em->flush();

            return $this->redirect($this->generateUrl('dashi_dashboard_overview'));
        }

        return $this->render(
            'DashiDashboardBundle:Default:new_dashboard.html.twig',
            array(
                'form' => $form->createView(),
            )
        );
    }
}
