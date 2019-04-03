NzoElasticQueryBundle
=====================

- Symfony bundle used to execute search based on simple query language on Elasticsearch system.
##### Features included:
- Search: match, notmatch, in, notin, gte, gt, lte, lt, range.
- Sort
- Limitation
- Pagination

Installation
------------

Install the bundle:

```
$ composer require nzo/elastic-query-bundle
```

Register the bundle in app/AppKernel.php (Symfony V3):

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

Configure the bundle:

``` yml
# app/config/config.yml (Symfony V3)
# config/packages/nzo_elastic_query.yaml (Symfony V4)

nzo_elastic_query:
    elastic_index_prefix: 'the_index_prefix'  # optional
    
```

Usage
-----
```php
use Nzo\ElasticQueryBundle\Query\ElasticQuerySearch;

class MyClass
{
    /**
     * @var ElasticQuerySearch
     */
    private $elasticQuerySearch;
    
    public function __construct(ElasticQuerySearch $elasticQuerySearch)
    {
        $this->elasticQuerySearch = $elasticQuerySearch;
    }
    
    /**
     * @param string|array $query  (json or array)
     * @param string $entityNamespace The FQCN (fully qualified class name) of the entity to execute the search on.
     * @param null|int $page
     * @param null|int $limit
     * @return array
     */
    public funtion foo($query, $entityNamespace, $page = null, $limit = null)
    {
        return $this->elasticQuerySearch->search($query, $entityNamespace, $page, $limit);
    }
}
```

Payload Exemple
---------------

POST  http://exemple.fr/search/myEnitity?page=1&limit=2

```json
{
    "query": {
        "search": {
            "or": [
                {
                    "field": "status",
                    "match": "foo"
                },
                {
                    "field": "entity.title",
                    "notmatch": "bar"
                },
                {
                    "and": [
                        {
                            "field": "lastname",
                            "notin": [
                                "titi",
                                "tata"
                            ]
                        },
                        {
                            "or": [
                                {
                                    "field": "age",
                                    "range": [
                                        20,
                                        30
                                    ]
                                },
                                {
                                    "field": "parent.age",
                                    "gte": 25
                                }
                            ]
                        }
                    ]
                }
            ]
        },
        "sort": [
            {
                "field": "createdAt",
                "order": "ASC"
            }
        ]
    }
}
```
