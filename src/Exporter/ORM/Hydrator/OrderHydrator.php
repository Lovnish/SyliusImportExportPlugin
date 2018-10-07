<?php

declare(strict_types=1);

namespace FriendsOfSylius\SyliusImportExportPlugin\Exporter\ORM\Hydrator;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

class OrderHydrator implements HydratorInterface
{
    /**
     * @var RepositoryInterface
     */
    protected $repository;

    public function __construct(
        RepositoryInterface $repository
    ) {
        $this->repository = $repository;
    }

    /**
     * @inheritdoc
     */
    public function getHydratedResources(array $idsToExport): array
    {
        /** @var ResourceInterface[] $items */
        if (!$this->repository instanceof \Doctrine\ORM\EntityRepository) {
            return $this->repository->findBy(['id' => $idsToExport]);
        }

        $query = $this->findOrdersQb($idsToExport)->getQuery();
        $items = $this->enableEagerLoading($query)->getResult();
        $this->hydrateOrderItemsQb($idsToExport)->getQuery()->getResult(); // This result can be discarded

        return $items;
    }

    /**
     * @param int[]|string[] $idsToExport
     * @return QueryBuilder
     */
    private function findOrdersQb(array $idsToExport): QueryBuilder
    {
        return $this->repository->createQueryBuilder('o')
            ->andWhere('o.id IN (:exportIds)')
            ->setParameter('exportIds', $idsToExport)
        ;
    }

    /**
     * @param int[]|string[] $idsToExport
     * @return QueryBuilder
     */
    protected function hydrateOrderItemsQb(array $idsToExport): QueryBuilder
    {
        // Partial hydration to make sure order items don't get lazy-loaded
        return $this->repository->createQueryBuilder('o')
            ->select('PARTIAL o.{id}, items')
            ->leftJoin('o.items', 'items')
            ->andWhere('o.id IN (:exportIds)')
            ->setParameter('exportIds', $idsToExport)
        ;
    }

    protected function enableEagerLoading(Query $query): Query
    {
        return $query
            ->setFetchMode(
                $this->repository->getClassName(),
                'customer',
                ClassMetadata::FETCH_EAGER
            )
            ->setFetchMode(
                $this->repository->getClassName(),
                'shippingAddress',
                ClassMetadata::FETCH_EAGER
            )
            ->setFetchMode(
                $this->repository->getClassName(),
                'billingAddress',
                ClassMetadata::FETCH_EAGER
            )
            ;
    }
}
