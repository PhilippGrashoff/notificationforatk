<?php

declare(strict_types=1);

namespace notificationforatk\tests;

use traitsforatkdata\TestCase;
use atk4\data\Persistence;
use atk4\data\Reference\HasMany;
use atk4\schema\Migration;
use notificationforatk\Notification;
use notificationforatk\tests\TestClasses\AppWithNofiticationSetting;
use notificationforatk\tests\TestClasses\ModelWithNotifications;

class ModelWithNotificationTraitTest extends TestCase
{

    protected $sqlitePersistenceModels = [
        Notification::class,
        ModelWithNotifications::class
    ];

    public function testReferenceSetInInit()
    {
        $model = new ModelWithNotifications(new Persistence\Array_());
        self::assertInstanceOf(
            HasMany::class,
            $model->getRef(Notification::class)
        );
    }

    public function testAfterSaveHook()
    {
        $model = new ModelWithNotifications(new Persistence\Array_());
        $model->save();
        self::assertCount(
            1,
            $model->ref(Notification::class)
        );
    }

    public function testAfterLoadHook()
    {
        $persistence = $this->_getSqliteWithMigrations();
        $model1 = new ModelWithNotifications($persistence);
        $model1->save();
        $model2 = new ModelWithNotifications($persistence, ['notificationType' => 'SOME_OTHER_TYPE']);
        $model2->save();

        $i = 0;
        foreach (new ModelWithNotifications($persistence) as $record) {
            self::assertCount(
                1,
                $record->ref(Notification::class)
            );
            $i++;
            if ($i === 1) {
                self::assertEquals(
                    'SOMETYPE',
                    $record->ref(Notification::class)->loadAny()->get('value')
                );
            } else {
                self::assertEquals(
                    'SOME_OTHER_TYPE',
                    $record->ref(Notification::class)->loadAny()->get('value')
                );
            }
        }

        self::assertSame(2, $i);
    }

    public function testgetMaxNotificationLevel()
    {
        $persistence = $this->_getSqliteWithMigrations();
        $model = new ModelWithNotifications($persistence);
        $model->save();

        self::assertEquals(1, $model->getMaxNotificationLevel());

        $model->createLevel3Notification();
        self::assertEquals(3, $model->getMaxNotificationLevel());
        self::assertCount(2, $model->ref(Notification::class));

        $model->deleteAllNotifications();
        self::assertEquals(0, $model->getMaxNotificationLevel());
    }

    public function testgetNotificationByType()
    {
        $persistence = $this->_getSqliteWithMigrations();
        $model = new ModelWithNotifications($persistence);
        $model->save();
        $res = $model->getNotificationByType('SOMETYPE');
        self::assertInstanceOf(Notification::class, $res);
        self::assertNull($model->getNotificationByType('SOMENONEXISTANTTYPE'));
    }

    public function testCreateNotificationUpdatesExisting() {
        $persistence = $this->_getSqliteWithMigrations();
        $model = new ModelWithNotifications($persistence);
        $model->save();
        $this->callProtected($model, 'createNotification', 'SOMETYPE', 'Blabla', [], 3);
        self::assertCount(1, $model->ref(Notification::class));
        self::assertEquals(3, $model->getMaxNotificationLevel());
    }

    public function testDeleteNotification() {
        $persistence = $this->_getSqliteWithMigrations();
        $model = new ModelWithNotifications($persistence);
        $model->save();
        $this->callProtected($model, 'deleteNotification', 'SOMETYPE');
        self::assertCount(0, $model->ref(Notification::class));
    }

    public function testFieldsArrayAsAdditionalArg() {
        $persistence = $this->_getSqliteWithMigrations();
        $model = new ModelWithNotifications($persistence);
        $model->save();
        $this->callProtected($model, 'createNotification', 'SOMETYPE', 'bla', ['field1', 'field2']);
        self::assertCount(2, $model->ref(Notification::class));
        $this->callProtected($model, 'deleteNotification', 'SOMETYPE', ['field1', 'field2']);
        self::assertCount(1, $model->ref(Notification::class));
        $this->callProtected($model, 'deleteNotification', 'SOMETYPE', ['field1', 'field2']);
        self::assertCount(1, $model->ref(Notification::class));
        $this->callProtected($model, 'deleteNotification', 'SOMETYPE');
        self::assertCount(0, $model->ref(Notification::class));
    }

    public function testLoadNotificationLoadsNotificationsFromDB() {
        $persistence = $this->_getSqliteWithMigrations();
        $model = new ModelWithNotifications($persistence);
        $model->save();
        $model2 = new ModelWithNotifications($persistence);
        $model2->load($model->get($model->id_field));
        $model2->loadNotifications();

        self::assertCount(1, $model2->ref(Notification::class));
    }

    public function testCreateNotificationForField() {
        $persistence = $this->_getSqliteWithMigrations();
        $model = new ModelWithNotifications($persistence);
        $model->save();
        $model->deleteAllNotifications();
        self::assertCount(0, $model->ref(Notification::class));
        $this->callProtected($model, 'createNotificationIfFieldEmpty', 'name');
        self::assertCount(1, $model->ref(Notification::class));
        $notification = $model->ref(Notification::class)->loadAny();
        self::assertEquals(
            ['name'],
            $notification->get('field')
        );

        $this->callProtected($model, 'deleteNotificationForField', 'name');
        self::assertCount(0, $model->ref(Notification::class));
    }

    public function testCreateNotificationForFieldDeletesIfFieldNotEmpty() {
        $persistence = $this->_getSqliteWithMigrations();
        $model = new ModelWithNotifications($persistence);
        $model->save();
        $model->deleteAllNotifications();
        self::assertCount(0, $model->ref(Notification::class));
        $this->callProtected($model, 'createNotificationIfFieldEmpty', 'name');
        self::assertCount(1, $model->ref(Notification::class));
        $model->set('name', 'somevalue');
        $this->callProtected($model, 'createNotificationIfFieldEmpty', 'name');
        self::assertCount(0, $model->ref(Notification::class));
    }

    public function testSettingInAppDisablesNotifications() {
        $persistence = $this->getSqliteTestPersistence();
        $persistence->app = new AppWithNofiticationSetting();
        $persistence->app->createNotifications = false;
        $model = new ModelWithNotifications($persistence);
        $model->set('name', 'Lala');
        $model->save();

        self::assertEquals(
            0,
            $model->ref(Notification::class)->action('count')->getOne()
        );

        $persistence->app->createNotifications = true;
        $model->set('name', 'hehe');
        $model->save();
        self::assertEquals(
            1,
            $model->ref(Notification::class)->action('count')->getOne()
        );
    }


    protected function _getSqliteWithMigrations(): Persistence
    {
        $persistence = Persistence::connect('sqlite::memory:');
        $model1 = new ModelWithNotifications($persistence);
        Migration::of($model1)->drop()->create();
        $notification = new Notification($persistence);
        Migration::of($notification)->drop()->create();

        return $persistence;
    }
}
