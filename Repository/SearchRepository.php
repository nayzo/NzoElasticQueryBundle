<?php

namespace Nzo\ElasticQueryBundle\Repository;

use FOS\ElasticaBundle\Repository;

class SearchRepository extends Repository
{
    /**
     * @param array $query
     * @param int|null $page
     * @param int|null $limit
     * @param array $options (int defaultPageNumber, int limitPerPage, int itemsMaxLimit)
     * @return array
     */
    public function executeSearch(array $query, $page, $limit, array $options)
    {
        list($defaultPageNumber, $limitPerPage, $itemsMaxLimit) = $options;
        if (empty($page) || !is_int($page) || $page < 1) {
            $page = $defaultPageNumber;
        }

        if (empty($limit) || !is_int($limit) || $limit < 1) {
            $limit = $limitPerPage;
        } else {
            $limit = min($limit, $itemsMaxLimit);
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
