<?php declare(strict_types=1);

namespace Lengow\Connector\EntityExtension\ExtensionStructure;

use Shopware\Core\Framework\Struct\Struct;

/**
 * Class ProductExtensionStructure
 * @package Lengow\Connector\EntityExtension\ExtensionStructure
 */
class ProductExtensionStructure extends Struct
{
    /**
     * @var bool
     */
    public $active = false;

    /**
     * @var array
     */
    public $activeArray = [];

    /**
     * ProductExtensionStructure constructor.
     *
     * @param bool $activeInLengow
     * @param array $activeArray
     */
    public function __construct($activeInLengow = false, $activeArray = [])
    {
        foreach ($activeArray as $active) {
            $this->activeArray[$active->salesChannelId] = true;
        }
        $this->active= $activeInLengow;
    }
}