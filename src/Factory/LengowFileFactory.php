<?php declare(strict_types=1);

namespace Lengow\Connector\Factory;

use Lengow\Connector\Components\LengowFile;
use Lengow\Connector\Util\EnvironmentInfoProvider;

/**
 * Class LengowFileFactory
 * @package Lengow\Connector\Factory
 */
class LengowFileFactory
{
    /**
     * @var EnvironmentInfoProvider Environment info provider utility
     */
    private $environmentInfoProvider;

    /**
     * LengowFileFactory Construct
     *
     * @param EnvironmentInfoProvider $environmentInfoProvider Environment info provider utility
     */
    public function __construct(EnvironmentInfoProvider $environmentInfoProvider)
    {
        $this->environmentInfoProvider = $environmentInfoProvider;
    }

    /**
     * Create a new LengowFile instance
     *
     * @param string $folderName Lengow folder name
     * @param string|null $fileName Lengow file name
     * @param string $mode Lengow file name
     *
     * @return LengowFile
     */
    public function create(string $folderName, string $fileName, string $mode = 'a+'): LengowFile
    {
        return new LengowFile($folderName, $fileName, $mode, $this->environmentInfoProvider);
    }
}
