<?php

namespace Dashi\WidgetSimpleClockBundle;

use Dashi\DashboardBundle\DashiDashboardPlugin;

class DashiWidgetSimpleClockBundle extends DashiDashboardPlugin
{
    public function getPluginId(){
        return 'SIMPLE_CLOCK';
    }
}
