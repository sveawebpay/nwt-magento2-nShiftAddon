<?php declare(strict_types=1);

namespace Svea\NShiftAddon\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Config access service
 */
class Config
{
    const BASE_CONFIG_PATH = 'svea_checkout_nshiftaddon/settings/';

    private ScopeConfigInterface $scopeConfig;

    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param integer|null $storeId
     * @return boolean
     */
    public function isActive(?int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::BASE_CONFIG_PATH . 'active',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param integer|null $store
     * @return string
     */
    public function getPublicKey(?int $store = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::BASE_CONFIG_PATH . 'public_api_key',
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @param integer|null $store
     * @return string
     */
    public function getPrivateKey(?int $store = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::BASE_CONFIG_PATH . 'private_api_key',
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @param integer|null $store
     * @return string
     */
    public function getDeliveryCheckoutId(?int $store = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::BASE_CONFIG_PATH . 'delivery_checkout_id',
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @param integer|null $store
     * @return int
     */
    public function getSenderQuickId(?int $store = null): int
    {
        return (int)$this->scopeConfig->getValue(
            self::BASE_CONFIG_PATH . 'sender_quick_id',
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }
}
