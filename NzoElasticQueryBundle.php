<?php

namespace Nzo\ElasticQueryBundle;

use Nzo\ElasticQueryBundle\DependencyInjection\Compiler\NzoElasticQueryPass;
use Nzo\ElasticQueryBundle\DependencyInjection\Compiler\NzoElasticUpdateNestedQueryPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class NzoElasticQueryBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new NzoElasticQueryPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 2);
        $container->addCompilerPass(new NzoElasticUpdateNestedQueryPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 1);
    }
}
