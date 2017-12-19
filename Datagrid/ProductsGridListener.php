<?php

namespace Oro\Bundle\CustomGridBundle\Datagrid;

use Doctrine\ORM\EntityManager;

use Symfony\Bridge\Doctrine\RegistryInterface;

use Oro\Bundle\DataGridBundle\Datasource\ResultRecord;
use Oro\Bundle\DataGridBundle\Event\BuildBefore;
use Oro\Bundle\DataGridBundle\Event\OrmResultAfter;
use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\OrganizationBundle\Entity\BusinessUnit;

use OroB2B\Bundle\PricingBundle\Entity\PriceListToProduct;
use OroB2B\Bundle\ProductBundle\Entity\Product;
use OroB2B\Bundle\PricingBundle\Entity\PriceList;

class ProductsGridListener
{
    /**
     * @var RegistryInterface
     */
    protected $registry;

    /**
     * @param RegistryInterface $registry
     */
    public function __construct(RegistryInterface $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @param BuildBefore $event
     */
    public function onBuildBefore(BuildBefore $event)
    {
        $datagridConfiguration = $event->getConfig();
        $this->addBusinessUnitColumn($datagridConfiguration);
        $this->addPriceListsColumn($datagridConfiguration);
        $this->addPriceListsFilter($datagridConfiguration);
    }

    /**
     * @param OrmResultAfter $event
     */
    public function onResultAfter(OrmResultAfter $event)
    {
        $records = $event->getRecords();
        $this->addPriceListsToRecords($records);
    }

    /**
     * @param DatagridConfiguration $datagridConfiguration
     */
    protected function addPriceListsColumn(DatagridConfiguration $datagridConfiguration)
    {
        $column = [
            'label' => 'Price Lists',
            'type' => 'twig',
            'template' => 'OroCustomGridBundle:Datagrid:Column/price_lists.html.twig',
            'frontend_type' => 'html',
            'renderable' => true,
        ];
        $datagridConfiguration->addColumn('price_lists', $column);
    }

    /**
     * @param DatagridConfiguration $datagridConfiguration
     */
    protected function addBusinessUnitColumn(DatagridConfiguration $datagridConfiguration)
    {
        $datagridConfiguration->joinTable(
            'left',
            [
                'join' => BusinessUnit::class,
                'alias' => 'business_unit',
                'conditionType' => 'WITH',
                'condition' => 'product.owner = business_unit',
            ]
        );

        $column = [
            'label' => 'Owner'
        ];

        // column name should be equal with select alias
        $datagridConfiguration->addColumn('owner', $column, 'business_unit.name as owner');
    }

    /**
     * @param DatagridConfiguration $datagridConfiguration
     */
    protected function addPriceListsFilter(DatagridConfiguration $datagridConfiguration)
    {
        $filter = [
            'type' => 'product-price-lists',
            'data_name' => 'price_lists',
            'options' => [
                'field_type' => 'entity',
                'field_options' => [
                    'class' => PriceList::class,
                    'property' => 'name',
                    'multiple' => true
                ]
            ]
        ];

        $datagridConfiguration->addFilter('price_lists', $filter);
    }

    /**
     * @param ResultRecord[] $records
     * @throws \Doctrine\ORM\ORMException
     */
    protected function addPriceListsToRecords(array $records)
    {
        $repository = $this->registry->getRepository(PriceListToProduct::class);
        /** @var EntityManager $objectManager */
        $objectManager = $this->registry->getManager();

        $products = [];
        foreach ($records as $record) {
            $products[] = $objectManager->getReference(Product::class, $record->getValue('id'));
        }

        $priceLists = [];
        foreach ($repository->findBy(['product' => $products]) as $item) {
            $priceLists[$item->getProduct()->getId()][] = $item->getPriceList();
        }

        /** @var ResultRecord $record */
        foreach ($records as $record) {
            $id = $record->getValue('id');
            $products[] = $objectManager->getReference(Product::class, $id);

            $record->addData(['price_lists' => $priceLists[$id]]);
        }
    }
}
