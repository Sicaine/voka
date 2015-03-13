<?php

namespace Voka\VokaBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="VokaCountryCard")
 */
final class VokaCountryCard
{
    /**
     * @ORM\Column(type="string")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    protected $id;

    /**
     * @ORM\Column(type="string", length=200, nullable=true)
     */
    protected $name;

    /**
     * @ORM\Column(type="string", length=200, nullable=true)
     */
    protected $capital;

    /**
     * @ORM\Column(type="string", length=200, nullable=true)
     */
    protected $continent;

    /**
     * @ORM\Column(type="string", length=200, nullable=true)
     */
    protected $population;

    /**
     * @return mixed
     */
    public function getId() {
        return $this->id;
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
    public function getName() {
        return $this->name;
    }

    /**
     * @param mixed $name
     */
    public function setName($name) {
        $this->name = $name;
    }

    /**
     * @return mixed
     */
    public function getCapital() {
        return $this->capital;
    }

    /**
     * @param mixed $capital
     */
    public function setCapital($capital) {
        $this->capital = $capital;
    }

    /**
     * @return mixed
     */
    public function getContinent() {
        return $this->continent;
    }

    /**
     * @param mixed $continent
     */
    public function setContinent($continent) {
        $this->continent = $continent;
    }

    /**
     * @return mixed
     */
    public function getPopulation() {
        return $this->population;
    }

    /**
     * @param mixed $population
     */
    public function setPopulation($population) {
        $this->population = $population;
    }


}