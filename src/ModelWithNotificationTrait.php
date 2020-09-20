<?php

declare(strict_types=1);

namespace notificationforatk;

use atk4\data\Model;


trait ModelWithNotificationTrait
{

    protected $notifications = [];
    protected $notificationsLoaded = false;


    protected function _addCheckNotificationHooks()
    {
        $this->onHook(
            Model::HOOK_BEFORE_SAVE,
            function (Model $model, $is_update) {
                if (!$is_update) {
                    return;
                }
                $model->checkNotifications();
            }
        );

        $this->onHook(
            Model::HOOK_AFTER_SAVE,
            function ($model, $is_update) {
                if ($is_update) {
                    return;
                }
                $model->checkNotifications();
            }
        );
    }


    /*
     * checks if the very same notification already exists
     * if not it creates the new Notification
     */
    protected function createNotification(
        string $type,
        string $message,
        array $fields = [],
        int $level = 1,
        array $extra_data = []
    ): Notification {
        $this->loadNotifications();

        $notification_exists = false;

        //check if notification already exists
        foreach ($this->notifications as $notification) {
            //notification found
            if (
                $notification->get('value') === $type
                && ($fields ? !array_diff($fields, $notification->get('field')) : true)
            ) {
                //update level if neccessary
                if ($notification->get('level') !== $level) {
                    $notification->set('level', $level);
                    $notification->save();

                    return $notification;
                }
            }
        }

        //create notification if it does not exist already
        $new_notification = new Notification($this->persistence, ['parentObject' => $this]);
        $new_notification->set('value', $type);
        $new_notification->set('message', $message);
        $new_notification->set('level', $level);
        $new_notification->set('field', $fields);
        $new_notification->set('extra_data', $extra_data);
        $new_notification->save();
        $this->notifications[$new_notification->get('id')] = $new_notification;

        return $new_notification;
    }

    protected function deleteNotification(string $type, string $field = ''): void
    {
        $this->loadNotifications();

        foreach ($this->notifications as $key => $notification) {
            if (
                $notification->get('value') === $type
                && (count($field) > 0 ? in_array($field, $notification->get('field')) : true)
            ) {
                $notification->delete();
                unset($this->notifications[$key]);
            }
        }
    }

    public function getMaxNotificationLevel(): int
    {
        $this->loadNotifications();

        $level = 0;
        foreach ($this->notifications as $n) {
            if (
                !$n->get('deactivated')
                && $n->get('level') > $level
            ) {
                $level = $n->get('level');
            }
        }

        return $level;
    }

    /**
     * returns an array containing all active notifications of the model.
     * Format: $return[] = ['id' => $notification fieldname, 'level' => notification level]
     * TODO: This should be in EOO UI, move there when approprioate
     */
    public function exportNotificationArray(): array
    {
        $return = [];
        $this->loadNotifications();

        foreach ($this->notifications as $notification) {
            if ($notification->get('deactivated') != 1) {
                foreach ($notification->get('field') as $fieldName) {
                    $return[] = ['id' => $fieldName, 'level' => $notification->get('level')];
                }
            }
        }
        return $return;
    }

    /**
     * useful for writing tests to check if a certain notification was created
     */
    public function getNotificationByType(string $type): ?Notification
    {
        if (!$this->notificationsLoaded) {
            $this->loadNotifications();
        }
        foreach ($this->notifications as $notification) {
            if ($notification->get('value') == $type) {
                return clone $notification;
            }
        }
        return null;
    }

    public function checkNotificationForField(string $field, int $level = 2)
    {
        $this->createNotificationIfFieldEmpty($field, $level);
        trigger_error(
            'checkNotificationForField is deprecated, use createNotificationIfFieldEmpty instead.',
            E_USER_DEPRECATED
        );
    }

    public function createNotificationIfFieldEmpty(string $field, int $level = 2, string $message = ''): void
    {
        if (empty($this->get($field))) {
            $this->createNotification(
                'NO_' . strtoupper($field),
                $message ?: 'Das Feld ' . $this->getField($field)->getCaption() . ' ist leer.',
                [$field],
                $level
            );
        } else {
            $this->deleteNotification('NO_' . strtoupper($field));
        }
    }

    public function deleteNotificationForField(string $field)
    {
        $this->deleteNotification('NO_' . strtoupper($field));
    }

    public function loadNotifications(): void
    {
        if ($this->notificationsLoaded) {
            return;
        }

        $this->notifications = [];

        foreach ($this->ref(Notification::class) as $notification) {
            $this->notifications[$notification->get('id')] = clone $notification;
        }
        $this->notificationsLoaded = true;
    }
}
