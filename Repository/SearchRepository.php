<?php

/**
 * This file is part of the NzoElasticQueryBundle package.
 *
 * (c) Ala Eddine Khefifi <alakhefifi@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Nzo\ElasticQueryBundle\Repository;

use FOS\ElasticaBundle\Repository;

class SearchRepository extends Repository
{
    /**
     * @param array $query
     * @param int|null $page
     * @param int|null $limit
     * @param array $options (int defaultPageNumber, int limitPerPage, int itemsMaxLimit, bool showScore)
     * @return array
     */
    public function executeSearch(array $query, $page, $limit, array $options)
    {
        list($defaultPageNumber, $limitPerPage, $itemsMaxLimit, $showScore) = $options;
        if (empty($page) || !\is_int($page) || $page < 1) {
            $page = $defaultPageNumber;
        }

        if (empty($limit) || !\is_int($limit) || $limit < 1) {
            $limit = $limitPerPage;
        } else {
            $limit = \min($limit, $itemsMaxLimit);
        }

        if ($showScore) {
            $adapter = $this->finder->findHybridPaginated($query);
            $adapter->setMaxPerPage($limit);
            $adapter->setCurrentPage($page);

            /** @var FOS\ElasticaBundle\HybridResult $list */
            $items = [];
            foreach ($adapter->getIterator() as $hybridResult) {
                $object = $hybridResult->getTransformed();
                if (method_exists($object,'setScoreElastic')) {
                    $object->setScoreElastic($hybridResult->getResult()->getScore());
                }
                $items[] = $hybridResult->getTransformed();
            }

            $totalHits = $adapter->getNbResults();

            return [
                'items' => $items,
                'totalItems' => $adapter->getNbResults(),
                'totalPages' => $totalHits ? (int)\ceil($totalHits / $limit) : 1,
            ];
        }

        $adapter = $this->finder->createPaginatorAdapter($query)->getResults(($page - 1) * $limit, $limit);
        $totalHits = $adapter->getTotalHits();

        return [
            'items' => $adapter->toArray(),
            'totalItems' => $totalHits,
            'totalPages' => $totalHits ? (int)\ceil($totalHits / $limit) : 1,
        ];
    }
}
