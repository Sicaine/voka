<?php

namespace Dashi\DashboardBundle;


use Symfony\Component\HttpKernel\Bundle\Bundle;

abstract class DashiDashboardPlugin extends Bundle{
    abstract public function getPluginId();
} 