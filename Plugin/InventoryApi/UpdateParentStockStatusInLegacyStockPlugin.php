<?php
/**
 * Copyright © Soft Commerce Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace SoftCommerce\InventoryConfigurableProduct\Plugin\InventoryApi;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Inventory\Model\SourceItem\Command\DecrementSourceItemQty;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryCatalogApi\Api\DefaultSourceProviderInterface;
use Magento\InventoryCatalogApi\Model\GetProductIdsBySkusInterface;
use Magento\ConfigurableProduct\Model\Inventory\ChangeParentStockStatus;

/**
 * Apply a fix for update configurable product stock status in legacy stock
 * after decrement quantity of child stock item
 */
class UpdateParentStockStatusInLegacyStockPlugin
{
    /**
     * @var DefaultSourceProviderInterface
     */
    private $defaultSourceProvider;

    /**
     * @var ChangeParentStockStatus
     */
    private $changeParentStockStatus;

    /**
     * @var GetProductIdsBySkusInterface
     */
    private $getProductIdsBySkus;

    /**
     * @param DefaultSourceProviderInterface $defaultSourceProvider
     * @param GetProductIdsBySkusInterface $getProductIdsBySkus
     * @param ChangeParentStockStatus $changeParentStockStatus
     */
    public function __construct(
        DefaultSourceProviderInterface $defaultSourceProvider,
        GetProductIdsBySkusInterface $getProductIdsBySkus,
        ChangeParentStockStatus $changeParentStockStatus
    ) {
        $this->defaultSourceProvider = $defaultSourceProvider;
        $this->getProductIdsBySkus = $getProductIdsBySkus;
        $this->changeParentStockStatus = $changeParentStockStatus;
    }

    /**
     * Make configurable product out of stock if all its children out of stock
     *
     * @param DecrementSourceItemQty $subject
     * @param void $result
     * @param SourceItemInterface[] $sourceItemDecrementData
     * @return void
     * @throws NoSuchEntityException
     */
    public function afterExecute(DecrementSourceItemQty $subject, $result, array $sourceItemDecrementData): void
    {
        $productIds = [];
        $sourceItems = array_column($sourceItemDecrementData, 'source_item');

        /** @var SourceItemInterface $sourceItem */
        foreach ($sourceItems as $sourceItem) {
            $sku = $sourceItem->getSku();
            if ($sourceItem->getSourceCode() === $this->defaultSourceProvider->getCode()
                && $productId = ($this->getProductIdsBySkus->execute([$sku])[$sku] ?? null)
            ) {
                $productIds[] = (int) $productId;
            }
        }

        if ($productIds) {
            $this->changeParentStockStatus->execute($productIds);
        }
    }
}
