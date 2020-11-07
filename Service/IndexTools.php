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

    public function __construct(array $appElasticIndexConfigs, string $elasticIndexPrefix)
    {
        $this->appElasticIndexConfigs = $appElasticIndexConfigs;
        $this->elasticIndexPrefix = $elasticIndexPrefix;
    }

    public function getElasticType(string $entityNamespace): string
    {
        if (\strpos($entityNamespace, '\\') === false) {
            return \lcfirst($entityNamespace);
        }

        return \lcfirst(\substr($entityNamespace, \strrpos($entityNamespace, '\\') + 1));
    }

    public function getElasticIndex(string $elasticType): string
    {
        $index = \strtolower(\preg_replace('/(?<!^)[A-Z]/', '_$0', $elasticType));

        return empty($this->elasticIndexPrefix) ? $index : $this->elasticIndexPrefix.'.'.$index;
    }

    public function getIndexMappingProperties(string $entityNamespace): array
    {
        $type = $this->getElasticType($entityNamespace);

        return $this->appElasticIndexConfigs[$this->getElasticIndex($type)]['types'][$type]['mapping']['properties'];
    }
}
