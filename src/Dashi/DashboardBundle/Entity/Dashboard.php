<?php

namespace Dashi\DashboardBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\OneToMany;

/**
 * @Entity
 * @Table(name="dashboard")
 */
class Dashboard {

    /**
     * @Column(type="integer")
     * @Id
     * @GeneratedValue(strategy="AUTO")
     */
	protected $id;
	
	/**
     * @Column(length=100)
     */
	protected $name;

    /**
     * @OneToMany(targetEntity="Widget", mappedBy="dashboard",cascade={"persist"})
     * @var ArrayCollection
     */
    private $widgets;
	
	public function getId(){
		return $this->id;
	}
	
	public function setId($id){
		$this->id = $id;
	}
	
	public function getName(){
		return $this->name;
	}
	
	public function setName($name){
		return $this->name = $name;
	}

    public function addWidget($widget){
        $this->widgets[] = $widget;
    }
    /**
     * @param \Doctrine\Common\Collections\ArrayCollection $widgets
     */
    public function setWidgets($widgets) {
        $this->widgets = $widgets;
    }

    /**
     * @return \Doctrine\Common\Collections\ArrayCollection
     */
    public function getWidgets() {
        return $this->widgets;
    }


}