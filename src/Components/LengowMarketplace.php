<?php declare(strict_types=1);

namespace Lengow\Connector\Components;

use Exception;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Lengow\Connector\Entity\Lengow\Order\OrderEntity as LengowOrderEntity;
use Lengow\Connector\Exception\LengowException;
use Lengow\Connector\Service\LengowAction;
use Lengow\Connector\Service\LengowConfiguration;
use Lengow\Connector\Service\LengowImport;
use Lengow\Connector\Service\LengowLog;

/**
 * Class LengowMarketplace
 * @package Lengow\Connector\Components
 */
class LengowMarketplace
{
    /**
     * @var LengowAction Lengow action service
     */
    private $lengowAction;

    /**
     * @var LengowLog Lengow log service
     */
    private $lengowLog;

    /**
     * @var LengowConfiguration Lengow configuration service
     */
    private $lengowConfiguration;

    /**
     * @var string the name of the marketplace
     */
    private $name;

    /**
     * @var string the label of the marketplace
     */
    private $label;

    /**
     * @var array Lengow states => marketplace states
     */
    private $statesLengow = [];

    /**
     * @var array marketplace states => Lengow states
     */
    private $states = [];

    /**
     * @var array all possible actions of the marketplace
     */
    private $actions = [];

    /**
     * @var array all carriers of the marketplace
     */
    private $carriers = [];

    /**
     * @var array all possible values for actions of the marketplace
     */
    private $argValues = [];

    /**
     * Construct a new Marketplace instance with xml configuration
     *
     * @param string $marketplaceCode code of the marketplace
     * @param mixed $marketplaceData marketplace data
     * @param LengowAction $lengowAction Lengow action service
     * @param LengowLog $lengowLog Lengow log service
     * @param LengowConfiguration $lengowConfiguration Lengow configuration service
     */
    public function __construct(
        string $marketplaceCode,
        $marketplaceData,
        LengowAction $lengowAction,
        Lengowlog $lengowLog,
        lengowConfiguration $lengowConfiguration
    )
    {
        $this->name = $marketplaceCode;
        $this->label = $marketplaceData->name;
        $this->lengowAction = $lengowAction;
        $this->lengowLog = $lengowLog;
        $this->lengowConfiguration = $lengowConfiguration;
        foreach ($marketplaceData->orders->status as $key => $state) {
            foreach ($state as $value) {
                $this->statesLengow[(string) $value] = (string) $key;
                $this->states[(string) $key][(string) $value] = (string) $value;
            }
        }
        foreach ($marketplaceData->orders->actions as $key => $action) {
            foreach ($action->status as $state) {
                $this->actions[(string) $key]['status'][(string) $state] = (string) $state;
            }
            foreach ($action->args as $arg) {
                $this->actions[(string) $key]['args'][(string) $arg] = (string) $arg;
            }
            foreach ($action->optional_args as $optionalArg) {
                $this->actions[(string) $key]['optional_args'][(string) $optionalArg] = $optionalArg;
            }
            foreach ($action->args_description as $argKey => $argDescription) {
                $validValues = [];
                if (isset($argDescription->valid_values)) {
                    foreach ($argDescription->valid_values as $code => $validValue) {
                        $validValues[(string) $code] = (string) ($validValue->label ?? $validValue);
                    }
                }
                $defaultValue = $argDescription->default_value ?? '';
                $acceptFreeValue = $argDescription->accept_free_values ?? true;
                $this->argValues[(string) $argKey] = [
                    'default_value' => $defaultValue,
                    'accept_free_values' => $acceptFreeValue,
                    'valid_values' => $validValues,
                ];
            }
        }
        if (isset($marketplaceData->orders->carriers)) {
            foreach ($marketplaceData->orders->carriers as $key => $carrier) {
                $this->carriers[(string) $key] = (string) $carrier->label;
            }
        }
    }

    /**
     * Get marketplace name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get marketplace label
     *
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * Get the real Lengow's state
     *
     * @param string $marketplaceOrderState The marketplace state
     *
     * @return string
     */
    public function getStateLengow(string $marketplaceOrderState): string
    {
        return $this->statesLengow[$marketplaceOrderState] ?? '';
    }

    /**
     * Get the action with parameters
     *
     * @param string $action action's name
     *
     * @return array
     */
    public function getAction(string $action): array
    {
        return $this->actions[$action] ?? [];
    }

    /**
     * Get the default value for argument
     *
     * @param string $arg argument's name
     *
     * @return string
     */
    public function getDefaultValue(string $arg): string
    {
        if (array_key_exists($arg, $this->argValues)) {
            $defaultValue = $this->argValues[$arg]['default_value'];
            if (!empty($defaultValue)) {
                return (string) $defaultValue;
            }
        }

        return '';
    }

