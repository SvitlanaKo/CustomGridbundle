<?php

namespace Oro\Bundle\CustomGridBundle\Filter;

use Symfony\Bridge\Doctrine\RegistryInterface;

use Oro\Bundle\FilterBundle\Datasource\FilterDatasourceAdapterInterface;
use Oro\Bundle\FilterBundle\Datasource\Orm\OrmFilterDatasourceAdapter;
use Oro\Bundle\FilterBundle\Filter\EntityFilter;

use OroB2B\Bundle\PricingBundle\Entity\PriceListToProduct;

class ProductPriceListsFilter extends EntityFilter
{
    /**
     * @var RegistryInterface
     */
    protected $registry;

    /**
     * @inheritdoc
     */
    public function apply(FilterDatasourceAdapterInterface $ds, $data)
    {
        /** @var array $data */
        $data = $this->parseData($data);
        if (!$data) {
            return false;
        }

        $this->restrictByPriceList($ds, $data['value']);

        return true;
    }

    /**
     * @param RegistryInterface $registry
     */
    public function setRegistry(RegistryInterface $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @param OrmFilterDatasourceAdapter|FilterDatasourceAdapterInterface $ds
     * @param array $priceLists
     */
    public function restrictByPriceList($ds, array $priceLists)
    {
        $queryBuilder = $ds->getQueryBuilder();
        $parentAlias = $queryBuilder->getRootAliases()[0];
        $parameterName = $ds->generateParameterName('price_lists');

        $repository = $this->registry->getRepository(PriceListToProduct::class);
        $subQueryBuilder = $repository->createQueryBuilder('relation');
        $subQueryBuilder->where(
            $subQueryBuilder->expr()->andX(
                $subQueryBuilder->expr()->eq('relation.product', $parentAlias),
                $subQueryBuilder->expr()->in('relation.priceList', ":$parameterName")
            )
        );

        $queryBuilder->andWhere($subQueryBuilder->expr()->exists($subQueryBuilder->getQuery()->getDQL()));
        $queryBuilder->setParameter($parameterName, $priceLists);
    }
}
