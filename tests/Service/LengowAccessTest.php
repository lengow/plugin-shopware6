<?php declare(strict_types=1);

namespace Lengow\Connector\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

class LengowAccessTest extends TestCase
{
    private $lengowConfiguration;
    private $salesChannelRepository;
    private $lengowAccess;

    protected function setUp(): void
    {
        $this->lengowConfiguration = $this->createMock(LengowConfiguration::class);
        $this->salesChannelRepository = new StaticEntityRepository([]);
        $this->lengowAccess = new LengowAccess($this->lengowConfiguration, $this->salesChannelRepository);
    }

    public function testCheckSalesChannelWithInvalidUuid(): void
    {
        $result = $this->lengowAccess->checkSalesChannel('invalid-uuid');
        $this->assertNull($result);
    }

    public function testCheckSalesChannelWithValidUuidButNoMatch(): void
    {
        $salesChannelId = Uuid::randomHex();
        $this->salesChannelRepository = new StaticEntityRepository([
            new EntitySearchResult(
                'sales_channel',
                0,
                new EntityCollection([]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        ]);

        $this->lengowAccess = new LengowAccess($this->lengowConfiguration, $this->salesChannelRepository);

        $result = $this->lengowAccess->checkSalesChannel($salesChannelId);
        $this->assertNull($result);
    }

    public function testCheckSalesChannelWithValidUuidAndMatch(): void
    {
        $salesChannelId = Uuid::randomHex();
        $salesChannel = $this->createMock(SalesChannelEntity::class);
        $salesChannel->method('getName')->willReturn('Test Channel');
        $salesChannel->method('getId')->willReturn($salesChannelId);

        $this->salesChannelRepository = new StaticEntityRepository([
            new EntitySearchResult(
                'sales_channel',
                1,
                new EntityCollection([$salesChannel]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        ]);

        $this->lengowAccess = new LengowAccess($this->lengowConfiguration, $this->salesChannelRepository);

        $result = $this->lengowAccess->checkSalesChannel($salesChannelId);
        $this->assertEquals('Test Channel', $result);
    }
    public function testCheckWebserviceAccessWithInvalidIpAndToken(): void
    {
        $_SERVER['REMOTE_ADDR'] = 'invalid-ip';

        $lengowAccess = $this->getMockBuilder(LengowAccess::class)
            ->setConstructorArgs([$this->lengowConfiguration, $this->salesChannelRepository])
            ->onlyMethods(['checkIp'])
            ->getMock();

        $lengowAccess->method('checkIp')->willReturn(false);

        $result = $lengowAccess->checkWebserviceAccess('invalid-token');
        $this->assertFalse($result);
    }

    public function testCheckWebserviceAccessWithValidIp(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.4.150';
        $result = $this->lengowAccess->checkWebserviceAccess();
        $this->assertTrue($result);
    }

    public function testCheckIpWithInvalidIp(): void
    {
        $result = $this->lengowAccess->checkIp('invalid-ip');
        $this->assertFalse($result);
    }

    public function testCheckIpWithValidIp(): void
    {
        $result = $this->lengowAccess->checkIp('10.0.4.150');
        $this->assertTrue($result);
    }

    public function testGetAuthorizedIps(): void
    {
        $customIps = ['185.61.176.142', '46.19.183.204'];

        $mockConfig = $this->createMock(LengowConfiguration::class);

        $mockConfig->method('get')
            ->willReturnOnConsecutiveCalls(
                $customIps,
                true
            );

        $lengowAccess = new LengowAccess($mockConfig, $this->salesChannelRepository);

        $result = $lengowAccess->getAuthorizedIps();

        foreach ($customIps as $ip) {
            $this->assertContains($ip, $result);
        }
    }

    public function testCheckTokenWithValidToken(): void
    {
        $salesChannelId = Uuid::randomHex();
        $tokenFromConfig = 'valid-token';
        $tokenToCheck = 'valid-token';

        // Créer un mock pour LengowConfiguration
        $mockConfig = $this->createMock(LengowConfiguration::class);
        $mockConfig->method('getToken')
            ->willReturn($tokenFromConfig);

        // Créer une instance de LengowAccess avec le mock de LengowConfiguration
        $lengowAccess = new LengowAccess($mockConfig, $this->salesChannelRepository);

        // Appeler la méthode à tester avec un token valide
        $result = $lengowAccess->checkToken($tokenToCheck, $salesChannelId);

        // Vérifier que la méthode retourne true lorsque les tokens correspondent
        $this->assertTrue($result);
    }

    public function testCheckTokenWithInvalidToken(): void
    {
        $salesChannelId = Uuid::randomHex();
        $tokenFromConfig = 'valid-token';
        $tokenToCheck = 'invalid-token';

        // Créer un mock pour LengowConfiguration
        $mockConfig = $this->createMock(LengowConfiguration::class);
        $mockConfig->method('getToken')
            ->willReturn($tokenFromConfig);

        // Créer une instance de LengowAccess avec le mock de LengowConfiguration
        $lengowAccess = new LengowAccess($mockConfig, $this->salesChannelRepository);

        // Appeler la méthode à tester avec un token invalide
        $result = $lengowAccess->checkToken($tokenToCheck, $salesChannelId);

        // Vérifier que la méthode retourne false lorsque les tokens ne correspondent pas
        $this->assertFalse($result);
    }

}