    /**
     * Is marketplace contain order Line
     *
     * @param string $action order action (ship or cancel)
     *
     * @return bool
     */
    public function containOrderLine(string $action): bool
    {
        if (isset($this->actions[$action])) {
            $actions = $this->actions[$action];
            if (isset($actions['args'])
                && is_array($actions['args'])
                && in_array(LengowAction::ARG_LINE, $actions['args'], true)
            ) {
                return true;
            }
            if (isset($actions['optional_args'])
                && is_array($actions['optional_args'])
                && in_array(LengowAction::ARG_LINE, $actions['optional_args'], true)
            ) {

                return true;
            }
        }

        return false;
    }

    /**
     * Call Action with marketplace
     *
     * @param string $action order action (ship or cancel)
     * @param LengowOrderEntity $lengowOrder Lengow order instance
     * @param OrderEntity $order Shopware order instance
     * @param OrderDeliveryEntity|null $orderDelivery Shopware order delivery instance
     * @param string|null $orderLineId Lengow order line id
     *
     * @throws LengowException|Exception
     *
     * @return bool
     */
    public function callAction(
        string $action,
        LengowOrderEntity $lengowOrder,
        OrderEntity $order,
        OrderDeliveryEntity $orderDelivery = null,
        string $orderLineId = null
    ): bool
    {
        // check the action and order data
        $this->checkAction($action);
        $this->checkOrderData($lengowOrder);
        // get all required and optional arguments for a specific marketplace
        $marketplaceArguments = $this->getMarketplaceArguments($action);
        // get all available values from an order
        $params = $this->getAllParams($action, $marketplaceArguments, $lengowOrder, $order, $orderDelivery);
        // check required arguments and clean value for empty optionals arguments
        $params = $this->checkAndCleanParams($action, $params);
        // complete the values with the specific values of the account
        if ($orderLineId !== null) {
            $params[LengowAction::ARG_LINE] = $orderLineId;
        }
        $params[LengowImport::ARG_MARKETPLACE_ORDER_ID] = $lengowOrder->getMarketplaceSku();
        $params[LengowImport::ARG_MARKETPLACE] = $lengowOrder->getMarketplaceName();
        $params[LengowAction::ARG_ACTION_TYPE] = $action;
        // checks whether the action is already created to not return an action
        if ($this->lengowAction->canSendAction($params, $order)) {
            // send a new action on the order via the Lengow API
            $this->lengowAction->sendAction($params, $order, $lengowOrder);
        }

        return true;
    }

    /**
     * Check if the action is valid and present on the marketplace
     *
     * @param string $action Lengow order actions type (ship or cancel)
     *
     * @throws LengowException
     */
    private function checkAction(string $action):void
    {
        if (!in_array($action, LengowAction::$validActions, true)) {
            throw new LengowException(
                $this->lengowLog->encodeMessage('lengow_log.exception.action_not_valid', [
                    'action' => $action,
                ])
            );
        }
        if (!isset($this->actions[$action])) {
            throw new LengowException(
                $this->lengowLog->encodeMessage('lengow_log.exception.marketplace_action_not_present', [
                    'action' => $action,
                ])
            );
        }
    }

    /**
     * Check if the essential data of the order are present
     *
     * @param LengowOrderEntity $lengowOrder Lengow order instance
     *
     * @throws LengowException
     */
    private function checkOrderData(LengowOrderEntity $lengowOrder): void
    {
        if ($lengowOrder->getMarketplaceSku() === '') {
            throw new LengowException(
                $this->lengowLog->encodeMessage('lengow_log.exception.marketplace_sku_require')
            );
        }
        if ($lengowOrder->getMarketplaceName() === '') {
            throw new LengowException(
                $this->lengowLog->encodeMessage('lengow_log.exception.marketplace_name_require')
            );
        }
    }

    /**
     * Get all marketplace arguments for a specific action
     *
     * @param string $action Lengow order actions type (ship or cancel)
     *
     * @return array
     */
    private function getMarketplaceArguments(string $action): array
    {
        $marketplaceArguments = [];
        $actions = $this->getAction($action);
        if (isset($actions['args'])) {
            $marketplaceArguments = array_merge($actions['args'], $marketplaceArguments);
        }
        if (isset($actions['optional_args'])) {
            $marketplaceArguments = array_merge($actions['optional_args'], $marketplaceArguments);
        }

        return $marketplaceArguments;
    }

