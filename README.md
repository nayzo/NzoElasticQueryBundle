ElasticQueryBundle
=====================

Installation
------------

### Through Composer:

Install the bundle:

```
$ composer require nzo/elastic-query-bundle
```

### Register the bundle in app/AppKernel.php (Symfony V2 or V3):

``` php
// app/AppKernel.php

public function registerBundles()
{
    return array(
        // ...
        new Nzo\ElasticQueryBundle\NzoElasticQueryBundle(),
    );
}
```

### Configure your application's config.yml:

Configure your secret encryption key:

``` yml
# app/config/config.yml (Symfony V2 or V3)
# config/packages/nzo_elastic_query.yaml (Symfony V4)

nzo_elastic_query:
    elastic_index_prefix: 'the_index_prefix'
    
```

Usage
-----
