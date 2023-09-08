<?php

declare(strict_types=1);

namespace PhilippR\Atk4\Notification\Tests;

use Atk4\Data\Persistence\Sql;
use Atk4\Data\Reference\HasMany;
use Atk4\Data\Schema\TestCase;
use PhilippR\Atk4\Notification\Notification;
use PhilippR\Atk4\Notification\Tests\TestClasses\ModelWithNotifications;

class ModelWithNotificationTraitTest extends TestCase
{


    protected function setUp(): void
    {
        parent::setUp();
        $this->db = new Sql('sqlite::memory:');
        $this->createMigrator(new Notification($this->db))->create();
        $this->createMigrator(new ModelWithNotifications($this->db))->create();
    }

    public function testReferenceSetInInit(): void
    {
        $model = new ModelWithNotifications($this->db);
        self::assertInstanceOf(
            HasMany::class,
            $model->getReference(Notification::class)
        );
    }

    public function testAfterSaveHook(): void
    {
        $model = (new ModelWithNotifications($this->db))->createEntity();
        $model->save();
        self::assertCount(
            1,
            $model->ref(Notification::class)
        );
    }

    public function testAfterLoadHook(): void
    {
        $model1 = (new ModelWithNotifications($this->db))->createEntity();
        $model1->save();
        $model2 = (new ModelWithNotifications($this->db, ['notificationType' => 'SOME_OTHER_TYPE']))->createEntity();
        $model2->save();

        $i = 0;
        foreach (new ModelWithNotifications($this->db) as $record) {
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
        $model = (new ModelWithNotifications($this->db))->createEntity();
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
        $model = (new ModelWithNotifications($this->db))->createEntity();
        $model->save();
        $res = $model->getNotificationByType('SOMETYPE');
        self::assertInstanceOf(Notification::class, $res);
        self::assertNull($model->getNotificationByType('SOMENONEXISTANTTYPE'));
    }

    public function testCreateNotificationUpdatesExisting(): void
    {
        $model = (new ModelWithNotifications($this->db))->createEntity();
        $model->save();
        $this->callProtected($model, 'createNotification', 'SOMETYPE', 'Blabla', null, 3);
        self::assertCount(1, $model->ref(Notification::class));
        self::assertSame(3, $model->getMaxNotificationLevel());
    }

    public function testDeleteNotification(): void
    {
        $model = (new ModelWithNotifications($this->db))->createEntity();
        $model->save();
        $this->callProtected($model, 'deleteNotification', 'SOMETYPE');
        self::assertCount(0, $model->ref(Notification::class));
    }

    public function testLoadNotificationLoadsNotificationsFromDB(): void
    {
        $model = (new ModelWithNotifications($this->db))->createEntity();
        $model->save();
        $model2 = new ModelWithNotifications($this->db);
        $model2 = $model2->load($model->getId());
        $model2->loadNotifications();

        self::assertCount(1, $model2->ref(Notification::class));
    }

    public function testCreateNotificationForField(): void
    {
        $model = (new ModelWithNotifications($this->db))->createEntity();
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
        $model = (new ModelWithNotifications($this->db))->createEntity();
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
        $_ENV['createNotifications'] = false;
        $model = (new ModelWithNotifications($this->db))->createEntity();
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

    public function testAddMaxNotificationLevelExpression(): void
    {
        $model = new ModelWithNotifications($this->db);
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
        $model = (new ModelWithNotifications($this->db))->createEntity();
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
        $model = (new ModelWithNotifications($this->db))->createEntity();
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
        $model = (new ModelWithNotifications($this->db))->createEntity();
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
