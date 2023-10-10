<?php

namespace PhilippR\Atk4\Notification\Tests\TestClasses;

use PhilippR\Atk4\Notification\Notification;
use PhilippR\Atk4\Notification\NotificationController;

class DemoNotificationController extends NotificationController
{
    public string $notificationType = 'SOMETYPE';
    public string $notificationMessage = 'SomeMessage';


    public function checkNotificationsAfterSave(): void
    {
        if (!$this->skipNotificationCreation()) {
            $this->createNotification($this->notificationType, $this->notificationMessage);
        }
    }

    public function checkNotificationsAfterDelete(): void
    {
        if (!$this->skipNotificationCreation()) {
            //this code is rather stupid and only there to test if the afterDelete implementation is triggered
            $_ENV['checkNotificationsAfterDelete'] = true;
        }
    }

    public function createLevel3Notification(): Notification
    {
        return $this->createNotification('LEVEL3', 'BLABLA', null, 3);
    }

    public function createLevel2Notification(): Notification
    {
        return $this->createNotification('LEVEL2', 'BLABLA', null, 2);
    }

    public function createLevel1Notification(): Notification
    {
        return $this->createNotification('LEVEL1', 'BLABLA', null, 1);
    }

    public function createLevelNotificationWithField(string $fieldName): Notification
    {
        return $this->createNotification('SOMEOTHERNOTI', 'BLABLA', $fieldName, 2);
    }

    public function deleteAllNotifications(): void
    {
        foreach ($this->entity->ref(Notification::class) as $notification) {
            $notification->delete();
        }
        $this->notificationsLoaded = false;
        $this->notifications = [];
    }
}