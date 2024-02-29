<?php declare(strict_types=1);

namespace Svea\NShiftAddon\Service\Api;

use Magento\Framework\HTTP\Client\CurlFactory as CurlFactory;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment as OrderShipment;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Magento\Sales\Model\Order\Shipment\TrackRepository;
use Magento\Framework\Escaper;
use Magento\Framework\Logger\Monolog as Logger;
use Svea\NShiftAddon\Service\Config;
use Svea\NShiftAddon\Exception\ApiException;
use Svea\Checkout\Service\SveaShippingInfo;
use Svea\Checkout\Model\Shipping\Carrier;

class StoredShipment
{
    const API_URL = 'https://api.unifaun.com/rs-extapi/v1/';

    const TRACK_TITLE = 'nShift Stored Shipment ID';

    private Config $config;

    private CurlFactory $curlFactory;

    private Json $serializer;

    private SveaShippingInfo $sveaShippingInfo;

    private TrackFactory $trackFactory;

    private TrackRepository $trackRepo;

    private Logger $logger;

    private Escaper $escaper;

    private ?Order $order = null;

    private ?OrderShipment $shipment = null;

    public function __construct(
        Config $config,
        CurlFactory $curlFactory,
        Json $serializer,
        SveaShippingInfo $sveaShippingInfo,
        TrackFactory $trackFactory,
        TrackRepository $trackRepo,
        Logger $logger,
        Escaper $escaper
    ) {
        $this->config = $config;
        $this->curlFactory = $curlFactory;
        $this->serializer = $serializer;
        $this->sveaShippingInfo = $sveaShippingInfo;
        $this->trackFactory = $trackFactory;
        $this->trackRepo = $trackRepo;
        $this->logger = $logger;
        $this->escaper = $escaper;
    }

    /**
     * Creates a prepared shipment, then a stored shipment
     *
     * @param Order $order
     * @param OrderShipment $shipment
     * @return void
     * @throws ApiException
     */
    public function createStoredShipment(OrderShipment $shipment): void
    {
        $this->order = $shipment->getOrder();
        $this->shipment = $shipment;

        $this->createPreparedShipment();

        $curl = $this->createCurl();
        $bodyData = $this->getStoredShipmentData();
        $requestBody = $this->serializer->serialize($bodyData);

        // Prepared shipment ID = order increment ID
        $targetUri = self::API_URL . 'prepared-shipments/' . $this->order->getIncrementId(). '/stored-shipments';
        $curl->post($targetUri, $requestBody);

        try {
            $this->handleCurlError($curl);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new ApiException(__($e->getMessage()));
        }

        $responseBody = $curl->getBody();

        if (!$responseBody) {
            $message = 'Empty response on create stored shipment attempt for order: ' . $this->order->getIncrementId();
            $this->logger->error($message);
            throw new ApiException(__($message));
        }

        $responseArray = $this->serializer->unserialize($responseBody);
        $track = $this->trackFactory->create();
        $track->setCarrierCode(Carrier::CODE);
        $track->setTrackNumber($responseArray['id']);
        $track->setTitle(self::TRACK_TITLE);
        $this->shipment->addTrack($track);

        // We save using track repo instead of shipment repo to avoid re-running observer methods
        $this->trackRepo->save($track);
    }

    /**
     * Throws exception on Curl error
     *
     * @param Curl $curl
     * @return void
     * @throws \Exception
     */
    private function handleCurlError(Curl $curl): void
    {
        $status = $curl->getStatus();
        if ($status < 300) {
            return;
        }

        $messageFormat = 'Curl Error: HTTP %s: %s';
        $curl->doError(sprintf($messageFormat, $curl->getStatus(), $curl->getBody()));
    }

    /**
     * Convert order to array shipment data for creating Stored Shipment
     *
     * @param Order $order
     * @return array
     */
    private function getStoredShipmentData(): array
    {
        $storeId = (int)$this->order->getStoreId();

        $shipmentData = [
            'sender' => [
                'quickId' => $this->config->getSenderQuickId($storeId)
            ],
            'orderNo' => $this->order->getIncrementId(),
            'receiver' => $this->getReceiver(),
            'parcels' => $this->getParcels()
        ];

        return array_filter($shipmentData);
    }

    /**
     * Convert order to array shipment data for creating prepared shipment
     *
     * @param Order $order
     * @return array
     */
    private function getPreparedShipmentData(): array
    {
        $storeId = (int)$this->order->getStoreId();

        $shipmentData = [
            'sender' => [
                'quickId' => $this->config->getSenderQuickId($storeId)
            ],
            'orderNo' => $this->order->getIncrementId(),
            'receiver' => $this->getReceiver(),
            'agent' => $this->getAgentData()
        ];

        return array_filter($shipmentData);
    }

