<?php
/**
 * Copyright (c) Meta Platforms, Inc. and affiliates. All Rights Reserved
 */

namespace Facebook\BusinessExtension\Observer;

use Facebook\BusinessExtension\Helper\FBEHelper;
use Facebook\BusinessExtension\Model\Feed\CategoryCollection;
use Facebook\BusinessExtension\Model\System\Config as SystemConfig;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ProcessCategoryAfterSaveEventObserver implements ObserverInterface
{
    /**
     * @var FBEHelper
     */
    protected $_fbeHelper;
    /**
     * @var SystemConfig
     */
    protected $systemConfig;

    /**
     * Constructor
     * @param FBEHelper $helper
     * @param SystemConfig $systemConfig
     */
    public function __construct(
        FBEHelper $helper,
        SystemConfig $systemConfig
    ) {
        $this->_fbeHelper = $helper;
        $this->systemConfig = $systemConfig;
    }

    /**
     * Call an API to category save from facebook catalog
     * after save category from Magento
     *
     * @param Observer $observer
     * @return
     */
    public function execute(Observer $observer)
    {
        if (!$this->systemConfig->getActiveCatalogSyncWebsites()) {
            return;
        }

        $category = $observer->getEvent()->getCategory();
        $this->_fbeHelper->log("save category: ".$category->getName());
        /** @var CategoryCollection $categoryObj */
        $categoryObj = $this->_fbeHelper->getObject(CategoryCollection::class);
        $syncEnabled = $category->getData("sync_to_facebook_catalog");
        if ($syncEnabled === "0") {
            $this->_fbeHelper->log("user disabled category sync");
            return;
        }

        if (!$categoryObj->getCategoryProductIds($category)) {
            $this->_fbeHelper->log("Category does not have products");
            return;
        }

        $response = $categoryObj->makeHttpRequestAfterCategorySave($category);
        return $response;
    }
}
