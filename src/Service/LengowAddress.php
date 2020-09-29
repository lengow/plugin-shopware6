<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateCollection;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Core\System\Salutation\SalutationEntity;
use Lengow\Connector\Exception\LengowException;
use Lengow\Connector\Util\StringCleaner;

/**
 * Class LengowAddress
 * @package Lengow\Connector\Service
 */
class LengowAddress
{
    /**
     * @var string address billing type
     */
    public const TYPE_BILLING = 'billing';

    /**
     * @var string address shipping type
     */
    public const TYPE_SHIPPING = 'shipping';

    /**
     * @var string salutation mister type
     */
    private const SALUTATION_MR = 'mr';

    /**
     * @var string salutation miss type
     */
    private const SALUTATION_MRS = 'mrs';

    /**
     * @var string salutation not specified type
     */
    private const SALUTATION_NOT_SPECIFIED = 'not_specified';

    /**
     * @var string code ISO A2 for France
     */
    private const ISO_A2_FR = 'FR';

    /**
     * @var string code ISO A2 for Spain
     */
    private const ISO_A2_ES = 'ES';

    /**
     * @var string code ISO A2 for Italy
     */
    private const ISO_A2_IT = 'IT';

    /**
     * @var LengowLog Lengow Log service
     */
    private $lengowLog;

    /**
     * @var StringCleaner Lengow string cleaner utility
     */
    private $stringCleaner;

    /**
     * @var EntityRepositoryInterface Shopware country repository
     */
    private $countryRepository;

    /**
     * @var EntityRepositoryInterface Shopware country state repository
     */
    private $countryStateRepository;

    /**
     * @var EntityRepositoryInterface Shopware salutation repository
     */
    private $salutationRepository;

    /**
     * @var array API fields for an address
     */
    private $addressApiNodes = [
        'company',
        'civility',
        'email',
        'last_name',
        'first_name',
        'full_name',
        'first_line',
        'second_line',
        'complement',
        'zipcode',
        'city',
        'state_region',
        'common_country_iso_a2',
        'phone_home',
        'phone_office',
        'phone_mobile',
    ];

    /**
     * @var array current alias of mister
     */
    private $currentMale = [
        'M',
        'M.',
        'Mr',
        'Mr.',
        'Mister',
        'Monsieur',
        'monsieur',
        'mister',
        'm.',
        'mr ',
    ];

    /**
     * @var array current alias of miss
     */
    protected $currentFemale = [
        'Mme',
        'mme',
        'Mm',
        'mm',
        'Mlle',
        'mlle',
        'Madame',
        'madame',
        'Mademoiselle',
        'madamoiselle',
        'Mrs',
        'mrs',
        'Mrs.',
        'mrs.',
        'Miss',
        'miss',
        'Ms',
        'ms',
    ];

