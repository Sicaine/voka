<?php

namespace Dashi\DashboardBundle\Controller;


use Dashi\DashboardBundle\Entity\Dashboard;
use Dashi\DashboardBundle\Entity\Widget;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class WidgetController  extends Controller {

    public function retrieveAllAction($dashboardId){
        $em = $this->getDoctrine()->getManager();

        /**
         * var Dashboard $dashboard
         */
        $dashboard = $em->getRepository('Dashi\DashboardBundle\Entity\Dashboard')->find($dashboardId);

        $resultArray = array();
        foreach($dashboard->getWidgets() as $widget){
            $element = array();
            $element['id'] = $widget->getId();
            $element['xCord'] = $widget->getXCord();
            $element['yCord'] = $widget->getYCord();
            $element['width'] = $widget->getWidth();
            $element['height'] = $widget->getHeight();

            $resultArray[] = $element;
        }
        return new JsonResponse($resultArray);
    }

    public function addAction(Request $request, $dashboardId){
        $em = $this->getDoctrine()->getManager();

        /**
         * var Dashboard $dashboard
         */
        $dashboard = $em->getRepository('Dashi\DashboardBundle\Entity\Dashboard')->find($dashboardId);

        $widget = new Widget();
        $widget->setXCord($request->get('xCord'));
        $widget->setYCord($request->get('yCord'));
        $widget->setWidth($request->get('width'));
        $widget->setHeight($request->get('height'));

        $dashboard->addWidget($widget);
        $widget->setDashboard($dashboard);
        $em->flush();

        return new JsonResponse(array( 'id' => $widget->getId()));
    }

    public function removeAction($widgetId){
        $em = $this->getDoctrine()->getManager();

        /**
         * var Widget $widget
         */
        $widget = $em->getRepository('Dashi\DashboardBundle\Entity\Widget')->find($widgetId);
        $em->remove($widget);
        $em->flush();

        return new JsonResponse();
    }

    public function moveAction(Request $request, $widgetId){
        $em = $this->getDoctrine()->getManager();

        /**
         * var Widget $widget
         */
        $widget = $em->getRepository('Dashi\DashboardBundle\Entity\Widget')->find($widgetId);
        $widget->setXCord($request->get('xCord'));
        $widget->setYCord($request->get('yCord'));
        $em->flush();
        return new JsonResponse();
    }

    public function resizeAction(Request $request, $widgetId) {
        $em = $this->getDoctrine()->getManager();

        /**
         * var Widget $widget
         */
        $widget = $em->getRepository('Dashi\DashboardBundle\Entity\Widget')->find($widgetId);
        $widget->setWidth($request->get('width'));
        $widget->setHeight($request->get('height'));
        $em->flush();

        return new JsonResponse();
    }

    public function pluginTypeIdAction($widgetId) {
        $em = $this->getDoctrine()->getManager();

        /**
         * var Widget $widget
         */
        $widget = $em->getRepository('Dashi\DashboardBundle\Entity\Widget')->find($widgetId);

        return new JsonResponse(array( 'pluginTypeId' => $widget->getPluginTypeId()));
    }

    public function setPluginTypeIdAction(Request $request, $widgetId) {
        $pluginTypeId = $request->get('pluginTypeId');
        $em = $this->getDoctrine()->getManager();

        /**
         * var Widget $widget
         */
        $widget = $em->getRepository('Dashi\DashboardBundle\Entity\Widget')->find($widgetId);
        if($widget->getPluginTypeId() == null){
            $widget->setPluginTypeId($pluginTypeId);
            $em->flush();
        } else {
            return new JsonResponse('ERROR');
        }

        return new JsonResponse();
    }
}