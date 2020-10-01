<?php

declare(strict_types=1);

namespace notificationforatk\tests;

use traitsforatkdata\TestCase;
use notificationforatk\Notification;


class NotificationTest extends TestCase {

    protected $sqlitePersistenceModels = [
        Notification::class
    ];


    public function testInit() {
        $notification = new Notification($this->getSqliteTestPersistence());
        self::assertTrue($notification->hasField('message'));
    }
}
