<?php

namespace Voka\VokaBundle\Document;

final class Vocabel
{

    /**
     * @var $id
     */
    protected $id;

    /**
     * @var string $officialName
     */
    protected $officialName;

    /**
     * @var string $officialLanguage
     */
    protected $officialLanguage;

    /**
     * @var string $currency
     */
    protected $currency;

    /**
     * @var collection $sharesBorderWith
     */
    protected $sharesBorderWith;

    /**
     * @var string $topLevelDomain
     */
    protected $topLevelDomain;

    /**
     * @var int $population
     */
    protected $population;

    /**
     * @var string $capital
     */
    protected $capital;

    /**
     * @var string $continent
     */
    protected $continent;

    /**
     * @var float $rand
     */
    protected $rand;


    /**
     * Set id
     *
     * @param custom_id $id
     * @return self
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Get id
     *
     * @return custom_id $id
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set officialName
     *
     * @param string $officialName
     * @return self
     */
    public function setOfficialName($officialName)
    {
        $this->officialName = $officialName;
        return $this;
    }

    /**
     * Get officialName
     *
     * @return string $officialName
     */
    public function getOfficialName()
    {
        return $this->officialName;
    }

    /**
     * Set officialLanguage
     *
     * @param string $officialLanguage
     * @return self
     */
    public function setOfficialLanguage($officialLanguage)
    {
        $this->officialLanguage = $officialLanguage;
        return $this;
    }

    /**
     * Get officialLanguage
     *
     * @return string $officialLanguage
     */
    public function getOfficialLanguage()
    {
        return $this->officialLanguage;
    }

    /**
     * Set currency
     *
     * @param string $currency
     * @return self
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
        return $this;
    }

    /**
     * Get currency
     *
     * @return string $currency
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * Set sharesBorderWith
     *
     * @param collection $sharesBorderWith
     * @return self
     */
    public function setSharesBorderWith($sharesBorderWith)
    {
        $this->sharesBorderWith = $sharesBorderWith;
        return $this;
    }

    /**
     * Get sharesBorderWith
     *
     * @return collection $sharesBorderWith
     */
    public function getSharesBorderWith()
    {
        return $this->sharesBorderWith;
    }

    /**
     * Set topLevelDomain
     *
     * @param string $topLevelDomain
     * @return self
     */
    public function setTopLevelDomain($topLevelDomain)
    {
        $this->topLevelDomain = $topLevelDomain;
        return $this;
    }

    /**
     * Get topLevelDomain
     *
     * @return string $topLevelDomain
     */
    public function getTopLevelDomain()
    {
        return $this->topLevelDomain;
    }

    /**
     * Set population
     *
     * @param int $population
     * @return self
     */
    public function setPopulation($population)
    {
        $this->population = $population;
        return $this;
    }

    /**
     * Get population
     *
     * @return int $population
     */
    public function getPopulation()
    {
        return $this->population;
    }

    /**
     * Set capital
     *
     * @param string $capital
     * @return self
     */
    public function setCapital($capital)
    {
        $this->capital = $capital;
        return $this;
    }

    /**
     * Get capital
     *
     * @return string $capital
     */
    public function getCapital()
    {
        return $this->capital;
    }

    /**
     * Set continent
     *
     * @param string $continent
     * @return self
     */
    public function setContinent($continent)
    {
        $this->continent = $continent;
        return $this;
    }

    /**
     * Get continent
     *
     * @return string $continent
     */
    public function getContinent()
    {
        return $this->continent;
    }

    /**
     * Set rand
     *
     * @param float $rand
     * @return self
     */
    public function setRand($rand)
    {
        $this->rand = $rand;
        return $this;
    }

    /**
     * Get rand
     *
     * @return float $rand
     */
    public function getRand()
    {
        return $this->rand;
    }
}
