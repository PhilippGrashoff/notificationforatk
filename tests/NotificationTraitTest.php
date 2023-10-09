<?php

declare(strict_types=1);

namespace PhilippR\Atk4\Notification\Tests;

use Atk4\Data\Persistence\Sql;
use Atk4\Data\Reference\HasMany;
use Atk4\Data\Schema\TestCase;
use PhilippR\Atk4\Notification\Notification;
use PhilippR\Atk4\Notification\NotificationController;
use PhilippR\Atk4\Notification\Tests\TestClasses\DemoNotificationController;
use PhilippR\Atk4\Notification\Tests\TestClasses\ModelWithNotificationTrait;

class NotificationTraitTest extends TestCase
{


    protected function setUp(): void
    {
        parent::setUp();
        $this->db = new Sql('sqlite::memory:');
        $this->createMigrator(new Notification($this->db))->create();
        $this->createMigrator(new ModelWithNotificationTrait($this->db))->create();
    }

    public function testReferenceSetInInit(): void
    {
        $model = new ModelWithNotificationTrait($this->db);
        self::assertInstanceOf(
            HasMany::class,
            $model->getReference(Notification::class)
        );
    }

    public function testAfterSaveHook(): void
    {
        $model = (new ModelWithNotificationTrait($this->db))->createEntity();
        $model->save();
        self::assertCount(
            1,
            $model->ref(Notification::class)
        );
    }

    public function testAddMaxNotificationLevelExpression(): void
    {
        $model = new ModelWithNotificationTrait($this->db);
        $model->addMaxNotificationLevelExpression();
        $entity = $model->createEntity();
        $entity->save();
        $controller = $entity->getNotificationController();
        self::assertTrue($entity->hasField('max_notification_level'));
        self::assertSame(
            0,
            $entity->get('max_notification_level')
        );

        $controller->createLevel1Notification();
        $entity->reload();
        self::assertSame(
            1,
            $entity->get('max_notification_level')
        );

        $controller->createLevel2Notification();
        $entity->reload();
        self::assertSame(
            2,
            $entity->get('max_notification_level')
        );

        $level3 = $controller->createLevel3Notification();
        $entity->reload();
        self::assertSame(
            3,
            $entity->get('max_notification_level')
        );

        $level3->set('deactivated', 1);
        $level3->save();
        $entity->reload();
        self::assertSame(
            2,
            $entity->get('max_notification_level')
        );
    }

}
