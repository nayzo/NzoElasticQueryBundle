<?php

/**
 * This file is part of the NzoElasticQueryBundle package.
 *
 * (c) Ala Eddine Khefifi <alakhefifi@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Nzo\ElasticQueryBundle\Service;

class IndexTools
{
    private $appElasticIndexConfigs;
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

    /**
     * @param string $entityNamespace
     * @return string
     */
    public function getElasticType($entityNamespace)
    {
        if (\strpos($entityNamespace, '\\') === false) {
            return \lcfirst($entityNamespace);
        }

        return \lcfirst(\substr($entityNamespace, \strrpos($entityNamespace, '\\') + 1));
    }

    /**
     * @param string $elasticType
     * @return string
     */
    public function getElasticIndex($elasticType)
    {
        $index = \strtolower(\preg_replace('/(?<!^)[A-Z]/', '_$0', $elasticType));

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
