<?php

declare(strict_types=1);

namespace notificationforatk\tests;

use atk4\core\AtkPhpunit\TestCase;
use atk4\data\Persistence;
use atk4\data\Reference\HasMany;
use atk4\schema\Migration;
use notificationforatk\Notification;
use notificationforatk\tests\TestClasses\ClassWithNotifications;

class ModelWithNotificationTraitTest extends TestCase
{

    public function testReferenceSetInInit()
    {
        $model = new ClassWithNotifications(new Persistence\Array_());
        self::assertInstanceOf(
            HasMany::class,
            $model->getRef(Notification::class)
        );
    }

    public function testAfterSaveHook()
    {
        $model = new ClassWithNotifications(new Persistence\Array_());
        $model->save();
        self::assertCount(
            1,
            $model->ref(Notification::class)
        );
    }

    public function testAfterLoadHook()
    {
        $persistence = $this->_getSqliteWithMigrations();
        $model1 = new ClassWithNotifications($persistence);
        $model1->save();
        $model2 = new ClassWithNotifications($persistence, ['notificationType' => 'SOME_OTHER_TYPE']);
        $model2->save();

        $i = 0;
        foreach (new ClassWithNotifications($persistence) as $record) {
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
        $model = new ClassWithNotifications($persistence);
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
        $model = new ClassWithNotifications($persistence);
        $model->save();
        $res = $model->getNotificationByType('SOMETYPE');
        self::assertInstanceOf(Notification::class, $res);
        self::assertNull($model->getNotificationByType('SOMENONEXISTANTTYPE'));
    }

    public function testCreateNotificationUpdatesExisting() {
        $persistence = $this->_getSqliteWithMigrations();
        $model = new ClassWithNotifications($persistence);
        $model->save();
        $this->callProtected($model, 'createNotification', 'SOMETYPE', 'Blabla', [], 3);
        self::assertCount(1, $model->ref(Notification::class));
        self::assertEquals(3, $model->getMaxNotificationLevel());
    }

    public function testDeleteNotification() {
        $persistence = $this->_getSqliteWithMigrations();
        $model = new ClassWithNotifications($persistence);
        $model->save();
        $this->callProtected($model, 'deleteNotification', 'SOMETYPE');
        self::assertCount(0, $model->ref(Notification::class));
    }

    public function testFieldsArrayAsAdditionalArg() {
        $persistence = $this->_getSqliteWithMigrations();
        $model = new ClassWithNotifications($persistence);
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
        $model = new ClassWithNotifications($persistence);
        $model->save();
        $model2 = new ClassWithNotifications($persistence);
        $model2->load($model->get($model->id_field));
        $model2->loadNotifications();

        self::assertCount(1, $model2->ref(Notification::class));
    }

    public function testCreateNotificationForField() {
        $persistence = $this->_getSqliteWithMigrations();
        $model = new ClassWithNotifications($persistence);
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
        $model = new ClassWithNotifications($persistence);
        $model->save();
        $model->deleteAllNotifications();
        self::assertCount(0, $model->ref(Notification::class));
        $this->callProtected($model, 'createNotificationIfFieldEmpty', 'name');
        self::assertCount(1, $model->ref(Notification::class));
        $model->set('name', 'somevalue');
        $this->callProtected($model, 'createNotificationIfFieldEmpty', 'name');
        self::assertCount(0, $model->ref(Notification::class));
    }

    protected function _getSqliteWithMigrations(): Persistence
    {
        $persistence = Persistence::connect('sqlite::memory:');
        $model1 = new ClassWithNotifications($persistence);
        Migration::of($model1)->drop()->create();
        $notification = new Notification($persistence);
        Migration::of($notification)->drop()->create();

        return $persistence;
    }
}
