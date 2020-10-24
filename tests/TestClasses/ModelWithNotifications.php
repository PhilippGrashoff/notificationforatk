<?php

declare(strict_types=1);

namespace notificationforatk\tests\TestClasses;

use atk4\data\Model;
use notificationforatk\ModelWithNotificationTrait;
use notificationforatk\Notification;

class ModelWithNotifications extends Model {

    use ModelWithNotificationTrait;

    public $notificationType = 'SOMETYPE';
    public $notificationMessage = 'SomeMessage';

    public $table = 'sometable';

    protected function init(): void
    {
        parent::init();
        $this->addField('name');

        $this->addNotificationReferenceAndHooks();
    }

    protected function _checkNotifications() {
        $this->createNotification($this->notificationType, $this->notificationMessage);
    }

    public function createLevel3Notification() {
        return $this->createNotification('LEVEL3', 'BLABLA', [], 3);
    }

    public function createLevel2Notification() {
        return $this->createNotification('LEVEL2', 'BLABLA', [], 2);
    }

    public function createLevel1Notification() {
        return $this->createNotification('LEVEL1', 'BLABLA', [], 1);
    }

    public function deleteAllNotifications() {
        foreach($this->ref(Notification::class) as $notification) {
            $notification->delete();
        }
        $this->notificationsLoaded = false;
        $this->notifications = [];
    }
}