    /**
     * @var array All region codes for correspondence
     */
    protected $regionCodes = [
        self::ISO_A2_ES => [
            '01' => 'VI',
            '02' => 'AB',
            '03' => 'A',
            '04' => 'AL',
            '05' => 'AV',
            '06' => 'BA',
            '07' => 'PM',
            '08' => 'B',
            '09' => 'BU',
            '10' => 'CC',
            '11' => 'CA',
            '12' => 'CS',
            '13' => 'CR',
            '14' => 'CO',
            '15' => 'C',
            '16' => 'CU',
            '17' => 'GI',
            '18' => 'GR',
            '19' => 'GU',
            '20' => 'SS',
            '21' => 'H',
            '22' => 'HU',
            '23' => 'J',
            '24' => 'LE',
            '25' => 'L',
            '26' => 'LO',
            '27' => 'LU',
            '28' => 'M',
            '29' => 'MA',
            '30' => 'MU',
            '31' => 'NA',
            '32' => 'OR',
            '33' => 'O',
            '34' => 'P',
            '35' => 'CG',
            '36' => 'PO',
            '37' => 'SA',
            '38' => 'TF',
            '39' => 'S',
            '40' => 'SG',
            '41' => 'SE',
            '42' => 'SO',
            '43' => 'T',
            '44' => 'TE',
            '45' => 'TO',
            '46' => 'V',
            '47' => 'VA',
            '48' => 'BI',
            '49' => 'ZA',
            '50' => 'Z',
            '51' => 'CE',
            '52' => 'ML',
        ],
        self::ISO_A2_IT => [
            '00' => 'RM',
            '01' => 'VT',
            '02' => 'RI',
            '03' => 'FR',
            '04' => 'LT',
            '05' => 'TR',
            '06' => 'PG',
            '07' => [
                '07000-07019' => 'SS',
                '07020-07029' => 'OT',
                '07030-07049' => 'SS',
                '07050-07999' => 'SS',
            ],
            '08' => [
                '08000-08010' => 'OR',
                '08011-08012' => 'NU',
                '08013-08013' => 'OR',
                '08014-08018' => 'NU',
                '08019-08019' => 'OR',
                '08020-08020' => 'OT',
                '08021-08029' => 'NU',
                '08030-08030' => 'OR',
                '08031-08032' => 'NU',
                '08033-08033' => 'CA',
                '08034-08034' => 'OR',
                '08035-08035' => 'CA',
                '08036-08039' => 'NU',
                '08040-08042' => 'OG',
                '08043-08043' => 'CA',
                '08044-08049' => 'OG',
                '08050-08999' => 'NU',
            ],
            '09' => [
                '09000-09009' => 'CA',
                '09010-09017' => 'CI',
                '09018-09019' => 'CA',
                '09020-09041' => 'VS',
                '09042-09069' => 'CA',
                '09070-09099' => 'OR',
                '09100-09169' => 'CA',
                '09170-09170' => 'OR',
                '09171-09999' => 'CA',
            ],
            '10' => 'TO',
            '11' => 'AO',
            '12' => [
                '12000-12070' => 'CN',
                '12071-12071' => 'SV',
                '12072-12999' => 'CN',
            ],
            '13' => [
                '13000-13799' => 'VC',
                '13800-13999' => 'BI',
            ],
            '14' => 'AT',
            '15' => 'AL',
            '16' => 'GE',
            '17' => 'SV',
            '18' => [
                '18000-18024' => 'IM',
                '18025-18025' => 'CN',
                '18026-18999' => 'IM',
            ],
            '19' => 'SP',
            '20' => [
                '20000-20799' => 'MI',
                '20800-20999' => 'MB',
            ],
            '21' => 'VA',
            '22' => 'CO',
            '23' => [
                '23000-23799' => 'SO',
                '23800-23999' => 'LC',
            ],
            '24' => 'BG',
            '25' => 'BS',
            '26' => [
                '26000-26799' => 'CR',
                '26800-26999' => 'LO',
            ],
            '27' => 'PV',
            '28' => [
                '28000-28799' => 'NO',
                '28800-28999' => 'VB',
            ],
            '29' => 'PC',
            '30' => 'VE',
            '31' => 'TV',
            '32' => 'BL',
            '33' => [
                '33000-33069' => 'UD',
                '33070-33099' => 'PN',
                '33100-33169' => 'UD',
                '33170-33999' => 'PN',
            ],
            '34' => [
                '34000-34069' => 'TS',
                '34070-34099' => 'GO',
                '34100-34169' => 'TS',
                '34170-34999' => 'GO',
            ],
            '35' => 'PD',
            '36' => 'VI',
            '37' => 'VR',
            '38' => 'TN',
            '39' => 'BZ',
            '40' => 'BO',
            '41' => 'MO',
            '42' => 'RE',
            '43' => 'PR',
            '44' => 'FE',
            '45' => 'RO',
            '46' => 'MN',
            '47' => [
                '47000-47799' => 'FC',
                '47800-47999' => 'RN',
            ],
            '48' => 'RA',
            '50' => 'FI',
            '51' => 'PT',
            '52' => 'AR',
            '53' => 'SI',
            '54' => 'MS',
            '55' => 'LU',
            '56' => 'PI',
            '57' => 'LI',
            '58' => 'GR',
            '59' => 'PO',
            '60' => 'AN',
            '61' => 'PU',
            '62' => 'MC',
            '63' => [
                '63000-63799' => 'AP',
                '63800-63999' => 'FM',
            ],
            '64' => 'TE',
            '65' => 'PE',
            '66' => 'CH',
            '67' => 'AQ',
            '70' => 'BA',
            '71' => 'FG',
            '72' => 'BR',
            '73' => 'LE',
            '74' => 'TA',
            '75' => 'MT',
            '76' => 'BT',
            '80' => 'NA',
            '81' => 'CE',
            '82' => 'BN',
            '83' => 'AV',
            '84' => 'SA',
            '85' => 'PZ',
            '86' => [
                '86000-86069' => 'CB',
                '86070-86099' => 'IS',
                '86100-86169' => 'CB',
                '86170-86999' => 'IS',
            ],
            '87' => 'CS',
            '88' => [
                '88000-88799' => 'CZ',
                '88800-88999' => 'KR',
            ],
            '89' => [
                '89000-89799' => 'RC',
                '89800-89999' => 'VV',
            ],
            '90' => 'PA',
            '91' => 'TP',
            '92' => 'AG',
            '93' => 'CL',
            '94' => 'EN',
            '95' => 'CT',
            '96' => 'SR',
            '97' => 'RG',
            '98' => 'ME',
        ],
    ];

