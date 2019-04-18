NzoElasticQueryBundle
=====================

- Symfony bundle used to execute search based on simple query language for the Elasticsearch system.
- This bundle is based on the FOSElasticaBundle implementation cf: https://github.com/FriendsOfSymfony/FOSElasticaBundle

##### Features included:
- Search: match, notmatch, in, notin, gte, gt, lte, lt, range.
- Sort
- Limitation
- Pagination


##### Requirement:

- Symfony 3.4 or 4.x


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
    elastic_index_prefix:  # optional (the index prefix)
    default_page_number:   # optional (default 1)
    limit_per_page:        # optional (default 100)
    items_max_limit:       # optional (default 1000)
    
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
        // $entityNamespace === 'App\Entity\FooBar'
        
        return $this->elasticQuerySearch->search($query, $entityNamespace, $page, $limit);
    }
}
```

Configure index
---------------
```yaml
fos_elastica:
    indexes:
        foo_bar: # the index name must reflect the entity name in "snake_case", exp: foo_bar
            types:
                fooBar: # the type name must reflect the entity name in "camelCase", exp: fooBar
                    properties:
                        id:
                            type: keyword
                            index: true
                        createdAt:
                            type:   date
                    persistence:
                        driver: orm
                        model: App\Entity\FooBar
                        provider: ~
                        finder: ~
                        repository: Nzo\ElasticQueryBundle\Repository\SearchRepository
```

Populate index
--------------
```bash
$ bin/console fos:elastica:populate
```

Payload Example
---------------

POST  http://example.fr/search/myEnitity?page=1&limit=2

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

