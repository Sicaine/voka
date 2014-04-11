<?php

namespace Dashi\DashboardBundle\Entity;

use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\ManyToOne;

/**
 * @Entity
 * @Table(name="widget")
 */
class Widget {

    /**
     * @Column(type="integer")
     * @Id
     * @GeneratedValue(strategy="AUTO")
     */
	protected $id;

    /**
     * @Column(type="integer")
     * @var int
     */
    protected $xCord;

    /**
     * @Column(type="integer")
     * @var int
     */
    protected $yCord;

    /**
     * @Column(type="integer")
     * @var int
     */
    protected $width;

    /**
     * @Column(type="integer")
     * @var int
     */
    protected $height;

    /**
     * @Column
     * @var string
     */
    protected $pluginTypeId;

    /**
     * @ManyToOne(targetEntity="Dashboard", inversedBy="widgets")
     */
    protected $dashboard;

    /**
     * @param int $height
     */
    public function setHeight($height) {
        $this->height = $height;
    }

    /**
     * @return int
     */
    public function getHeight() {
        return $this->height;
    }

    /**
     * @param mixed $id
     */
    public function setId($id) {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @param string $pluginTypeId
     */
    public function setPluginTypeId($pluginTypeId) {
        $this->pluginTypeId = $pluginTypeId;
    }

    /**
     * @return string
     */
    public function getPluginTypeId() {
        return $this->pluginTypeId;
    }

    /**
     * @param int $width
     */
    public function setWidth($width) {
        $this->width = $width;
    }

    /**
     * @return int
     */
    public function getWidth() {
        return $this->width;
    }

    /**
     * @param mixed $xCord
     */
    public function setXCord($xCord) {
        $this->xCord = $xCord;
    }

    /**
     * @return mixed
     */
    public function getXCord() {
        return $this->xCord;
    }

    /**
     * @param int $yCord
     */
    public function setYCord($yCord) {
        $this->yCord = $yCord;
    }

    /**
     * @return int
     */
    public function getYCord() {
        return $this->yCord;
    }

    /**
     * @param mixed $dashboard
     */
    public function setDashboard($dashboard) {
        $this->dashboard = $dashboard;
    }

    /**
     * @return mixed
     */
    public function getDashboard() {
        return $this->dashboard;
    }

}