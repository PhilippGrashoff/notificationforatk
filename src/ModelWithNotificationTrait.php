<?php

declare(strict_types=1);

namespace notificationforatk;

use atk4\core\AppScopeTrait;
use atk4\data\Model;
use atk4\data\Reference;
use traitsforatkdata\ModelWithAppTrait;


trait ModelWithNotificationTrait
{

    use ModelWithAppTrait;

    protected $notifications = [];
    protected $notificationsLoaded = false;


    protected function addNotificationReferenceAndHooks(): Reference\HasMany
    {
        $ref = $this->hasMany(
            Notification::class,
            [
                function () {
                    return (new Notification($this->persistence, ['parentObject' => $this]))->addCondition(
                        'model_class',
                        get_class($this)
                    );
                },
                'their_field' => 'model_id'
            ]
        );

        $this->onHook(
            Model::HOOK_AFTER_SAVE,
            function (self $model, $isUpdate) {
                $model->checkNotifications();
            }
        );

        $this->onHook(
            Model::HOOK_AFTER_LOAD,
            function (self $model) {
                $model->resetLoadedNotifications();
            }
        );

        return $ref;
    }

    final public function checkNotifications()
    {
        if($this->_checkSkipNotifications()) {
            $this->_checkNotifications();
        }
    }

    /**
     * implement in child classes
     * @codeCoverageIgnore
     */
    protected function _checkNotifications(): void{}

    /**
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

        //check if notification already exists
        foreach ($this->notifications as $key => $notification) {
            //notification found
            if (
                $notification->get('value') === $type
                && !array_diff($fields, $notification->get('field'))
            ) {
                //update level if neccessary
                if ($notification->get('level') !== $level) {
                    $notification->set('level', $level);
                    $notification->save();

                    $this->notifications[$key] = clone $notification;
                }

                return $notification;
            }
        }

        //create notification if it does not exist already
        $newNotification = new Notification($this->persistence, ['parentObject' => $this]);
        $newNotification->set('value', $type);
        $newNotification->set('message', $message);
        $newNotification->set('level', $level);
        $newNotification->set('field', $fields);
        $newNotification->set('extra_data', $extra_data);
        $newNotification->save();
        $this->notifications[$newNotification->get('id')] = clone $newNotification;

        return $newNotification;
    }

    protected function deleteNotification(string $type, array $fields = []): void
    {
        $this->loadNotifications();

        foreach ($this->notifications as $key => $notification) {
            if (
                $notification->get('value') === $type
                && (count($fields) ? !array_diff($fields, $notification->get('field')) : true)
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
     * useful for writing tests to check if a certain notification was created
     */
    public function getNotificationByType(string $type): ?Notification
    {
        $this->loadNotifications();

        foreach ($this->notifications as $notification) {
            if ($notification->get('value') == $type) {
                return clone $notification;
            }
        }
        return null;
    }

    protected function createNotificationIfFieldEmpty(string $field, int $level = 2, string $message = ''): void
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

    protected function deleteNotificationForField(string $field)
    {
        $this->deleteNotification('NO_' . strtoupper($field));
    }

    public function loadNotifications(): void
    {
        if (
            $this->notificationsLoaded
            || !$this->_checkSkipNotifications()
        ) {
            return;
        }

        $this->notifications = [];

        foreach ($this->ref(Notification::class) as $notification) {
            $this->notifications[$notification->get('id')] = clone $notification;
        }
        $this->notificationsLoaded = true;
    }

    public function resetLoadedNotifications()
    {
        $this->notificationsLoaded = false;
        $this->notifications = [];
    }


    protected function _checkSkipNotifications(): bool
    {
        //add possibility to skip auditing App-wide, e.g. to speed up tests
        if (
            isset($this->persistence->app->createNotifications)
            && !$this->persistence->app->createNotifications
        ) {
            return false;
        }

        return true;
    }

    public function addMaxNotificationLevelExpression()
    {
        $this->addExpression(
            'max_notification_level',
            [
                $this->refLink(Notification::class)
                    ->addCondition('deactivated', '!=', 1)
                    ->action('fx0', ['max', 'level']),
                'type' => 'integer',
                'default' => 0
            ]
        );

        return $this;
    }
}