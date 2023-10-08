<?php

declare(strict_types=1);

namespace PhilippR\Atk4\Notification\Tests\TestClasses;

use Atk4\Data\Model;
use PhilippR\Atk4\Notification\Notification;
use PhilippR\Atk4\Notification\NotificationTrait;

class ModelWithNotificationTrait extends Model
{

    use NotificationTrait;

    public $table = 'sometable';

    protected function init(): void
    {
        parent::init();
        $this->addField('name');
        $this->notificationControllerClass = DemoNotificationController::class;

        $this->addNotificationReferenceAndHooks();
    }
}
