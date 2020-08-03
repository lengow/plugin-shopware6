<?php declare(strict_types=1);

namespace Lengow\Connector\tests;

use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * Class ClassInstantiableTest
 * @package Lengow\Connector\tests
 */
class ClassInstantiableTest extends TestCase
{
    public function testClassesAreInstantiable(): void
    {
        $namespace = str_replace('\Test', '', __NAMESPACE__);

        foreach ($this->getPluginClasses() as $class) {
            $classRelativePath = str_replace(['.php', '/'], ['', '\\'], $class->getRelativePathname());

            $this->getMockBuilder($namespace . '\\' . $classRelativePath)
                 ->disableOriginalConstructor()
                 ->getMock();
        }

        // Nothing broke so far, classes seem to be instantiable
        $this->assertTrue(true);
    }

    /**
     * @return Finder
     */
    private function getPluginClasses(): Finder
    {
        $finder = new Finder();
        $finder->in(realpath(__DIR__ . '/../'));
        $finder->exclude('Test');
        return $finder->files()->name('*.php');
    }
}
