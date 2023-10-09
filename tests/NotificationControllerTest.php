<?php

declare(strict_types=1);


use Atk4\Data\Persistence\Sql;
use Atk4\Data\Reference\HasMany;
use Atk4\Data\Schema\TestCase;
use PhilippR\Atk4\Notification\Notification;
use PhilippR\Atk4\Notification\Tests\TestClasses\DemoNotificationController;
use PhilippR\Atk4\Notification\Tests\TestClasses\ModelWithNotificationTrait;

class NotificationControllerTest extends TestCase
{


    protected function setUp(): void
    {
        parent::setUp();
        $this->db = new Sql('sqlite::memory:');
        $this->createMigrator(new Notification($this->db))->create();
        $this->createMigrator(new ModelWithNotificationTrait($this->db))->create();
    }

    public function testGetMaxNotificationLevel(): void
    {
        $model = (new ModelWithNotificationTrait($this->db))->createEntity();
        $model->save();
        $controller = new DemoNotificationController($model);

        self::assertSame(1, $controller->getMaxNotificationLevel());

        $controller->createLevel3Notification();
        self::assertSame(3, $controller->getMaxNotificationLevel());
        self::assertCount(2, $model->ref(Notification::class));

        $controller->deleteAllNotifications();
        self::assertSame(0, $controller->getMaxNotificationLevel());
    }

    public function testGetNotificationByType(): void
    {
        $model = (new ModelWithNotificationTrait($this->db))->createEntity();
        $model->save();
        $controller = $model->getNotificationController();
        $res = $controller->getNotificationByType('SOMETYPE');
        self::assertInstanceOf(Notification::class, $res);
        self::assertNull($controller->getNotificationByType('SOMENONEXISTANTTYPE'));
    }

    public function testCreateNotificationUpdatesExisting(): void
    {
        $model = (new ModelWithNotificationTrait($this->db))->createEntity();
        $model->save();
        $controller = $model->getNotificationController();
        $this->callProtected($controller, 'createNotification', 'SOMETYPE', 'Blabla', null, 3);
        self::assertCount(1, $model->ref(Notification::class));
        self::assertSame(3, $controller->getMaxNotificationLevel());
    }

    public function testDeleteNotification(): void
    {
        $model = (new ModelWithNotificationTrait($this->db))->createEntity();
        $model->save();
        $controller = $model->getNotificationController();
        $this->callProtected($controller, 'deleteNotification', 'SOMETYPE');
        self::assertCount(0, $model->ref(Notification::class));
    }

    public function testLoadNotificationLoadsNotificationsFromDB(): void
    {
        $model = (new ModelWithNotificationTrait($this->db))->createEntity();
        $model->save();
        $model2 = new ModelWithNotificationTrait($this->db);
        $model2 = $model2->load($model->getId());

        self::assertCount(1, $model2->ref(Notification::class));
    }

    public function testCreateNotificationForField(): void
    {
        $model = (new ModelWithNotificationTrait($this->db))->createEntity();
        $model->save();
        $controller = $model->getNotificationController();
        $controller->deleteAllNotifications();
        self::assertCount(0, $model->ref(Notification::class));
        $this->callProtected($controller, 'createNotificationIfFieldEmpty', 'name');
        self::assertCount(1, $model->ref(Notification::class));
        $notification = $model->ref(Notification::class)->loadAny();
        self::assertSame(
            'name',
            $notification->get('field')
        );

        $this->callProtected($controller, 'deleteNotificationForField', 'name');
        self::assertCount(0, $model->ref(Notification::class));
    }

    public function testCreateNotificationForFieldDeletesIfFieldNotEmpty(): void
    {
        $model = (new ModelWithNotificationTrait($this->db))->createEntity();
        $model->save();
        $controller = $model->getNotificationController();
        $controller->deleteAllNotifications();
        self::assertCount(0, $model->ref(Notification::class));
        $this->callProtected($controller, 'createNotificationIfFieldEmpty', 'name');
        self::assertCount(1, $model->ref(Notification::class));
        $model->set('name', 'somevalue');
        $this->callProtected($controller, 'createNotificationIfFieldEmpty', 'name');
        self::assertCount(0, $model->ref(Notification::class));
    }

    public function testEnvSettingDisablesNotifications(): void
    {
        $_ENV['createNotifications'] = false;
        $model = (new ModelWithNotificationTrait($this->db))->createEntity();
        $model->set('name', 'Lala');
        $model->save();

        self::assertSame(
            0,
            (int)$model->ref(Notification::class)->action('count')->getOne()
        );

        $_ENV['createNotifications'] = true;
        $model->set('name', 'hehe');
        $model->save();
        self::assertSame(
            1,
            (int)$model->ref(Notification::class)->action('count')->getOne()
        );
    }

    public function testCreateNotificationDoesNotCreateSameNotificationMoreThanOnce(): void
    {
        $model = (new ModelWithNotificationTrait($this->db))->createEntity();
        $model->save();
        self::assertSame(
            1,
            (int)$model->ref(Notification::class)->action('count')->getOne()
        );

        $model->set('name', 'somename');
        $model->save();
        self::assertSame(
            1,
            (int)$model->ref(Notification::class)->action('count')->getOne()
        );
    }

    public function testExportNotificationFieldLevels(): void
    {
        $model = (new ModelWithNotificationTrait($this->db))->createEntity();
        $model->save();
        $controller = $model->getNotificationController();
        $controller->createLevelNotificationWithField('field3');
        self::assertSame(
            ['field3' => 2],
            $controller->exportNotificationFieldLevels()
        );

        $controller->createLevelNotificationWithField('field2');
        self::assertEquals(
            [
                'field2' => 2,
                'field3' => 2
            ],
            $controller->exportNotificationFieldLevels()
        );
    }

    public function testExportNotificationFieldLevelsIgnoresDeactivated(): void
    {
        $model = (new ModelWithNotificationTrait($this->db))->createEntity();
        $model->save();
        $controller = $model->getNotificationController();
        $notification = $controller->createLevelNotificationWithField('field3');
        self::assertSame(
            ['field3' => 2],
            $controller->exportNotificationFieldLevels()
        );

        $notification->set('deactivated', 1);
        $notification->save();
        $controller->resetLoadedNotifications();
        self::assertSame(
            [],
            $controller->exportNotificationFieldLevels()
        );
    }
}
