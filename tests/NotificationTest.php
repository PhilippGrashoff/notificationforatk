<?php

declare(strict_types=1);

namespace notificationforatk\tests;

use atk4\core\AtkPhpunit\TestCase;
use atk4\data\Persistence;
use notificationforatk\Notification;


class NotificationTest extends TestCase {

    public function testInit() {
        $notification = new Notification(new Persistence\Array_());
        self::assertTrue($notification->hasField('message'));
    }
}