    /**
     * Get all available values from an order
     *
     * @param string $action Lengow order actions type (ship or cancel)
     * @param array $marketplaceArguments All marketplace arguments for a specific action
     * @param LengowOrderEntity $lengowOrder Lengow order instance
     * @param OrderEntity $order Shopware order instance
     * @param OrderDeliveryEntity|null $orderDelivery Shopware order delivery instance
     *
     * @return array
     */
    private function getAllParams(
        string $action,
        array $marketplaceArguments,
        LengowOrderEntity $lengowOrder,
        OrderEntity $order,
        OrderDeliveryEntity $orderDelivery = null
    ): array
    {
        $params = [];
        $actions = $this->getAction($action);
        // get all order data
        foreach ($marketplaceArguments as $arg) {
            switch ($arg) {
                case LengowAction::ARG_TRACKING_NUMBER:
                    $trackingCodes = $orderDelivery ? $orderDelivery->getTrackingCodes() : [];
                    $params[$arg] = !empty($trackingCodes) ? end($trackingCodes) : '';
                    break;
                case LengowAction::ARG_CARRIER:
                case LengowAction::ARG_CARRIER_NAME:
                case LengowAction::ARG_SHIPPING_METHOD:
                case LengowAction::ARG_CUSTOM_CARRIER:
                    $carrierName = '';
                    $shippingMethod = $orderDelivery ? $orderDelivery->getShippingMethod() : null;
                    if ($lengowOrder->getCarrier() &&  $lengowOrder->getCarrier() !== '') {
                        $carrierName = $lengowOrder->getCarrier();
                    } elseif ($shippingMethod) {
                        $carrierName = $this->matchShippingMethod($shippingMethod->getName());
                    }
                    $params[$arg] = $carrierName;
                    break;
                case LengowAction::ARG_TRACKING_URL:
                    $shippingMethod = $orderDelivery ? $orderDelivery->getShippingMethod() : null;
                    $params[$arg] = $shippingMethod ? $shippingMethod->getTrackingUrl() : '';
                    break;
                case LengowAction::ARG_SHIPPING_PRICE:
                    $params[$arg] = $order->getShippingTotal();
                    break;
                case LengowAction::ARG_SHIPPING_DATE:
                case LengowAction::ARG_DELIVERY_DATE:
                    $params[$arg] = $this->lengowConfiguration->date(time(), 'c');
                    break;
                default:
                    if (isset($actions['optional_args']) && in_array($arg, $actions['optional_args'], true)) {
                        break;
                    }
                    $defaultValue = $this->getDefaultValue($arg);
                    $paramValue = $defaultValue !== '' ? $defaultValue : $arg . ' not available';
                    $params[$arg] = $paramValue;
                    break;
            }
        }

        return $params;
    }

    /**
     * Check required parameters and delete empty parameters
     *
     * @param string $action Lengow order actions type (ship or cancel)
     * @param array $params all available values
     *
     * @throws LengowException
     *
     * @return array
     */
    private function checkAndCleanParams(string $action, array $params): array
    {
        $actions = $this->getAction($action);
        if (isset($actions['args'])) {
            foreach ($actions['args'] as $arg) {
                if (!isset($params[$arg]) || $params[$arg] === '') {
                    throw new LengowException(
                        $this->lengowLog->encodeMessage('lengow_log.exception.arg_is_required', [
                            'arg_name' => $arg,
                        ])
                    );
                }
            }
        }
        if (isset($actions['optional_args'])) {
            foreach ($actions['optional_args'] as $arg) {
                if (isset($params[$arg]) && $params[$arg] === '') {
                    unset($params[$arg]);
                }
            }
        }

        return $params;
    }

    /**
     * Match Shopware shipping method name with accepted values
     *
     * @param string $name Shopware shipping method name
     *
     * @return string
     */
    private function matchShippingMethod(string $name): string
    {
        if (!empty($this->carriers)) {
            $nameCleaned = $this->cleanString($name);
            // strict search for a chain
            $result = $this->searchCarrierCode($nameCleaned);
            // approximate search for a chain
            if ($result === '') {
                $result = $this->searchCarrierCode($nameCleaned, false);
            }
            if ($result !== '') {
                return $result;
            }
        }

        return $name;
    }

    /**
     * Cleaning a string before search
     *
     * @param string $string string to clean
     *
     * @return string
     */
    private function cleanString(string $string): string
    {
        $cleanFilters = [' ', '-', '_', '.'];

        return strtolower(str_replace($cleanFilters, '', trim($string)));
    }

    /**
     * Search carrier code in a chain
     *
     * @param string $nameCleaned carrier code cleaned
     * @param bool $strict strict search
     *
     * @return string
     */
    private function searchCarrierCode(string $nameCleaned, bool $strict = true): string
    {
        $result = '';
        foreach ($this->carriers as $key => $label) {
            $keyCleaned = $this->cleanString((string)$key);
            $labelCleaned = $this->cleanString($label);
            // search on the carrier key
            $found = $this->searchValue($keyCleaned, $nameCleaned, $strict);
            // search on the carrier label if it is different from the key
            if (!$found && $labelCleaned !== $keyCleaned) {
                $found = $this->searchValue($labelCleaned, $nameCleaned, $strict);
            }
            if ($found) {
                $result = $key;
            }
        }

        return (string)$result;
    }

    /**
     * Strict or approximate search for a chain
     *
     * @param string $pattern search pattern
     * @param string $subject string to search
     * @param bool $strict strict search
     *
     * @return bool
     */
    private function searchValue(string $pattern, string $subject, bool $strict = true): bool
    {
        if ($strict) {

            return $pattern === $subject;
        }

        return (bool) preg_match('`.*?' . $pattern . '.*?`i', $subject);
    }
}
