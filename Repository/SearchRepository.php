<?php

namespace Nzo\ElasticQueryBundle\Repository;

use FOS\ElasticaBundle\Repository;

class SearchRepository extends Repository
{
    const PAGE_DEFAULT = 1;
    const LIMIT_DEFAULT = 100;
    const LIMIT_MAX = 1000;

    /**
     * @param array $query
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function search(array $query, $page, $limit)
    {
        if (empty($page) || !is_int($page) || $page < 1) {
            $page = self::PAGE_DEFAULT;
        }

        if (empty($limit) || !is_int($limit) || $limit < 1) {
            $limit = self::LIMIT_DEFAULT;
        } else {
            $limit = min($limit, self::LIMIT_MAX);
        }

        $adapter = $this->finder->createPaginatorAdapter($query)->getResults(($page - 1) * $limit, $limit);
        $totalHits = $adapter->getTotalHits();

        return [
            'items' => $adapter->toArray(),
            'totalItems' => $totalHits,
            'totalPages' => $totalHits ? ceil($totalHits / $limit) : 1,
        ];
    }
}
