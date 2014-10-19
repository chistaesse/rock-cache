<?php

namespace rock\cache;


trait ObjectTrait 
{
    public function __construct(array $config = [])
    {
        if (!empty($config)) {
            $this->setProperties($config);
        }
    }

    /**
     * Configures an object with the initial property values.
     *
     * @param array  $properties the property initial values given in terms of name-value pairs.
     */
    protected function setProperties(array $properties)
    {
        foreach ($properties as $name => $value) {
            $this->$name = $value;
        }
    }
} 