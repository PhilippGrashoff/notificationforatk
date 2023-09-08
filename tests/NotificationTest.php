<?php

declare(strict_types=1);

namespace PhilippR\Atk4\Notification\tests;

use atkextendedtestcase\TestCase;
use PhilippR\Atk4\Notification\Notification;


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
