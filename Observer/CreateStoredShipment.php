<?php declare(strict_types=1);

namespace Svea\NShiftAddon\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Message\Manager;
use Svea\NShiftAddon\Exception\ApiException;
use Svea\NShiftAddon\Service\Config;
use Svea\NShiftAddon\Service\Api\StoredShipment;
use Svea\Checkout\Model\Shipping\Carrier;

/**
 * Observer which will create stored shipment in nShift
 */
class CreateStoredShipment implements ObserverInterface
{
    private Config $config;

    private StoredShipment $storedShipmentService;

    private Manager $messageManager;

    public function __construct(
        Config $config,
        StoredShipment $storedShipmentService,
        Manager $messageManager
    ) {
        $this->config = $config;
        $this->storedShipmentService = $storedShipmentService;
        $this->messageManager = $messageManager;
    }

    /**
     * For event: sales_order_shipment_save_after
     *
     * @param EventObserver $observer
     * @return void
     */
    public function execute(EventObserver $observer): void
    {
        /** @var \Magento\Sales\Model\Order\Shipment $shipment */
        $shipment = $observer->getEvent()->getShipment();

        // Exit if shipping method is not Svea Shipping, if shipment is not new, or if addon is not active in config
        $shippingMethodObject = $shipment->getOrder()->getShippingMethod(true);
        $exit = (
            $shippingMethodObject->getCarrierCode() !== Carrier::CODE
            || $shipment->getOrigData('entity_id')
            || !$this->config->isActive((int)$shipment->getStoreId())
        );

        if ($exit) {
            return;
        }

        try {
            $this->storedShipmentService->createStoredShipment($shipment);
        } catch (ApiException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
    }
}
