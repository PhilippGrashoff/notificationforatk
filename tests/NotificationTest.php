<?php

declare(strict_types=1);

namespace notificationforatk\tests;

use atkextendedtestcase\TestCase;
use notificationforatk\Notification;


class NotificationTest extends TestCase
{
    protected array $sqlitePersistenceModels = [
        Notification::class
    ];


    public function testInit(): void
    {
        $notification = new Notification($this->getSqliteTestPersistence());
        self::assertTrue($notification->hasField('message'));
    }
}
