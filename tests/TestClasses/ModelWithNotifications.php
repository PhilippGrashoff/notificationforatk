<?php

declare(strict_types=1);

namespace notificationforatk\tests\TestClasses;

use Atk4\Data\Model;
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

    public function createLevelNotificationWithTwoFields() {
        return $this->createNotification('SOMEOTHER', 'BLABLA', ['field1', 'field2'], 1);
    }
    public function createLevelNotificationWithOneField(string $fieldName) {
        return $this->createNotification('SOMEOTHERNOTI', 'BLABLA', [$fieldName], 2);
    }

    public function deleteAllNotifications() {
        foreach($this->ref(Notification::class) as $notification) {
            $notification->delete();
        }
        $this->notificationsLoaded = false;
        $this->notifications = [];
    }
}
