<?php
/**
 * Copyright (c) Meta Platforms, Inc. and affiliates. All Rights Reserved
 */

namespace Facebook\BusinessExtension\Model\System;

use Magento\Config\Model\ResourceModel\Config as ResourceConfig;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;

class Config
{
    const XML_PATH_FACEBOOK_COLLECTIONS_SYNC_IS_ACTIVE = 'facebook/catalog_management/collections_sync';

    const XML_PATH_FACEBOOK_CATALOG_SYNC_IS_ACTIVE = 'facebook/catalog_management/catalog_sync';

    const XML_PATH_FACEBOOK_OUT_OF_STOCK_THRESHOLD = 'facebook/inventory_management/out_of_stock_threshold';

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var ResourceConfig
     */
    private $resourceConfig;

    /**
     * @var TypeListInterface
     */
    private $cacheTypeList;

    /**
     * @var array
     */
    private $activeCatalogSyncWebsites;

    /**
     * @method __construct
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param ResourceConfig $resourceConfig
     * @param TypeListInterface $cacheTypeList
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        ResourceConfig $resourceConfig,
        TypeListInterface $cacheTypeList
    ) {
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->resourceConfig = $resourceConfig;
        $this->cacheTypeList = $cacheTypeList;
    }

    /**
     * @method isSingleStoreMode
     * @return bool
     */
    public function isSingleStoreMode()
    {
        return $this->storeManager->isSingleStoreMode();
    }

    /**
     * @param $path
     * @param $value
     * @return $this
     */
    public function saveConfig($path, $value)
    {
        $this->resourceConfig->saveConfig($path, $value);
        return $this;
    }

    /**
     * @param $path
     * @return $this
     */
    public function deleteConfig($path)
    {
        $this->resourceConfig->deleteConfig($path);
        return $this;
    }

    /**
     * @return $this
     */
    public function cleanCache()
    {
        $this->cacheTypeList->cleanType(\Magento\Framework\App\Cache\Type\Config::TYPE_IDENTIFIER);
        return $this;
    }

    /**
     * @return bool
     */
    public function isActiveCollectionsSync()
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_FACEBOOK_COLLECTIONS_SYNC_IS_ACTIVE);
    }

    /**
     * @return bool
     */
    public function isActiveCatalogSync($storeId = null)
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_FACEBOOK_CATALOG_SYNC_IS_ACTIVE,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            $storeId
        );
    }

    /**
     * @return array
     */
    public function getActiveCatalogSyncWebsites()
    {
        if (null !== $this->activeCatalogSyncWebsites) {
            return $this->activeCatalogSyncWebsites;
        }

        $this->activeCatalogSyncWebsites = [];

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $storeManager = $objectManager->get(\Magento\Store\Model\StoreManagerInterface::class);
        foreach ($storeManager->getWebsites() as $website) {
            $store = $website->getDefaultStore();
            if ($this->isActiveCatalogSync($store->getId())) {
                $this->activeCatalogSyncWebsites[] = $website->getId();
            }
        }
        return $this->activeCatalogSyncWebsites;
    }

    /**
     * @param $product
     * @return bool
     */
    public function isActiveCatalogSyncForProduct($product)
    {
        $activeCatalogSyncWebsites = $this->getActiveCatalogSyncWebsites();
        if (!$activeCatalogSyncWebsites) {
            return false;
        }

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $storeManager = $objectManager->get(\Magento\Store\Model\StoreManagerInterface::class);

        $isActiveCatalogSync = false;

        $categories = $product->getAvailableInCategories();
        $websiteIds = $product->getWebsiteIds();

        if ($categories) {
            foreach ($storeManager->getWebsites() as $website) {

                $store = $website->getDefaultStore();
                if (!$this->isActiveCatalogSync($store->getId())) {
                    continue;
                }

                if (!in_array($website->getId(), $websiteIds)) {
                    continue;
                }

                foreach ($categories as $categoryId) {
                    try {
                        $category = $objectManager->get(\Magento\Catalog\Api\CategoryRepositoryInterface::class)
                            ->get($categoryId, $store->getId());
                    } catch (\Exception $e) {
                        continue;
                    }

                    $rootCategoryId = $store->getRootCategoryId();

                    if ($category->getIsActive()
                        && $category->getLevel() > 1
                        && $category->getData("sync_to_facebook_catalog")
                        && in_array($rootCategoryId, $category->getPathIds())
                        && !$category->hasChildren()
                    ) {
                        $isActiveCatalogSync = true;
                        break 2;
                    }
                }
            }
        } else {
            $activeCatalogSyncWebsites = $this->getActiveCatalogSyncWebsites();
            if (array_intersect($websiteIds, $activeCatalogSyncWebsites)) {
                $isActiveCatalogSync = true;
            }
        }

        return $isActiveCatalogSync;
    }

    /**
     * @return mixed
     */
    public function getOutOfStockThreshold()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_FACEBOOK_OUT_OF_STOCK_THRESHOLD);
    }
}
