<?php

namespace Dashi\DashboardBundle\Controller;


use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;

class PluginController  extends Controller {

    public function retrieveAllAction(){
        $bundles = $this->container->getParameter('kernel.bundles');

        $pluginIds = array();
        foreach($bundles as $name => $class){
            $plugin = new $class();

            // instanceof doesn't detect inheritance?!
            if($plugin instanceof DashiDashboardPlugin || method_exists($plugin, 'getPluginId')){
                $pluginIds[] = array('id' => $plugin->getPluginId());
            }
        }

        return new JsonResponse($pluginIds);
    }
}