    /**
     * @return array
     */
    private function getReceiver(): array
    {
        $shipping   = $this->shipment->getShippingAddress();
        $billing    = $this->shipment->getBillingAddress();
        $receiver = [];

        $phone = $shipping->getTelephone() ? $shipping->getTelephone() : $billing->getTelephone();
        $email = $billing->getEmail() ? $billing->getEmail(): $shipping->getEmail();

        $receiver['name'] = $this->escaper->escapeHtml($shipping->getName());
        $receiver['address1'] = $this->escaper->escapeHtml($shipping->getStreetLine(1));
        $receiver['address2'] = $this->escaper->escapeHtml($shipping->getStreetLine(2));
        $receiver['zipcode'] = $this->escaper->escapeHtml($shipping->getPostcode());
        $receiver['city'] = $this->escaper->escapeHtml($shipping->getCity());
        $receiver['country'] = $shipping->getCountryId();
        $receiver['phone'] = $phone;
        $receiver['email'] = $email;
        $receiver['contact'] = $shipping->getName();
        $receiver['fax'] = $shipping->getFax();
        $receiver['vatno'] =$this->order->getCustomerTaxvat();

        return array_filter($receiver);
    }

    /**
     * Return carrier specific data, for example pickup agent
     * @return array
     */
    private function getAgentData(): array
    {
        $orderSveaShippingInfo = $this->sveaShippingInfo->getFromOrder($this->order);
        if (null === $orderSveaShippingInfo) {
            return [];
        }

        // Agent is called 'location' in the Svea shipping solution
        $orderAgentData = $orderSveaShippingInfo['location'] ?? [];
        if (!$orderAgentData) {
            return [];
        }

        // Mandatory values for agents are 'name', 'city', and 'country'
        // At this point the 'location' should always have 'address' property,
        //  If it somehow doesn't, we should exit to prevent fatal errors
        $agentAddress = $orderAgentData['address'] ?? [];
        if (!$agentAddress) {
            return [];
        }

        $processedAgentData = [
            'name' => $orderAgentData['name'] ?? '',
            'city' => $agentAddress['city'] ?? '',
            'country' => $agentAddress['countryCode'] ?? '',
            'zipcode' => $agentAddress['postalCode'] ?? '',
            'address1' => $agentAddress['streetAddress'] ?? '',
            'address2' => $agentAddress['streetAddress2'] ?? ''
        ];

        return $processedAgentData;
    }

    /**
     * @return array
     */
    private function getParcels(): array
    {
        $weight = $this->shipment->getTotalWeight() ?? 0.01;
        $parcel = [
            'copies' => 1,
            'weight' => round($weight, 2)
        ];

        // We add product names as parcel content info
        $parcelContents = [];
        $items = $this->shipment->getAllItems();
        foreach ($items as $item) {
            /** @var \Magento\Sales\Model\Order\Shipment\Item $item */
            $parcelContents[] = trim((string)$item->getName());
        }
        $parcelContents = implode('. ', $parcelContents);

        if (!!$parcelContents) {
            $parcel['contents'] = $parcelContents;
        }

        return [$parcel];
    }

    /**
     * Create a prepared shipment
     *
     * @return void
     * @throws ApiException
     */
    private function createPreparedShipment(): void
    {
        $orderSveaShippingInfo = $this->sveaShippingInfo->getFromOrder($this->order);
        $orderWeight = $this->order->getWeight();
        $requestData = [
            'shipment'           => $this->getPreparedShipmentData(),
            'selectedOptionId'   => $orderSveaShippingInfo['id'],
            'selectedAddons'     => $orderSveaShippingInfo['addons'] ?? false,
            'prepareId'          => $this->order->getIncrementId(),
            'returnShipmentData' => false,
            'weight'             => $orderWeight > 0 ? $orderWeight : 0.01
        ];

        $deliveryCheckoutId = $this->config->getDeliveryCheckoutId((int)$this->order->getStoreId());
        $targetUri = self::API_URL . 'delivery-checkouts/' . $deliveryCheckoutId;

        $requestBody = $this->serializer->serialize(array_filter($requestData));

        $curl = $this->createCurl();
        $curl->post($targetUri, $requestBody);
        try {
            $this->handleCurlError($curl);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new ApiException(__($e->getMessage()));
        }
    }

    /**
     * Create Curl client instance with authentication
     *
     * @return \Magento\Framework\HTTP\Client\Curl
     */
    private function createCurl(): \Magento\Framework\HTTP\Client\Curl
    {
        $curl = $this->curlFactory->create();
        $storeId = (int)$this->order->getStoreId();
        $curl->setCredentials(
            $this->config->getPublicKey($storeId),
            $this->config->getPrivateKey($storeId)
        );
        $curl->addHeader('Content-Type', 'application/json');
        return $curl;
    }
}
