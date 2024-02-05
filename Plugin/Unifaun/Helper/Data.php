<?php declare(strict_types=1);

namespace Svea\NShiftAddon\Plugin\Unifaun;

use Nwt\Unifaun\Helper\Data as Subject;
use Svea\Checkout\Model\Shipping\Carrier;

/**
 * Plugin for NWT Unifaun general Helper
 */
class Data
{
    /**
     * Return true also if shipping method is Svea nShift
     *
     * @param Subject $subject
     * @param boolean $result
     * @param string $shippingMethod
     * @return boolean
     */
    public function afterCarrierIsUnifaun(Subject $subject, bool $result, string $shippingMethod): bool
    {
        return $result || $this->isSveaCarrier((string)$shippingMethod);
    }

    /**
     * @param string $shippingMethod
     * @return boolean
     */
    private function isSveaCarrier(string $shippingMethod): bool
    {
        return strpos($shippingMethod, Carrier::CODE) !== false;
    }
}
