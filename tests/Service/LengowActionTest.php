<?php

use Lengow\Connector\Entity\Lengow\Action\ActionCollection;
use Lengow\Connector\Service\LengowConfiguration;
use Lengow\Connector\Service\LengowConnector;
use Lengow\Connector\Service\LengowImport;
use Lengow\Connector\Service\LengowLog;
use PHPUnit\Framework\TestCase;
use Lengow\Connector\Service\LengowAction;
use Lengow\Connector\Exception\LengowException;
use Lengow\Connector\Entity\Lengow\Action\ActionDefinition as LengowActionDefinition;
use Lengow\Connector\Entity\Lengow\Action\ActionEntity as LengowActionEntity;
use Lengow\Connector\Entity\Lengow\Action\ActionCollection as LengowActionCollection;
use Lengow\Connector\Entity\Lengow\Order\OrderEntity as LengowOrderEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;

class LengowActionTest extends TestCase
{
    private $lengowActionRepository;
    private $lengowLog;
    private $lengowConnector;
    private $lengowConfiguration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lengowActionRepository = $this->createMock(EntityRepository::class);
        $this->lengowLog = $this->createMock(LengowLog::class);
        $this->lengowConnector = $this->createMock(LengowConnector::class);
        $this->lengowConfiguration = $this->createMock(LengowConfiguration::class);
    }

    public function testCreateAction(): void
    {
        $this->lengowActionRepository->expects($this->once())
            ->method('create')
            ->willReturnCallback(function ($data, $context) {
                // Simulate successful creation
                return $this->createMock(EntityWrittenContainerEvent::class);
            });

        $lengowAction = new LengowAction(
            $this->lengowActionRepository,
            $this->lengowLog,
            $this->lengowConnector,
            $this->lengowConfiguration
        );

        $validData = [
            LengowActionDefinition::FIELD_ORDER_ID => Uuid::randomHex(),
            LengowActionDefinition::FIELD_ACTION_ID => Uuid::randomHex(),
            LengowActionDefinition::FIELD_ACTION_TYPE => LengowAction::TYPE_SHIP,
            LengowActionDefinition::FIELD_PARAMETERS => [],
        ];

        $result = $lengowAction->create($validData);
        $this->assertTrue($result);
    }

    public function testCreateActionMissingRequiredFields(): void
    {
        $this->lengowActionRepository->expects($this->never())
            ->method('create');

        $lengowAction = new LengowAction(
            $this->lengowActionRepository,
            $this->lengowLog,
            $this->lengowConnector,
            $this->lengowConfiguration
        );

        $invalidData = [
            LengowActionDefinition::FIELD_ORDER_ID => Uuid::randomHex(),
            LengowActionDefinition::FIELD_ACTION_ID => Uuid::randomHex(),
            LengowActionDefinition::FIELD_PARAMETERS => [],
        ];

        $result = $lengowAction->create($invalidData);
        $this->assertFalse($result);
    }

    /**
     * @expectedException Exception
     */
    public function testCreateActionRepositoryException(): void
    {
        $this->lengowActionRepository->expects($this->once())
            ->method('create')
            ->willThrowException(new \Exception('Mocked repository exception'));

        $this->lengowLog->expects($this->once())
            ->method('write');

        $lengowAction = new LengowAction(
            $this->lengowActionRepository,
            $this->lengowLog,
            $this->lengowConnector,
            $this->lengowConfiguration
        );

        $validData = [
            LengowActionDefinition::FIELD_ORDER_ID => Uuid::randomHex(),
            LengowActionDefinition::FIELD_ACTION_ID => Uuid::randomHex(),
            LengowActionDefinition::FIELD_ACTION_TYPE => LengowAction::TYPE_SHIP,
            LengowActionDefinition::FIELD_PARAMETERS => [],
        ];

        $result = $lengowAction->create($validData);
        $this->assertFalse($result);
    }

    public function testUpdateAction(): void
    {
        $this->lengowActionRepository->expects($this->once())
            ->method('update')
            ->willReturnCallback(function ($data, $context) {
                return $this->createMock(EntityWrittenContainerEvent::class);
            });

        $lengowAction = new LengowAction(
            $this->lengowActionRepository,
            $this->lengowLog,
            $this->lengowConnector,
            $this->lengowConfiguration
        );

        $actionId = Uuid::randomHex();
        $validData = [
            LengowActionDefinition::FIELD_ACTION_TYPE => LengowAction::TYPE_SHIP,
        ];

        $result = $lengowAction->update($actionId, $validData);
        $this->assertTrue($result);
    }

    public function testUpdateActionWithUnauthorizedFields(): void
    {
        $this->lengowActionRepository->expects($this->once())
            ->method('update')
            ->willReturnCallback(function ($data, $context) {
                return $this->createMock(EntityWrittenContainerEvent::class);
            });

        $lengowAction = new LengowAction(
            $this->lengowActionRepository,
            $this->lengowLog,
            $this->lengowConnector,
            $this->lengowConfiguration
        );

        $actionId = Uuid::randomHex();
        $invalidData = [
            LengowActionDefinition::FIELD_ACTION_ID => Uuid::randomHex(), // Assuming this field is not authorized to be updated
        ];

        $result = $lengowAction->update($actionId, $invalidData);
        $this->assertTrue($result);
    }

    /**
     * @expectedException Exception
     */
    public function testUpdateActionRepositoryException(): void
    {
        $this->lengowActionRepository->expects($this->once())
            ->method('update')
            ->willThrowException(new \Exception('Mocked repository exception'));

        $this->lengowLog->expects($this->once())
            ->method('write');

        $lengowAction = new LengowAction(
            $this->lengowActionRepository,
            $this->lengowLog,
            $this->lengowConnector,
            $this->lengowConfiguration
        );

        $actionId = Uuid::randomHex();
        $validData = [
            LengowActionDefinition::FIELD_ACTION_TYPE => LengowAction::TYPE_SHIP,
        ];

        $result = $lengowAction->update($actionId, $validData);
        $this->assertFalse($result);
    }

    public function testGetActionByApiActionId(): void
    {
        $apiActionId = 123;

        $mockLengowActionEntity = $this->createMock(LengowActionEntity::class);

        $mockLengowActionCollection = $this->createMock(LengowActionCollection::class);
        $mockLengowActionCollection->method('first')->willReturn($mockLengowActionEntity);
        $mockLengowActionCollection->method('count')->willReturn(1);

        $mockEntitySearchResult = $this->createMock(EntitySearchResult::class);
        $mockEntitySearchResult->method('getEntities')->willReturn($mockLengowActionCollection);

        $this->lengowActionRepository->expects($this->once())
            ->method('search')
            ->willReturn($mockEntitySearchResult);

        $lengowAction = new LengowAction(
            $this->lengowActionRepository,
            $this->lengowLog,
            $this->lengowConnector,
            $this->lengowConfiguration
        );

        $result = $lengowAction->getActionByApiActionId($apiActionId);
        $this->assertInstanceOf(LengowActionEntity::class, $result);
    }

    public function testGetActiveActions(): void
    {
        $mockLengowActionEntity = $this->createMock(LengowActionEntity::class);

        $mockLengowActionCollection = $this->createMock(LengowActionCollection::class);
        $mockLengowActionCollection->method('first')->willReturn($mockLengowActionEntity);
        $mockLengowActionCollection->method('count')->willReturn(1);

        $mockEntitySearchResult = $this->createMock(EntitySearchResult::class);
        $mockEntitySearchResult->method('getEntities')->willReturn($mockLengowActionCollection);

        $this->lengowActionRepository->expects($this->once())
            ->method('search')
            ->willReturn($mockEntitySearchResult);

        $lengowAction = new LengowAction(
            $this->lengowActionRepository,
            $this->lengowLog,
            $this->lengowConnector,
            $this->lengowConfiguration
        );

        $result = $lengowAction->getActiveActions();
        $this->assertInstanceOf(LengowActionCollection::class, $result);
    }

    public function testGetActionsByOrderId(): void
    {
        $orderId = Uuid::randomHex();

        $mockLengowActionEntity = $this->createMock(LengowActionEntity::class);

        $mockLengowActionCollection = $this->createMock(LengowActionCollection::class);
        $mockLengowActionCollection->method('first')->willReturn($mockLengowActionEntity);
        $mockLengowActionCollection->method('count')->willReturn(1);

        $mockEntitySearchResult = $this->createMock(EntitySearchResult::class);
        $mockEntitySearchResult->method('getEntities')->willReturn($mockLengowActionCollection);

        $this->lengowActionRepository->expects($this->once())
            ->method('search')
            ->willReturn($mockEntitySearchResult);

        $lengowAction = new LengowAction(
            $this->lengowActionRepository,
            $this->lengowLog,
            $this->lengowConnector,
            $this->lengowConfiguration
        );

        $result = $lengowAction->getActionsByOrderId($orderId);
        $this->assertInstanceOf(LengowActionCollection::class, $result);
    }

    public function testGetActiveActionsByOrderId(): void
    {
        $orderId = Uuid::randomHex();

        $mockLengowActionEntity = $this->createMock(LengowActionEntity::class);

        $mockLengowActionCollection = $this->createMock(LengowActionCollection::class);
        $mockLengowActionCollection->method('first')->willReturn($mockLengowActionEntity);
        $mockLengowActionCollection->method('count')->willReturn(1);

        $mockEntitySearchResult = $this->createMock(EntitySearchResult::class);
        $mockEntitySearchResult->method('getEntities')->willReturn($mockLengowActionCollection);

        $this->lengowActionRepository->expects($this->once())
            ->method('search')
            ->willReturn($mockEntitySearchResult);

        $lengowAction = new LengowAction(
            $this->lengowActionRepository,
            $this->lengowLog,
            $this->lengowConnector,
            $this->lengowConfiguration
        );

        $result = $lengowAction->getActionsByOrderId($orderId, true);
        $this->assertInstanceOf(LengowActionCollection::class, $result);
    }

    public function testGetOldActions(): void
    {
        $intervalTime = 3600;

        $mockLengowActionEntity = $this->createMock(LengowActionEntity::class);

        $mockLengowActionCollection = $this->createMock(LengowActionCollection::class);
        $mockLengowActionCollection->method('first')->willReturn($mockLengowActionEntity);
        $mockLengowActionCollection->method('count')->willReturn(1);

        $mockEntitySearchResult = $this->createMock(EntitySearchResult::class);
        $mockEntitySearchResult->method('getEntities')->willReturn($mockLengowActionCollection);

        $this->lengowActionRepository->expects($this->once())
            ->method('search')
            ->willReturn($mockEntitySearchResult);

        $lengowAction = new LengowAction(
            $this->lengowActionRepository,
            $this->lengowLog,
            $this->lengowConnector,
            $this->lengowConfiguration
        );

        $result = $lengowAction->getOldActions($intervalTime);
        $this->assertInstanceOf(LengowActionCollection::class, $result);
    }

    public function testGetLastOrderActionType(): void
    {
        $orderId = Uuid::randomHex();

        $mockLengowActionEntity = $this->createMock(LengowActionEntity::class);
        $mockLengowActionEntity->method('getActionType')->willReturn('ship');

        $mockLengowActionCollection = $this->createMock(LengowActionCollection::class);
        $mockLengowActionCollection->method('last')->willReturn($mockLengowActionEntity);
        $mockLengowActionCollection->method('count')->willReturn(1);

        $mockEntitySearchResult = $this->createMock(EntitySearchResult::class);
        $mockEntitySearchResult->method('getEntities')->willReturn($mockLengowActionCollection);

        $this->lengowActionRepository->expects($this->once())
            ->method('search')
            ->willReturn($mockEntitySearchResult);

        $lengowAction = new LengowAction(
            $this->lengowActionRepository,
            $this->lengowLog,
            $this->lengowConnector,
            $this->lengowConfiguration
        );

        $result = $lengowAction->getLastOrderActionType($orderId);
        $this->assertEquals('ship', $result);
    }

    public function testGetLastOrderActionTypeReturnsNull(): void
    {
        $orderId = Uuid::randomHex();

        $mockLengowActionCollection = $this->createMock(LengowActionCollection::class);
        $mockLengowActionCollection->method('count')->willReturn(0);

        $mockEntitySearchResult = $this->createMock(EntitySearchResult::class);
        $mockEntitySearchResult->method('getEntities')->willReturn($mockLengowActionCollection);

        $this->lengowActionRepository->expects($this->once())
            ->method('search')
            ->willReturn($mockEntitySearchResult);

        $lengowAction = new LengowAction(
            $this->lengowActionRepository,
            $this->lengowLog,
            $this->lengowConnector,
            $this->lengowConfiguration
        );

        $result = $lengowAction->getLastOrderActionType($orderId);
        $this->assertNull($result);
    }

    public function testCanSendAction(): void
    {
        $params = [
            'action_type' => 'ship',
            'line' => '123',
        ];
        $orderId = Uuid::randomHex();

        $mockOrderEntity = $this->createMock(OrderEntity::class);
        $mockOrderEntity->method('getId')->willReturn($orderId);

        $mockLengowActionEntity = $this->createMock(LengowActionEntity::class);
        $mockLengowActionEntity->method('getState')->willReturn(LengowAction::STATE_NEW);
        $mockLengowActionEntity->method('getRetry')->willReturn(0);

        $mockLengowActionCollection = $this->createMock(LengowActionCollection::class);
        $mockLengowActionCollection->method('first')->willReturn($mockLengowActionEntity);
        $mockLengowActionCollection->method('count')->willReturn(1);

        $mockEntitySearchResult = $this->createMock(EntitySearchResult::class);
        $mockEntitySearchResult->method('getEntities')->willReturn($mockLengowActionCollection);

        $this->lengowActionRepository->expects($this->once())
            ->method('search')
            ->willReturn($mockEntitySearchResult);

        $this->lengowConnector->expects($this->once())
            ->method('queryApi')
            ->willReturn((object) ['count' => 1, 'results' => [(object) ['id' => 1]]]);

        $lengowAction = new LengowAction(
            $this->lengowActionRepository,
            $this->lengowLog,
            $this->lengowConnector,
            $this->lengowConfiguration
        );

        $result = $lengowAction->canSendAction($params, $mockOrderEntity);
        $this->assertFalse($result);
    }

    public function testCanSendActionUnsetParams(): void
    {
        $params = [
            'action_type' => 'ship',
            'line' => '123',
            'marketplace_order_id' => 'amazon_fr',
            LengowAction::ARG_SHIPPING_DATE => '2022-01-01',
            LengowAction::ARG_DELIVERY_DATE => '2022-01-02',
        ];
        $orderId = Uuid::randomHex();

        $mockOrderEntity = $this->createMock(OrderEntity::class);
        $mockOrderEntity->method('getId')->willReturn($orderId);

        $this->lengowConnector->expects($this->once())
            ->method('queryApi')
            ->willReturn((object) ['count' => 0]);

        $lengowAction = new LengowAction(
            $this->lengowActionRepository,
            $this->lengowLog,
            $this->lengowConnector,
            $this->lengowConfiguration
        );

        $result = $lengowAction->canSendAction($params, $mockOrderEntity);
        $this->assertTrue($result);
    }

    public function testCanSendActionThrowsException(): void
    {
        $this->expectException(LengowException::class);

        $params = [
            'action_type' => 'ship',
            'line' => '123',
        ];
        $orderId = Uuid::randomHex();

        $mockOrderEntity = $this->createMock(OrderEntity::class);
        $mockOrderEntity->method('getId')->willReturn($orderId);

        $this->lengowConnector->expects($this->once())
            ->method('queryApi')
            ->willReturn((object) ['error' => (object) ['message' => 'Mocked error message']]);

        $lengowAction = new LengowAction(
            $this->lengowActionRepository,
            $this->lengowLog,
            $this->lengowConnector,
            $this->lengowConfiguration
        );

        $lengowAction->canSendAction($params, $mockOrderEntity);
    }

    public function testCanSendActionReturnsTrue(): void
    {
        $params = [
            'action_type' => 'ship',
            'line' => '123',
        ];
        $orderId = Uuid::randomHex();

        $mockOrderEntity = $this->createMock(OrderEntity::class);
        $mockOrderEntity->method('getId')->willReturn($orderId);

        $this->lengowConnector->expects($this->once())
            ->method('queryApi')
            ->willReturn((object) ['count' => 0]);

        $lengowAction = new LengowAction(
            $this->lengowActionRepository,
            $this->lengowLog,
            $this->lengowConnector,
            $this->lengowConfiguration
        );

        $result = $lengowAction->canSendAction($params, $mockOrderEntity);
        $this->assertTrue($result);
    }

    public function testCanSendActionCreatesAction(): void
    {
        $params = [
            'action_type' => 'ship',
            'line' => '123',
            'marketplace_order_id' => 'amazon_fr',
        ];
        $orderId = Uuid::randomHex();

        $mockOrderEntity = $this->createMock(OrderEntity::class);
        $mockOrderEntity->method('getId')->willReturn($orderId);

        $this->lengowConnector->expects($this->once())
            ->method('queryApi')
            ->willReturn((object) ['count' => 1, 'results' => [(object) ['id' => 1]]]);

        $this->lengowActionRepository->expects($this->once())
            ->method('search')
            ->willReturn($this->createMock(EntitySearchResult::class));

        $this->lengowActionRepository->expects($this->once())
            ->method('create')
            ->willReturn($this->createMock(EntityWrittenContainerEvent::class));

        $lengowAction = new LengowAction(
            $this->lengowActionRepository,
            $this->lengowLog,
            $this->lengowConnector,
            $this->lengowConfiguration
        );

        $result = $lengowAction->canSendAction($params, $mockOrderEntity);
        $this->assertFalse($result);
    }

    public function testSendAction(): void
    {
        $params = [
            LengowAction::ARG_ACTION_TYPE => LengowAction::TYPE_SHIP,
            LengowAction::ARG_LINE => '123',
            LengowAction::ARG_CARRIER => 'DHL',
            LengowAction::ARG_CARRIER_NAME => 'DHL Express',
            LengowAction::ARG_SHIPPING_METHOD => 'Express',
            LengowAction::ARG_TRACKING_NUMBER => '123456789',
            LengowAction::ARG_TRACKING_URL => 'http://tracking.example.com',
            LengowAction::ARG_SHIPPING_PRICE => '10.00',
            LengowAction::ARG_SHIPPING_DATE => '2022-01-01',
            LengowAction::ARG_DELIVERY_DATE => '2022-01-02',
            LengowImport::ARG_MARKETPLACE_ORDER_ID => 'MO123456',
        ];

        $orderEntity = $this->createMock(OrderEntity::class);
        $lengowOrderEntity = $this->createMock(LengowOrderEntity::class);

        $this->lengowConnector->expects($this->once())
            ->method('queryApi')
            ->with(
                $this->equalTo(LengowConnector::POST),
                $this->equalTo(LengowConnector::API_ORDER_ACTION),
                $this->equalTo($params)
            )
            ->willReturn((object) ['id' => 1]);

        $lengowAction = new LengowAction(
            $this->lengowActionRepository,
            $this->lengowLog,
            $this->lengowConnector,
            $this->lengowConfiguration
        );

        $lengowAction->sendAction($params, $orderEntity, $lengowOrderEntity);
    }

    public function testSendActionWithoutIdInResult(): void
    {
        $params = [
            LengowAction::ARG_ACTION_TYPE => LengowAction::TYPE_SHIP,
            LengowAction::ARG_LINE => '123',
            LengowAction::ARG_CARRIER => 'DHL',
            LengowAction::ARG_CARRIER_NAME => 'DHL Express',
            LengowAction::ARG_SHIPPING_METHOD => 'Express',
            LengowAction::ARG_TRACKING_NUMBER => '123456789',
            LengowAction::ARG_TRACKING_URL => 'http://tracking.example.com',
            LengowAction::ARG_SHIPPING_PRICE => '10.00',
            LengowAction::ARG_SHIPPING_DATE => '2022-01-01',
            LengowAction::ARG_DELIVERY_DATE => '2022-01-02',
            LengowImport::ARG_MARKETPLACE_ORDER_ID => 'MO123456',
        ];

        $orderEntity = $this->createMock(OrderEntity::class);
        $lengowOrderEntity = $this->createMock(LengowOrderEntity::class);

        $this->lengowConnector->expects($this->once())
            ->method('queryApi')
            ->with(
                $this->equalTo(LengowConnector::POST),
                $this->equalTo(LengowConnector::API_ORDER_ACTION),
                $this->equalTo($params)
            )
            ->willReturn((object) ['result' => 'No id']);

        $this->lengowLog->expects($this->once())
            ->method('encodeMessage')
            ->with(
                $this->equalTo('lengow_log.exception.action_not_created'),
                $this->equalTo(['error_message' => json_encode(['result' => 'No id'])])
            );

        $lengowAction = new LengowAction(
            $this->lengowActionRepository,
            $this->lengowLog,
            $this->lengowConnector,
            $this->lengowConfiguration
        );

        $this->expectException(LengowException::class);

        $lengowAction->sendAction($params, $orderEntity, $lengowOrderEntity);
    }

    public function testSendActionWithNullResult(): void
    {
        $params = [
            LengowAction::ARG_ACTION_TYPE => LengowAction::TYPE_SHIP,
            LengowAction::ARG_LINE => '123',
            LengowAction::ARG_CARRIER => 'DHL',
            LengowAction::ARG_CARRIER_NAME => 'DHL Express',
            LengowAction::ARG_SHIPPING_METHOD => 'Express',
            LengowAction::ARG_TRACKING_NUMBER => '123456789',
            LengowAction::ARG_TRACKING_URL => 'http://tracking.example.com',
            LengowAction::ARG_SHIPPING_PRICE => '10.00',
            LengowAction::ARG_SHIPPING_DATE => '2022-01-01',
            LengowAction::ARG_DELIVERY_DATE => '2022-01-02',
            LengowImport::ARG_MARKETPLACE_ORDER_ID => 'MO123456',
        ];

        $orderEntity = $this->createMock(OrderEntity::class);
        $lengowOrderEntity = $this->createMock(LengowOrderEntity::class);

        $this->lengowConnector->expects($this->once())
            ->method('queryApi')
            ->with(
                $this->equalTo(LengowConnector::POST),
                $this->equalTo(LengowConnector::API_ORDER_ACTION),
                $this->equalTo($params)
            )
            ->willReturn(null);

        $this->lengowLog->expects($this->once())
            ->method('encodeMessage')
            ->with(
                $this->equalTo('lengow_log.exception.action_not_created_api')
            );

        $lengowAction = new LengowAction(
            $this->lengowActionRepository,
            $this->lengowLog,
            $this->lengowConnector,
            $this->lengowConfiguration
        );

        $this->expectException(LengowException::class);

        $lengowAction->sendAction($params, $orderEntity, $lengowOrderEntity);
    }

    public function testFinishActions(): void
    {
        $orderId = 'testOrderId';
        $actionType = LengowAction::TYPE_SHIP;

        // Mocking LengowActionEntity
        $lengowActionEntity = $this->createMock(LengowActionEntity::class);
        $lengowActionEntity->method('getId')->willReturn('testActionId');

        // Mocking ActionCollection to return the LengowActionEntity
        $lengowActionCollection = $this->getMockBuilder(ActionCollection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getElements', 'getIterator'])
            ->getMock();
        $lengowActionCollection->method('getElements')->willReturn([$lengowActionEntity]);
        $lengowActionCollection->method('getIterator')->willReturn(new ArrayIterator([$lengowActionEntity]));

        // Mocking EntitySearchResult to return the mocked ActionCollection
        $entitySearchResult = $this->createMock(EntitySearchResult::class);
        $entitySearchResult->method('getEntities')->willReturn($lengowActionCollection);

        // Mocking the repository to return the mocked EntitySearchResult
        $this->lengowActionRepository->expects($this->once())
            ->method('search')
            ->willReturn($entitySearchResult);

        // Creating a mock of EntityWrittenContainerEvent
        $entityWrittenContainerEvent = $this->createMock(EntityWrittenContainerEvent::class);

        // Mocking the update method to return true for success check
        $lengowAction = $this->getMockBuilder(LengowAction::class)
            ->setConstructorArgs([$this->lengowActionRepository, $this->lengowLog, $this->lengowConnector, $this->lengowConfiguration])
            ->onlyMethods(['update'])
            ->getMock();
        $lengowAction->expects($this->once())
            ->method('update')
            ->with('testActionId', [LengowActionDefinition::FIELD_STATE => LengowAction::STATE_FINISH])
            ->willReturn(true);

        // Calling finishActions
        $result = $lengowAction->finishActions($orderId, $actionType);

        // Asserting that the result is true
        $this->assertTrue($result);
    }

    public function testFinishActionsFailure(): void
    {
        $orderId = 'testOrderId';
        $actionType = LengowAction::TYPE_SHIP;

        // Mocking LengowActionEntity
        $lengowActionEntity = $this->createMock(LengowActionEntity::class);
        $lengowActionEntity->method('getId')->willReturn('testActionId');

        // Mocking ActionCollection to return the LengowActionEntity
        $lengowActionCollection = $this->getMockBuilder(ActionCollection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getElements', 'getIterator'])
            ->getMock();
        $lengowActionCollection->method('getElements')->willReturn([$lengowActionEntity]);
        $lengowActionCollection->method('getIterator')->willReturn(new ArrayIterator([$lengowActionEntity]));

        // Mocking EntitySearchResult to return the mocked ActionCollection
        $entitySearchResult = $this->createMock(EntitySearchResult::class);
        $entitySearchResult->method('getEntities')->willReturn($lengowActionCollection);

        // Mocking the repository to return the mocked EntitySearchResult
        $this->lengowActionRepository->expects($this->once())
            ->method('search')
            ->willReturn($entitySearchResult);

        // Creating a mock of EntityWrittenContainerEvent
        $entityWrittenContainerEvent = $this->createMock(EntityWrittenContainerEvent::class);

        // Mocking the update method to return true for success check
        $lengowAction = $this->getMockBuilder(LengowAction::class)
            ->setConstructorArgs([$this->lengowActionRepository, $this->lengowLog, $this->lengowConnector, $this->lengowConfiguration])
            ->onlyMethods(['update'])
            ->getMock();
        $lengowAction->expects($this->once())
            ->method('update')
            ->with('testActionId', [LengowActionDefinition::FIELD_STATE => LengowAction::STATE_FINISH])
            ->willReturn(false);

        // Calling finishActions
        $result = $lengowAction->finishActions($orderId, $actionType);

        // Asserting that the result is true
        $this->assertFalse($result);
    }

}
