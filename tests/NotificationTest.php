<?php

declare(strict_types=1);

namespace PhilippR\Atk4\Notification\Tests;

use Atk4\Data\Persistence\Sql;
use Atk4\Data\Schema\TestCase;
use PhilippR\Atk4\Notification\Notification;


class NotificationTest extends TestCase
{


    protected function setUp(): void
    {
        parent::setUp();
        $this->db = new Sql('sqlite::memory:');
        $this->createMigrator(new Notification($this->db))->create();
    }

    public function testInit(): void
    {
        $notification = new Notification($this->db);
        self::assertTrue($notification->hasField('message'));
    }
}
