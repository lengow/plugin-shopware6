<?php declare(strict_types=1);

namespace Lengow\Connector\Storefront\Struct;

use Shopware\Core\Framework\Struct\Struct;

/**
 * The sole purpose of this class is to be used to pass data to the lengow tracker in the storefront
 *
 * Class CheckoutFinishTrackerData
 * @package Lengow\Connector\Storefront\Struct
 */
class CheckoutFinishTrackerData extends Struct
{
    public const EXTENSION_NAME = 'lengow';

    /**
     * @var array tracker data
     */
    protected $data;

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }
}
