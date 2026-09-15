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
            // Through the getter rather than the property: Entity::__get() would
            // return it either way, but that leaves the code depending on magic
            // access to a protected member.
            $this->activeArray[$active->getSalesChannelId()] = true;
        }
        $this->active= $activeInLengow;
    }
}