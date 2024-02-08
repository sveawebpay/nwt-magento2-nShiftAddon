<?php declare(strict_types=1);

namespace Svea\NSHiftAddon\Block\Adminhtml\Shipping;

use Magento\Shipping\Block\Adminhtml\View as Original;
use Svea\Checkout\Model\Shipping\Carrier;

/**
 * Adds button to create Stored Shipment if the current shipment is missing one
 */
class View extends Original
{
    protected function _construct()
    {
        parent::_construct();

        // If nShift track is missing, add button to try to create stored shipment again
        $shipment = $this->getShipment();
        $nShiftTracks = $shipment->getTracksCollection()->addFieldToFilter('carrier_code', Carrier::CODE);
        if ($nShiftTracks->getSize() > 0) {
            return;
        }

        $this->addButton(
            'createNShiftStoredShipment',
            [
                'label' => __('Create nShift Stored Shipment'),
                'class' => 'save primary',
                'onclick' => 'setLocation(\'' . $this->getStoredShipmentUrl() . '\')'
            ]
        );
    }

    /**
     * Generate URL to create stored shipment controller
     *
     * @return string
     */
    private function getStoredShipmentUrl(): string
    {
        return $this->getUrl(
            'svea_nshift/storedShipment/index',
            ['shipment_id' => $this->getShipment()->getId()]
        );
    }
}
