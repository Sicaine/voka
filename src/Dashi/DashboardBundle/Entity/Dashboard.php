<?php

namespace Dashi\DashboardBundle\Entity;

use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Column;

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
}