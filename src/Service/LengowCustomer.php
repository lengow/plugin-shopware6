<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use \Exception;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Lengow\Connector\Util\EnvironmentInfoProvider;

/**
 * Class LengowCustomer
 * @package Lengow\Connector\Service
 */
class LengowCustomer
{
    /**
     * @var LengowAddress Lengow address service
     */
    private $lengowAddress;

    /**
     * @var LengowLog Lengow Log service
     */
    private $lengowLog;

    /**
     * @var EntityRepositoryInterface Shopware customer repository
     */
    private $customerRepository;

    /**
     * @var NumberRangeValueGeneratorInterface Shopware number range value generator interface
     */
    private $numberRangeValueGenerator;

    /**
     * @var EnvironmentInfoProvider Lengow environment info provider utility
     */
    private $environmentInfoProvider;

    /**
     * LengowCustomer constructor
     *
     * @param LengowAddress $lengowAddress Lengow address service
     * @param LengowLog $lengowLog Lengow log service
     * @param EntityRepositoryInterface $customerRepository Shopware customer repository
     * @param NumberRangeValueGeneratorInterface $numberRangeValueGenerator Shopware number generator service
     * @param EnvironmentInfoProvider $environmentInfoProvider Environment info provider utility
     */
    public function __construct(
        LengowAddress $lengowAddress,
        LengowLog $lengowLog,
        EntityRepositoryInterface $customerRepository,
        NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        EnvironmentInfoProvider $environmentInfoProvider
    )
    {
        $this->lengowAddress = $lengowAddress;
        $this->lengowLog = $lengowLog;
        $this->customerRepository = $customerRepository;
        $this->numberRangeValueGenerator = $numberRangeValueGenerator;
        $this->environmentInfoProvider = $environmentInfoProvider;
    }

    /**
     * Get Shopware customer by id
     *
     * @param string $customerId Shopware customer id
     *
     * @return CustomerEntity|null
     */
    public function getCustomerById(string $customerId): ?CustomerEntity
    {
        $criteria = new Criteria();
        $criteria->setIds([$customerId]);
        /** @var CustomerCollection $customerCollection */
        $customerCollection = $this->customerRepository->search($criteria, Context::createDefaultContext())
            ->getEntities();
        if ($customerCollection->count() !== 0) {
            return $customerCollection->first();
        }
        return null;
    }

    /**
     * Get Shopware customer by email and sales channel id
     *
     * @param SalesChannelEntity $salesChannel Shopware sales channel entity
     * @param string $customerEmail fictitious customer email
     *
     * @return CustomerEntity|null
     */
    public function getCustomerByEmail(SalesChannelEntity $salesChannel, string $customerEmail): ?CustomerEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('email', $customerEmail),
            new EqualsFilter('salesChannelId', $salesChannel->getId()),
        ]));
        /** @var CustomerCollection $customerCollection */
        $customerCollection = $this->customerRepository->search($criteria, Context::createDefaultContext())
            ->getEntities();
        if ($customerCollection->count() !== 0) {
            return $customerCollection->first();
        }
        return null;
    }

    /**
     * Create a Shopware Customer
     *
     * @param SalesChannelEntity $salesChannel Shopware sales channel entity
     * @param string $customerEmail fictitious customer email
     *
     * @return CustomerEntity|null
     */
    public function createCustomer(SalesChannelEntity $salesChannel, string $customerEmail): ?CustomerEntity
    {
        $customerId = Uuid::randomHex();
        $defaultAddressId = Uuid::randomHex();
        $defaultAddress = $this->lengowAddress->getAddressData(LengowAddress::TYPE_BILLING);
        // get Lengow payment method
        $lengowPaymentMethod = $this->environmentInfoProvider->getLengowPaymentMethod();
        if ($lengowPaymentMethod === null) {
            $this->lengowLog->write(
                LengowLog::CODE_IMPORT,
                $this->lengowLog->encodeMessage('log.import.lengow_payment_not_found') // TODO add message
            );
            return null;
        }
        $customerData = [
            'id' => $customerId,
            'groupId' => $salesChannel->getCustomerGroupId(),
            'defaultPaymentMethodId' => $lengowPaymentMethod->getId(),
            'salesChannelId' => $salesChannel->getId(),
            'defaultBillingAddressId' => $defaultAddressId,
            'defaultShippingAddressId' => $defaultAddressId,
            'salutationId' => $defaultAddress['salutation_id'],
            'customerNumber' => $this->numberRangeValueGenerator->getValue(
                'customer',
                Context::createDefaultContext(),
                null
            ),
            'firstName' => $defaultAddress['first_name'],
            'lastName' => $defaultAddress['last_name'],
            'email' => $customerEmail,
            'addresses' => [
                [
                    'id' => $defaultAddressId,
                    'customerId' => $customerId,
                    'countryId' => $defaultAddress['country_id'],
                    'countryStateId' => $defaultAddress['country_state_id'],
                    'salutationId' => $defaultAddress['salutation_id'],
                    'vatId' => $defaultAddress['vat_id'],
                    'company' => $defaultAddress['company'],
                    'firstName' => $defaultAddress['first_name'],
                    'lastName' => $defaultAddress['last_name'],
                    'street' => $defaultAddress['street'],
                    'additionalAddressLine1' => $defaultAddress['additional_address_line_1'],
                    'additionalAddressLine2' => $defaultAddress['additional_address_line_2'],
                    'zipcode' => $defaultAddress['zipcode'],
                    'city' => $defaultAddress['city'],
                ],
            ],
        ];
        try {
            $this->customerRepository->create([$customerData], Context::createDefaultContext());
        } catch (Exception $e) {
            $errorMessage = '[Shopware error] "' . $e->getMessage() . '" ' . $e->getFile() . ' | ' . $e->getLine();
            $this->lengowLog->write(
                LengowLog::CODE_ORM,
                $this->lengowLog->encodeMessage('log.orm.record_insert_failed', [
                    'decoded_message' => str_replace(PHP_EOL, '', $errorMessage),
                ])
            );
            return null;
        }
        return $this->getCustomerById($customerId);
    }
}
