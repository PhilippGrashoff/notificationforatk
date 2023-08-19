<?php

declare(strict_types=1);

namespace notificationforatk\tests;

use Atk4\Data\Reference\HasMany;
use atkextendedtestcase\TestCase;
use notificationforatk\Notification;
use notificationforatk\tests\TestClasses\ModelWithNotifications;

class ModelWithNotificationTraitTest extends TestCase
{

    protected array $sqlitePersistenceModels = [
        Notification::class,
        ModelWithNotifications::class
    ];

    public function testReferenceSetInInit(): void
    {
        $model = new ModelWithNotifications($this->getSqliteTestPersistence());
        self::assertInstanceOf(
            HasMany::class,
            $model->getReference(Notification::class)
        );
    }

    public function testAfterSaveHook(): void
    {
        $model = (new ModelWithNotifications($this->getSqliteTestPersistence()))->createEntity();
        $model->save();
        self::assertCount(
            1,
            $model->ref(Notification::class)
        );
    }

    public function testAfterLoadHook(): void
    {
        $persistence = $this->getSqliteTestPersistence();
        $model1 = (new ModelWithNotifications($persistence))->createEntity();
        $model1->save();
        $model2 = (new ModelWithNotifications($persistence, ['notificationType' => 'SOME_OTHER_TYPE']))->createEntity();
        $model2->save();

        $i = 0;
        foreach (new ModelWithNotifications($persistence) as $record) {
            self::assertCount(
                1,
                $record->ref(Notification::class)
            );
            $i++;
            if ($i === 1) {
                self::assertSame(
                    'SOMETYPE',
                    $record->ref(Notification::class)->loadAny()->get('value')
                );
            } else {
                self::assertSame(
                    'SOME_OTHER_TYPE',
                    $record->ref(Notification::class)->loadAny()->get('value')
                );
            }
        }

        self::assertSame(2, $i);
    }

    public function testgetMaxNotificationLevel(): void
    {
        $persistence = $this->getSqliteTestPersistence();
        $model = (new ModelWithNotifications($persistence))->createEntity();
        $model->save();

        self::assertSame(1, $model->getMaxNotificationLevel());

        $model->createLevel3Notification();
        self::assertSame(3, $model->getMaxNotificationLevel());
        self::assertCount(2, $model->ref(Notification::class));

        $model->deleteAllNotifications();
        self::assertSame(0, $model->getMaxNotificationLevel());
    }

    public function testgetNotificationByType(): void
    {
        $persistence = $this->getSqliteTestPersistence();
        $model = (new ModelWithNotifications($persistence))->createEntity();
        $model->save();
        $res = $model->getNotificationByType('SOMETYPE');
        self::assertInstanceOf(Notification::class, $res);
        self::assertNull($model->getNotificationByType('SOMENONEXISTANTTYPE'));
    }

    public function testCreateNotificationUpdatesExisting(): void
    {
        $persistence = $this->getSqliteTestPersistence();
        $model = (new ModelWithNotifications($persistence))->createEntity();
        $model->save();
        $this->callProtected($model, 'createNotification', 'SOMETYPE', 'Blabla', null, 3);
        self::assertCount(1, $model->ref(Notification::class));
        self::assertSame(3, $model->getMaxNotificationLevel());
    }

    public function testDeleteNotification(): void
    {
        $persistence = $this->getSqliteTestPersistence();
        $model = (new ModelWithNotifications($persistence))->createEntity();
        $model->save();
        $this->callProtected($model, 'deleteNotification', 'SOMETYPE');
        self::assertCount(0, $model->ref(Notification::class));
    }

    public function testLoadNotificationLoadsNotificationsFromDB(): void
    {
        $persistence = $this->getSqliteTestPersistence();
        $model = (new ModelWithNotifications($persistence))->createEntity();
        $model->save();
        $model2 = new ModelWithNotifications($persistence);
        $model2 = $model2->load($model->getId());
        $model2->loadNotifications();

        self::assertCount(1, $model2->ref(Notification::class));
    }

