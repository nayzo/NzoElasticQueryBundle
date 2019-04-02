<?php

namespace Nzo\ElasticQueryBundle;

use Nzo\ElasticQueryBundle\Compiler\ElasticQueryPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class ElasticQueryBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new ElasticQueryPass());
    }
}
