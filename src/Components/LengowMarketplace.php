<?php declare(strict_types=1);

namespace Lengow\Connector\Components;

use FontLib\Table\Type\name;

/**
 * Class LengowMarketplace
 * @package Lengow\Connector\Components
 */
class LengowMarketplace
{
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
     * @param object $marketplaceData marketplace data
     */
    public function __construct(string $marketplaceCode, object $marketplaceData)
    {
        $this->name = $marketplaceCode;
        $this->label = $marketplaceData->name;
        foreach ($marketplaceData->orders->status as $key => $state) {
            foreach ($state as $value) {
                $this->statesLengow[(string)$value] = (string)$key;
                $this->states[(string)$key][(string)$value] = (string)$value;
            }
        }
        foreach ($marketplaceData->orders->actions as $key => $action) {
            foreach ($action->status as $state) {
                $this->actions[(string)$key]['status'][(string)$state] = (string)$state;
            }
            foreach ($action->args as $arg) {
                $this->actions[(string)$key]['args'][(string)$arg] = (string)$arg;
            }
            foreach ($action->optional_args as $optionalArg) {
                $this->actions[(string)$key]['optional_args'][(string)$optionalArg] = $optionalArg;
            }
            foreach ($action->args_description as $argKey => $argDescription) {
                $validValues = [];
                if (isset($argDescription->valid_values)) {
                    foreach ($argDescription->valid_values as $code => $validValue) {
                        $validValues[(string)$code] = (string)($validValue->label ?? $validValue);
                    }
                }
                $defaultValue = (string)($argDescription->default_value ?? '');
                $acceptFreeValue = (bool)($argDescription->accept_free_values ?? true);
                $this->argValues[(string)$argKey] = [
                    'default_value' => $defaultValue,
                    'accept_free_values' => $acceptFreeValue,
                    'valid_values' => $validValues,
                ];
            }
        }
        if (isset($marketplaceData->orders->carriers)) {
            foreach ($marketplaceData->orders->carriers as $key => $carrier) {
                $this->carriers[(string)$key] = (string)$carrier->label;
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
}