    public function testCreateNotificationForField(): void
    {
        $persistence = $this->getSqliteTestPersistence();
        $model = (new ModelWithNotifications($persistence))->createEntity();
        $model->save();
        $model->deleteAllNotifications();
        self::assertCount(0, $model->ref(Notification::class));
        $this->callProtected($model, 'createNotificationIfFieldEmpty', 'name');
        self::assertCount(1, $model->ref(Notification::class));
        $notification = $model->ref(Notification::class)->loadAny();
        self::assertSame(
            'name',
            $notification->get('field')
        );

        $this->callProtected($model, 'deleteNotificationForField', 'name');
        self::assertCount(0, $model->ref(Notification::class));
    }

    public function testCreateNotificationForFieldDeletesIfFieldNotEmpty(): void
    {
        $persistence = $this->getSqliteTestPersistence();
        $model = (new ModelWithNotifications($persistence))->createEntity();
        $model->save();
        $model->deleteAllNotifications();
        self::assertCount(0, $model->ref(Notification::class));
        $this->callProtected($model, 'createNotificationIfFieldEmpty', 'name');
        self::assertCount(1, $model->ref(Notification::class));
        $model->set('name', 'somevalue');
        $this->callProtected($model, 'createNotificationIfFieldEmpty', 'name');
        self::assertCount(0, $model->ref(Notification::class));
    }

    public function testEnvSettingDisablesNotifications(): void
    {
        $persistence = $this->getSqliteTestPersistence();
        $_ENV['createNotifications'] = false;
        $model = (new ModelWithNotifications($persistence))->createEntity();
        $model->set('name', 'Lala');
        $model->save();

        self::assertSame(
            0,
            (int) $model->ref(Notification::class)->action('count')->getOne()
        );

        $_ENV['createNotifications'] = true;
        $model->set('name', 'hehe');
        $model->save();
        self::assertSame(
            1,
            (int) $model->ref(Notification::class)->action('count')->getOne()
        );
    }

    public function testAddMaxNotificationLevelExpression(): void
    {
        $persistence = $this->getSqliteTestPersistence();
        $model = new ModelWithNotifications($persistence);
        $model->addMaxNotificationLevelExpression();
        $model = $model->createEntity();
        $model->save();
        self::assertTrue($model->hasField('max_notification_level'));
        self::assertSame(
            0,
            $model->get('max_notification_level')
        );

        $model->createLevel1Notification();
        $model->reload();
        self::assertSame(
            1,
            $model->get('max_notification_level')
        );

        $model->createLevel2Notification();
        $model->reload();
        self::assertSame(
            2,
            $model->get('max_notification_level')
        );

        $level3 = $model->createLevel3Notification();
        $model->reload();
        self::assertSame(
            3,
            $model->get('max_notification_level')
        );

        $level3->set('deactivated', 1);
        $level3->save();
        $model->reload();
        self::assertSame(
            2,
            $model->get('max_notification_level')
        );
    }

    public function testCreateNotificationDoesNotCreateSameNotificationMoreThanOnce(): void
    {
        $persistence = $this->getSqliteTestPersistence();
        $model = (new ModelWithNotifications($persistence))->createEntity();
        $model->save();
        self::assertSame(
            1,
            (int) $model->ref(Notification::class)->action('count')->getOne()
        );

        $model->set('name', 'somename');
        $model->save();
        self::assertSame(
            1,
            (int) $model->ref(Notification::class)->action('count')->getOne()
        );
    }

    public function testExportNotificationFieldLevels(): void
    {
        $persistence = $this->getSqliteTestPersistence();
        $model = (new ModelWithNotifications($persistence))->createEntity();
        $model->save();
        $model->createLevelNotificationWithField('field3');
        self::assertSame(
            ['field3' => 2],
            $model->exportNotificationFieldLevels()
        );

        $model->createLevelNotificationWithField('field2');
        self::assertEquals(
            [
                'field2' => 2,
                'field3' => 2
            ],
            $model->exportNotificationFieldLevels()
        );
    }

    public function testExportNotificationFieldLevelsIgnoresDeactivated(): void
    {
        $persistence = $this->getSqliteTestPersistence();
        $model = (new ModelWithNotifications($persistence))->createEntity();
        $model->save();
        $notification = $model->createLevelNotificationWithField('field3');
        self::assertSame(
            ['field3' => 2],
            $model->exportNotificationFieldLevels()
        );

        $notification->set('deactivated', 1);
        $notification->save();
        $model->resetLoadedNotifications();
        self::assertSame(
            [],
            $model->exportNotificationFieldLevels()
        );
    }
}
