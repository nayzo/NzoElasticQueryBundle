<?php

namespace Nzo\ElasticQueryBundle;

use Nzo\ElasticQueryBundle\DependencyInjection\Compiler\NzoElasticQueryPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class NzoElasticQueryBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new NzoElasticQueryPass());
    }
}
