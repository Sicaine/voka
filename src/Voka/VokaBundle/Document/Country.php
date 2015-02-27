<?php

namespace Voka\VokaBundle\Document;

use Doctrine\MongoDB\Collection;

final class Country
{

    /**
     * @var $id
     */
    protected $id;

    /**
     * @var string $title
     */
    protected $title;

    /**
     * @var string $type
     */
    protected $type;

    /**
     * @var string $modified
     */
    protected $modified;

    /**
     * @var collection $aliases
     */
    protected $aliases;

    /**
     * @var collection $labels
     */
    protected $labels;

    /**
     * @var collection $descriptions
     */
    protected $descriptions;

    /**
     * @var collection $claims
     */
    protected $claims;

    /**
     * @var collection $sitelinks
     */
    protected $sitelinks;


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
     * Set title
     *
     * @param string $title
     * @return self
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Get title
     *
     * @return string $title
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set type
     *
     * @param string $type
     * @return self
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Get type
     *
     * @return string $type
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set modified
     *
     * @param string $modified
     * @return self
     */
    public function setModified($modified)
    {
        $this->modified = $modified;
        return $this;
    }

    /**
     * Get modified
     *
     * @return string $modified
     */
    public function getModified()
    {
        return $this->modified;
    }

    /**
     * Set aliases
     *
     * @param collection $aliases
     * @return self
     */
    public function setAliases($aliases)
    {
        $this->aliases = $aliases;
        return $this;
    }

    /**
     * Get aliases
     *
     * @return collection $aliases
     */
    public function getAliases()
    {
        return $this->aliases;
    }

    /**
     * Set labels
     *
     * @param collection $labels
     * @return self
     */
    public function setLabels($labels)
    {
        $this->labels = $labels;
        return $this;
    }

    /**
     * Get labels
     *
     * @return collection $labels
     */
    public function getLabels()
    {
        return $this->labels;
    }

    /**
     * Set descriptions
     *
     * @param collection $descriptions
     * @return self
     */
    public function setDescriptions($descriptions)
    {
        $this->descriptions = $descriptions;
        return $this;
    }

    /**
     * Get descriptions
     *
     * @return collection $descriptions
     */
    public function getDescriptions()
    {
        return $this->descriptions;
    }

    /**
     * Set claims
     *
     * @param collection $claims
     * @return self
     */
    public function setClaims($claims)
    {
        $this->claims = $claims;
        return $this;
    }

    /**
     * Get claims
     *
     * @return collection $claims
     */
    public function getClaims()
    {
        return $this->claims;
    }

    /**
     * Set sitelinks
     *
     * @param collection $sitelinks
     * @return self
     */
    public function setSitelinks($sitelinks)
    {
        $this->sitelinks = $sitelinks;
        return $this;
    }

    /**
     * Get sitelinks
     *
     * @return collection $sitelinks
     */
    public function getSitelinks()
    {
        return $this->sitelinks;
    }
}
