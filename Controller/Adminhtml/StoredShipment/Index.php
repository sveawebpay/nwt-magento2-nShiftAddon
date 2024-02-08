<?php declare(strict_types=1);

namespace Svea\NShiftAddon\Controller\Adminhtml\StoredShipment;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Sales\Model\Order\ShipmentRepository;
use Svea\NShiftAddon\Service\Api\StoredShipment;

/**
 * Creates a stored shipment in nShift based on a Magento Shipments
 */
class Index extends Action
{
    const ADMIN_RESOURCE = 'Svea_Checkout::system_config';

    private ShipmentRepository $shipmentRepo;

    private StoredShipment $storedShipmentService;

    public function __construct(
        Action\Context $context,
        ShipmentRepository $shipmentRepo,
        StoredShipment $storedShipmentService
    ) {
        parent::__construct($context);
        $this->shipmentRepo = $shipmentRepo;
        $this->storedShipmentService = $storedShipmentService;
    }

    public function execute()
    {
        $shipmentId = $this->getRequest()->getParam('shipment_id') ?? 0;

        try {
            $shipment = $this->shipmentRepo->get($shipmentId);
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Failed to load Shipment'));
            return $this->redirectBackToShipmentView();
        }

        try {
            $this->storedShipmentService->createStoredShipment($shipment);
        } catch (\Exception $e) {
            $message = 'Error occurred when attempting to create stored shipment. Check error logs.';
            $this->messageManager->addErrorMessage(__($message));
            return $this->redirectBackToShipmentView();
        }

        $this->messageManager->addSuccessMessage(__('Stored Shipment has been created'));
        return $this->redirectBackToShipmentView();
    }

    /**
     * @return Redirect
     */
    private function redirectBackToShipmentView(): Redirect
    {
        return $this->resultRedirectFactory->create()->setPath(
            'sales/shipment/view',
            ['shipment_id' => $this->getRequest()->getParam('shipment_id')]
        );
    }
}
