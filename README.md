NzoElasticQueryBundle
=====================

[![Build Status](https://travis-ci.org/nayzo/NzoElasticQueryBundle.svg?branch=master)](https://travis-ci.org/nayzo/NzoElasticQueryBundle)
[![Latest Stable Version](https://poser.pugx.org/nzo/elastic-query-bundle/v/stable)](https://packagist.org/packages/nzo/elastic-query-bundle)

- Symfony bundle used to execute search based on simple query language for the Elasticsearch system.
- This bundle is based on the FOSElasticaBundle implementation cf: https://github.com/FriendsOfSymfony/FOSElasticaBundle  
  
- PHP Version: **"^7.2.5"**
- Elasticsearch Version: **"5.x|6.x"**

##### Features included:
- Search: **match**, **notmatch**, **isnull**, **in**, **notin**, **gte**, **gt**, **lte**, **lt**, **range**, **wildcard**.
- Sort
- Limitation
- Pagination


###### This Bundle is compatible with **Symfony >= 4.4**


Installation
------------

Install the bundle:

```
$ composer require nzo/elastic-query-bundle
```

#### Register the bundle in config/bundles.php (without Flex)

``` php
// config/bundles.php

return [
    // ...
    Nzo\ElasticQueryBundle\ElasticQueryBundle::class => ['all' => true],
];
```

#### Configure the bundle:

``` yml
# config/packages/nzo_elastic_query.yaml

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
        
        // check access permission on the search
        return $this->elasticQuerySearch->search(
            $query,
            $entityNamespace,
            $page,
            $limit,
            ['role' => 'ROLE_SEARCH', 'message' => 'Search not authorized'] // 'message' is optional
        );
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
                    "field": "entity.title",
                    "wildcard": "*toto*"
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
                                },
                                {
                                    "and": [
                                        {
                                            // This check is needed to make sure the 'parent' is not Null
                                            "field": "parent.id",
                                            "isnull": false
                                        },
                                        {
                                            "field": "parent.text",
                                            "isnull": true
                                        }
                                    ]
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

License
-------

This bundle is under the MIT license. See the complete license in the bundle:

See [LICENSE](https://github.com/nayzo/NzoElasticQueryBundle/tree/master/LICENSE)
