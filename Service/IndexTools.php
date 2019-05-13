<?php

namespace Nzo\ElasticQueryBundle\Service;

class IndexTools
{
    /**
     * @var string
     */
    private $appElasticIndexConfigs;
    /**
     * @var string
     */
    private $elasticIndexPrefix;

    /**
     * SearchManager constructor.
     *
     * @param string $appElasticIndexConfigs
     * @param string $elasticIndexPrefix
     */
    public function __construct($appElasticIndexConfigs, $elasticIndexPrefix)
    {
        $this->appElasticIndexConfigs = $appElasticIndexConfigs;
        $this->elasticIndexPrefix = $elasticIndexPrefix;
    }

    public function getElasticType(string $entityNamespace)
    {
        return lcfirst(substr($entityNamespace, strrpos($entityNamespace, '\\') + 1));
    }

    /**
     * @param string $elasticType
     * @return string
     */
    public function getElasticIndex(string $elasticType)
    {
        $index = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $elasticType));

        return empty($this->elasticIndexPrefix) ? $index : $this->elasticIndexPrefix.'.'.$index;
    }

    /**
     * @param string $entityNamespace
     * @return array
     */
    public function getIndexMappingProperties($entityNamespace)
    {
        $type = $this->getElasticType($entityNamespace);

        return $this->appElasticIndexConfigs[$this->getElasticIndex($type)]['types'][$type]['mapping']['properties'];
    }
}
