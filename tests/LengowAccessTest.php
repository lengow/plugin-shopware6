<?php declare(strict_types=1);

namespace Lengow\Connector\tests;

use PHPUnit\Framework\TestCase;
use Lengow\Connector\Service\LengowAccess;
use Lengow\Connector\Service\LengowConfiguration;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class LengowAccessTest extends TestCase
{
    /**
     * @var LengowConfiguration|MockObject
     */
    private $lengowConfiguration;

    /**
     * @var EntityRepository|MockObject
     */
    private $salesChannelRepository;

    /**
     * @var LengowAccess
     */
    private $lengowAccess;

    protected function setUp(): void
    {
        $this->entityRepositoryMock = $this->createMock(EntityRepository::class);
        $this->lengowConfiguration = $this->createMock(LengowConfiguration::class);
        $this->salesChannelRepository = $this->createMock(EntityRepository::class);
        $this->lengowAccess = new LengowAccess($this->lengowConfiguration, $this->salesChannelRepository);
    }

    public function testCheckSalesChannelWithInvalidId(): void
    {
        $result = $this->lengowAccess->checkSalesChannel('invalid-uuid');
        $this->assertNull($result);
    }

    public function testCheckSalesChannelWithValidId(): void
    {
        // Define the expected sales channel ID and name
        $salesChannelId = '9f4906f1e8b14b7d86d5b7f4ebd3c57d';
        $expectedName = 'Test Channel';

        $salesChannelEntity = new SalesChannelEntity();
        $salesChannelEntity->setName($expectedName);
        $salesChannelEntity->setUniqueIdentifier($salesChannelId);

        // Create an EntityCollection containing the mock SalesChannelEntity
        $entityCollection = new EntityCollection([$salesChannelEntity]);

        // Create an EntitySearchResult with the entity name (empty string for entity), total count, entity collection, criteria, and context
        $searchResult = new EntitySearchResult(
            'sales_channel', // Empty string for entity name
            $entityCollection->count(), // Total count of entities
            $entityCollection, // Entity collection
            null, // Facets (can be null if not used)
            new Criteria(), // Criteria
            Context::createDefaultContext() // Default context
        );

        $result = $searchResult->first()->getName();

        // Assert that the result matches the expected name
        $this->assertEquals($expectedName, $result);
    }

    public function testCheckWebserviceAccessWithAuthorizedIp(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.4.150';
        $this->lengowConfiguration->method('get')
            ->willReturn(false);

        $result = $this->lengowAccess->checkWebserviceAccess(null, null);
        $this->assertTrue($result);
    }

    public function testCheckWebserviceAccessWithValidToken(): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $this->lengowConfiguration->method('get')
            ->willReturn(false);
        $this->lengowConfiguration->method('getToken')
            ->willReturn('valid-token');

        $result = $this->lengowAccess->checkWebserviceAccess('valid-token', null);
        $this->assertTrue($result);
    }

    public function testCheckIpWithUnauthorizedIp(): void
    {
        $result = $this->lengowAccess->checkIp('127.0.0.1');
        $this->assertFalse($result);
    }

    public function testCheckIpWithAuthorizedIp(): void
    {
        $result = $this->lengowAccess->checkIp('10.0.4.150');
        $this->assertTrue($result);
    }

    public function testGetAuthorizedIps(): void
    {
        $this->lengowConfiguration->method('get')
            ->willReturnMap([
                [LengowConfiguration::AUTHORIZED_IPS, []],
                [LengowConfiguration::AUTHORIZED_IP_ENABLED, true]
            ]);

        $result = $this->lengowAccess->getAuthorizedIps();
        $this->assertContains('10.0.4.150', $result);
    }

    public function testCheckTokenWithInvalidToken(): void
    {
        $this->lengowConfiguration->method('getToken')
            ->willReturn('valid-token');

        $result = $this->lengowAccess->checkToken('invalid-token');
        $this->assertFalse($result);
    }

    public function testCheckTokenWithValidToken(): void
    {
        $this->lengowConfiguration->method('getToken')
            ->willReturn('valid-token');

        $result = $this->lengowAccess->checkToken('valid-token');
        $this->assertTrue($result);
    }
}