    /**
     * @var array billing data
     */
    private $billingData = [];

    /**
     * @var array shipping data
     */
    private $shippingData = [];

    /**
     * @var string carrier relay id
     */
    private $relayId;

    /**
     * @var string vatNumber of current order
     */
    private $vatNumber;

    /**
     * LengowAddress constructor
     *
     * @param LengowLog $lengowLog Lengow Log service
     * @param StringCleaner $stringCleaner Lengow string cleaner utility
     * @param EntityRepositoryInterface $countryRepository Shopware country repository
     * @param EntityRepositoryInterface $countryStateRepository Shopware country state repository
     * @param EntityRepositoryInterface $salutationRepository Shopware salutation repository
     *
     */
    public function __construct(
        LengowLog $lengowLog,
        StringCleaner $stringCleaner,
        EntityRepositoryInterface $countryRepository,
        EntityRepositoryInterface $countryStateRepository,
        EntityRepositoryInterface $salutationRepository
    )
    {
        $this->lengowLog = $lengowLog;
        $this->stringCleaner = $stringCleaner;
        $this->countryRepository = $countryRepository;
        $this->countryStateRepository = $countryStateRepository;
        $this->salutationRepository = $salutationRepository;
    }

    /**
     * Init address data
     *
     * @param $params array optional options
     * array  billing_data  API billing data
     * array  shipping_data API shipping data
     * string relay_id      carrier id relay
     * string vat_number    vat number
     *
     * @throws LengowException
     */
    public function init(array $params = []): void
    {
        $this->relayId = $params['relay_id'] ?? null;
        $this->vatNumber = $params['vat_number'] ?? null;
        if (isset($params['billing_data'])) {
            $billingAddressData = $this->extractAddressDataFromAPI($params['billing_data']);
            $this->billingData = $this->setShopwareAddressFields($billingAddressData, self::TYPE_BILLING);
        }
        if (isset($params['shipping_data'])) {
            $shippingAddressData = $this->extractAddressDataFromAPI($params['shipping_data']);
            $this->shippingData = $this->setShopwareAddressFields($shippingAddressData, self::TYPE_SHIPPING);
        }
    }

    /**
     * Get all address data for specific address type
     *
     * @param string $addressType address type (billing or shipping)
     *
     * @return array
     */
    public function getAddressData(string $addressType): array
    {
        if ($addressType === self::TYPE_SHIPPING) {
            return $this->shippingData;
        }
        return $this->billingData;
    }

    /**
     * Extract address data from API
     *
     * @param object $apiData API nodes containing data
     *
     * @return array
     */
    private function extractAddressDataFromAPI(object $apiData): array
    {
        $addressData = [];
        foreach ($this->addressApiNodes as $node) {
            $addressData[$node] = $apiData->{$node};
        }
        return $addressData;
    }

