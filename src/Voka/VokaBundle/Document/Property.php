<?php

namespace Voka\VokaBundle\Document;

final class Property
{

    /**
     * @var $id
     */
    protected $id;

    /**
     * @var $data
     */
    protected $data;


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
     * Set data
     *
     * @param $data
     * @return self
     */
    public function setData($data)
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Get data
     *
     * @return raw_type $data
     */
    public function getData()
    {
        return $this->data;
    }
}
