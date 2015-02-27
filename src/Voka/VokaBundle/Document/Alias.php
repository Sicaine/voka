<?php

namespace Voka\VokaBundle\Document;

final class Alias
{
    /**
     * @var string $language
     */
    protected $language;

    /**
     * @var collection $keyvalue
     */
    protected $keyvalue;


    /**
     * Set language
     *
     * @param string $language
     * @return self
     */
    public function setLanguage($language)
    {
        $this->language = $language;
        return $this;
    }

    /**
     * Get language
     *
     * @return string $language
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * Set keyvalue
     *
     * @param collection $keyvalue
     * @return self
     */
    public function setKeyvalue($keyvalue)
    {
        $this->keyvalue = $keyvalue;
        return $this;
    }

    /**
     * Get keyvalue
     *
     * @return collection $keyvalue
     */
    public function getKeyvalue()
    {
        return $this->keyvalue;
    }
    /**
     * @var $id
     */
    protected $id;


    /**
     * Get id
     *
     * @return id $id
     */
    public function getId()
    {
        return $this->id;
    }
}