    /**
     * Prepare API address data for Shopware address object
     *
     * @param array $addressData API address data
     * @param string $addressType address type (billing or shipping)
     *
     * @return array
     * @throws LengowException
     *
     */
    private function setShopwareAddressFields(array $addressData, string $addressType): array
    {
        $country = $this->getCountryByIso($addressData['common_country_iso_a2']);
        if ($country === null) {
            throw new LengowException(
                $this->lengowLog->encodeMessage('lengow_log.exception.country_not_found', [
                    'iso_code' => $addressData['common_country_iso_a2'],
                ])
            );
        }
        $salutation = $this->getSalutation($addressData);
        if ($salutation === null) {
            throw new LengowException($this->lengowLog->encodeMessage('lengow_log.exception.salutation_not_found'));
        }
        $state = $this->getState($country, $addressData['zipcode'], $addressData['state_region']);
        $names = $this->getNames($addressData);
        $addressFields = $this->getAddressFields($addressData, $addressType);
        return [
            'company' => $addressData['company'],
            'salutationId' => $salutation->getId(),
            'firstName' => ucfirst(strtolower($names['first_name'])),
            'lastName' => ucfirst(strtolower($names['last_name'])),
            'street' => strtolower($addressFields['street']),
            'additionalAddressLine1' => strtolower($addressFields['additional_address_line_1']),
            'additionalAddressLine2' => strtolower($addressFields['additional_address_line_2']),
            'zipcode' => $addressData['zipcode'],
            'city' => ucfirst(strtolower(preg_replace('/[!<>?=+@{}_$%]/sim', '', $addressData['city']))),
            'countryId' => $country->getId(),
            'countryStateId' => $state ? $state->getId() : null,
            'phoneNumber' => $this->getPhoneNumber($addressData),
            'vatId' => $this->vatNumber,
        ];
    }

    /**
     * Get country by iso code
     *
     * @param string $countryIsoCode Country iso code
     *
     * @return CountryEntity|null
     */
    private function getCountryByIso(string $countryIsoCode): ?CountryEntity
    {
        $countryIsoCode = strtoupper(substr(str_replace(' ', '', $countryIsoCode), 0, 2));
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('iso', $countryIsoCode));
        /** @var CountryCollection $countryCollection */
        $countryCollection = $this->countryRepository->search($criteria, Context::createDefaultContext())
            ->getEntities();
        if ($countryCollection->count() !== 0) {
            return $countryCollection->first();
        }
        return null;
    }

    /**
     * Get the real salutation
     *
     * @param array $addressData API address data
     *
     * @return SalutationEntity|null
     */
    private function getSalutation(array $addressData): ?SalutationEntity
    {
        $salutation = $addressData['civility'];
        if (empty($salutation) && !empty($addressData['full_name'])) {
            $split = explode(' ', $addressData['full_name']);
            if (!empty($split)) {
                $salutation = $split[0];
            }
        }
        if (in_array($salutation, $this->currentMale, true)) {
            $salutationKey = self::SALUTATION_MR;
        } elseif (in_array($salutation, $this->currentFemale, true)) {
            $salutationKey = self::SALUTATION_MRS;
        } else {
            $salutationKey = self::SALUTATION_NOT_SPECIFIED;
        }
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salutationKey', $salutationKey));
        /** @var SalutationCollection $salutationCollection */
        $salutationCollection = $this->salutationRepository->search($criteria, Context::createDefaultContext())
            ->getEntities();
        if ($salutationCollection->count() !== 0) {
            return $salutationCollection->first();
        }
        return null;
    }

    /**
     * Get country state if exist
     *
     * @param CountryEntity $country Shopware country instance
     * @param string $postcode address postcode
     * @param string|null $stateRegion address state region
     *
     * @return CountryStateEntity|null
     */
    private function getState(CountryEntity $country, string $postcode, string $stateRegion = null): ?CountryStateEntity
    {
        $state = null;
        if (in_array($country->getIso(), [self::ISO_A2_FR, self::ISO_A2_ES, self::ISO_A2_IT], true)) {
            $state = $this->searchStateByPostcode($country, $postcode);
        } elseif (!empty($stateRegion)) {
            $state = $this->searchStateByStateRegion($country, $stateRegion);
        }
        return $state;
    }

    /**
     * Search state by postcode for specific countries
     *
     * @param CountryEntity $country Shopware country instance
     * @param string $postcode address postcode
     *
     * @return CountryStateEntity|null
     */
    private function searchStateByPostcode(CountryEntity $country, string $postcode): ?CountryStateEntity
    {
        $countryIsoA2 = $country->getIso();
        $postcodeSubstr = substr(str_pad($postcode, 5, '0', STR_PAD_LEFT), 0, 2);
        switch ($countryIsoA2) {
            case self::ISO_A2_FR:
                $shortCode = ltrim($postcodeSubstr, '0');
                break;
            case self::ISO_A2_ES:
                $shortCode = $this->regionCodes[$countryIsoA2][$postcodeSubstr] ?? false;
                break;
            case self::ISO_A2_IT:
                $shortCode = $this->regionCodes[$countryIsoA2][$postcodeSubstr] ?? false;
                if ($shortCode && is_array($shortCode) && !empty($shortCode)) {
                    $shortCode = $this->getShortCodeFromIntervalPostcodes((int)$postcode, $shortCode);
                }
                break;
            default:
                $shortCode = null;
        }
        if ($shortCode) {
            $shortCode = $country->getIso() . '-' . $shortCode;
            $criteria = new Criteria();
            $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('shortCode', $shortCode),
                new EqualsFilter('countryId', $country->getId()),
            ]));
            /** @var CountryStateCollection $countryStateCollection */
            $countryStateCollection = $this->countryStateRepository->search($criteria, Context::createDefaultContext())
                ->getEntities();
            if ($countryStateCollection->count() !== 0) {
                return $countryStateCollection->first();
            }
        }
        return null;
    }

    /**
     * Get short code from interval postcodes
     *
     * @param int $postcode address postcode$stateRegionCleaned
     * @param array $intervalPostcodes postcode intervals
     *
     * @return string|null
     */
    private function getShortCodeFromIntervalPostcodes(int $postcode, array $intervalPostcodes): ?string
    {
        foreach ($intervalPostcodes as $intervalPostcode => $shortCode) {
            $intervalPostcodes = explode('-', $intervalPostcode);
            if (!empty($intervalPostcodes) && count($intervalPostcodes) === 2) {
                $minPostcode = is_numeric($intervalPostcodes[0]) ? (int)$intervalPostcodes[0] : false;
                $maxPostcode = is_numeric($intervalPostcodes[1]) ? (int)$intervalPostcodes[1] : false;
                if (($minPostcode && $maxPostcode) && ($postcode >= $minPostcode && $postcode <= $maxPostcode)) {
                    return $shortCode;
                }
            }
        }
        return null;
    }

    /**
     * Search Shopware region id by state return by api
     *
     * @param CountryEntity $country Shopware country instance
     * @param string $stateRegion address state region
     *
     * @return CountryStateEntity|null
     */
    private function searchStateByStateRegion(CountryEntity $country, string $stateRegion): ?CountryStateEntity
    {
        $state = null;
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('countryId', $country->getId()));
        /** @var CountryStateCollection $countryStateCollection */
        $countryStateCollection = $this->countryStateRepository->search($criteria, Context::createDefaultContext())
            ->getEntities();
        $stateRegionCleaned = $this->cleanString($stateRegion);
        $stateRegionWithPrefixCleaned = $this->cleanString($country->getIso() . '-' . $stateRegion);
        if (!empty($stateRegion) && $countryStateCollection->count()) {
            // strict search on the region code
            foreach ($countryStateCollection as $countryState) {
                $shortCodeCleaned = $this->cleanString($countryState->getShortCode());
                if ($stateRegionWithPrefixCleaned === $shortCodeCleaned) {
                    $state = $countryState;
                    break;
                }
            }
            // approximate search on the state name
            if (!$state) {
                $results = [];
                foreach ($countryStateCollection as $countryState) {
                    $nameCleaned = $this->cleanString($countryState->getName());
                    similar_text($stateRegionCleaned, $nameCleaned, $percent);
                    if ($percent > 70) {
                        $results[(int)$percent] = $countryState;
                    }
                }
                if (!empty($results)) {
                    krsort($results);
                    $state = current($results);
                }
            }
        }
        return $state;
    }

    /**
     * Cleaning a string before search
     *
     * @param string $string string to clean
     *
     * @return string
     */
    protected function cleanString(string $string): string
    {
        $string = strtolower(str_replace([' ', '-', '_', '.'], '', trim($string)));
        return $this->stringCleaner->replaceAccentedChars(html_entity_decode($string));
    }

    /**
     * Check if first name or last name are empty
     *
     * @param array $addressData API address data
     *
     * @return array
     */
    private function getNames(array $addressData): array
    {
        $names = [
            'first_name' => !empty($addressData['first_name']) ? trim($addressData['first_name']) : '',
            'last_name' => !empty($addressData['last_name']) ? trim($addressData['last_name']) : '',
            'full_name' => !empty($addressData['full_name']) ? $this->cleanFullName($addressData['full_name']) : '',
        ];
        if (empty($names['first_name']) && empty($names['last_name'])) {
            $names = $this->splitNames($names['full_name']);
        } elseif (empty($names['first_name'])) {
            $names = $this->splitNames($names['last_name']);
        } elseif (empty($names['last_name'])) {
            $names = $this->splitNames($names['first_name']);
        }
        unset($names['full_name']);
        $names['first_name'] = !empty($names['first_name']) ? ucfirst(strtolower($names['first_name'])) : '__';
        $names['last_name'] = !empty($names['last_name']) ? ucfirst(strtolower($names['last_name'])) : '__';
        return $names;
    }

    /**
     * Clean full name field without salutation
     *
     * @param string $fullName full name of the customer
     *
     * @return string
     */
    private function cleanFullName(string $fullName): string
    {
        $split = explode(' ', $fullName);
        if (!empty($split)) {
            $splitCount = count($split);
            $fullName = in_array($split[0], $this->currentMale, true) || in_array($split[0], $this->currentFemale, true)
                ? ''
                : $split[0];
            for ($i = 1; $i < $splitCount; $i++) {
                $fullName .= !empty($fullName) ? ' ' . $split[$i] : $split[$i];
            }
        }
        return $fullName;
    }

    /**
     * Split full name
     *
     * @param string $fullName full name of the customer
     *
     * @return array
     */
    private function splitNames(string $fullName): array
    {
        $split = explode(' ', $fullName);
        if (empty($split)) {
            return ['first_name' => '__', 'last_name' => '__'];
        }
        $splitCount = count($split);
        $names['first_name'] = $split[0];
        $names['last_name'] = '';
        for ($i = 1; $i < $splitCount; $i++) {
            $names['last_name'] .= !empty($names['last_name']) ? ' ' . $split[$i] : $split[$i];
        }
        return $names;
    }

    /**
     * Get clean address fields
     *
     * @param array $addressData API address data
     * @param string $addressType address type (billing or shipping)
     *
     * @return array
     */
    private function getAddressFields(array $addressData, string $addressType): array
    {
        $street = !empty($addressData['first_line']) ? trim($addressData['first_line']) : '';
        $additionalAddressLine1 = !empty($addressData['second_line']) ? trim($addressData['second_line']) : '';
        $additionalAddressLine2 = !empty($addressData['complement']) ? trim($addressData['complement']) : '';
        if (empty($street)) {
            if (!empty($additionalAddressLine1)) {
                $street = $additionalAddressLine1;
                $additionalAddressLine1 = '';
            } elseif (!empty($additionalAddressLine2)) {
                $street = $additionalAddressLine2;
                $additionalAddressLine2 = '';
            }
        }
        // get relay id for shipping addresses
        $relayId = $this->relayId !== null ? 'Relay id: ' . $this->relayId : '';
        if ($addressType === self::TYPE_SHIPPING) {
            $additionalAddressLine2 .= !empty($additionalAddressLine2) ? ' - ' . $relayId : $relayId;
        }
        return [
            'street' => $street,
            'additional_address_line_1' => $additionalAddressLine1,
            'additional_address_line_2' => $additionalAddressLine2,
        ];
    }

    /**
     * Get clean phone number
     *
     * @param array $addressData API address data
     *
     * @return string
     */
    private function getPhoneNumber(array $addressData = []): string
    {
        $phoneNumber = '';
        if (!empty($addressData['phone_home'])) {
            $phoneNumber = $addressData['phone_home'];
        } elseif (!empty($addressData['phone_mobile'])) {
            $phoneNumber = $addressData['phone_mobile'];
        } elseif (!empty($addressData['phone_office'])) {
            $phoneNumber = $addressData['phone_office'];
        }
        return $this->cleanPhoneNumber($phoneNumber);
    }

    /**
     * @param string $phoneNumber
     *
     * @return string
     */
    private function cleanPhoneNumber(string $phoneNumber): string
    {
        if (!$phoneNumber) {
            return '';
        }
        return str_replace(['.', ' ', '-', '/'], '', preg_replace('/\D*/', '', $phoneNumber));
    }
}
