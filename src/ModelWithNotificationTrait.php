<?php

declare(strict_types=1);

namespace PhilippR\Atk4\Notification;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Reference;

/**
 * @extends Model<Model>
 */
trait ModelWithNotificationTrait
{
    /** @var array<int,Notification> $notifications */
    protected array $notifications = [];

    /**
     * @var bool $notificationsLoaded Indicates if the notifications for this Entity were already loaded to
     * $notifications array
     */
    protected bool $notificationsLoaded = false;

    /**
     * Use this method to add a Notification reference to a Model using this trait
     *
     * @return Reference\HasMany
     */
    protected function addNotificationReferenceAndHooks(): Reference\HasMany
    {
        $ref = $this->hasMany(
            Notification::class,
            [
                'model' => function () {
                    return (new Notification($this->getPersistence()))
                        ->addCondition('model_class', '=', get_class($this))
                        ->addCondition('model_id', '=', $this->action('field', [$this->idField]));
                },
                'theirField' => 'model_id'
            ]
        );

        //After saving the entity, re-check the notifications with new values
        $this->onHook(
            Model::HOOK_AFTER_SAVE,
            function (self $entity) {
                $entity->checkNotifications();
            }
        );

        //Needed when iterating over a Model and doing something with the notifications of each entity
        $this->onHook(
            Model::HOOK_AFTER_LOAD,
            function (self $model) {
                $model->resetLoadedNotifications();
            }
        );

        return $ref;
    }

    /**
     * this method checks if notifications should be calculated at all. If so, it triggers the re-check of all notifications
     * @return void
     */
    final public function checkNotifications(): void
    {
        if ($this->_checkSkipNotifications()) {
            $this->_checkNotifications();
        }
    }

    /**
     * Can be used to disable notification creation app-wide, e.g. to speed up tests
     *
     * @return bool
     */
    protected function _checkSkipNotifications(): bool
    {
        if (
            isset($_ENV['createNotifications'])
            && $_ENV['createNotifications'] === false
        ) {
            return false;
        }

        return true;
    }

    /**
     * implement in child classes. In here, the logic of the notification calculation is stored. This means checking
     * values, set references or some other logic you want to implement.
     *
     * @return void
     */
    protected function _checkNotifications(): void
    {
    }

    /**
     *  First checks if the very same notification already exists. if not it creates the new Notification
     *
     * @param string $type
     * @param string $message
     * @param string|null $field
     * @param int $level
     * @param array<string, mixed> $extra_data
     * @return Notification
     * @throws Exception
     * @throws \Atk4\Core\Exception
     */
    protected function createNotification(
        string $type,
        string $message,
        ?string $field = null,
        int $level = 1,
        array $extra_data = []
    ): Notification {

        $this->loadNotifications();

        //check if notification already exists
        foreach ($this->notifications as $key => $notification) {
            //notification found
            if (
                $notification->get('value') === $type
                && $field === $notification->get('field')
            ) {
                //update level if necessary
                if ($notification->get('level') !== $level) {
                    $notification->set('level', $level);
                    $notification->save();

                    $this->notifications[$key] = clone $notification;
                }

                return $notification;
            }
        }

        //create notification if it does not exist already
        $newNotification = (new Notification($this->getPersistence()))->createEntity();
        $newNotification->setParentEntity($this);
        $newNotification->set('value', $type);
        $newNotification->set('message', $message);
        $newNotification->set('level', $level);
        $newNotification->set('field', $field);
        $newNotification->set('extra_data', $extra_data);
        $newNotification->save();
        $this->notifications[$newNotification->getId()] = clone $newNotification;

        return $newNotification;
    }

    /**
     * deletes a notification based on its identifier and the field it applies to.
     *
     * @param string $type
     * @param string|null $field
     * @return void
     * @throws Exception
     */
    protected function deleteNotification(string $type, ?string $field = null): void
    {
        $this->loadNotifications();

        foreach ($this->notifications as $key => $notification) {
            if (
                $notification->get('value') === $type
                && $field === $notification->get('field')
            ) {
                $notification->delete();
                unset($this->notifications[$key]);
            }
        }
    }

    /**
     * returns the maximum level of all notifications which are active
     *
     * @return int
     * @throws Exception
     */
    public function getMaxNotificationLevel(): int
    {
        $this->loadNotifications();

        $level = 0;
        foreach ($this->notifications as $notification) {
            if (
                !$notification->get('deactivated')
                && $notification->get('level') > $level
            ) {
                $level = $notification->get('level');
            }
        }

        return $level;
    }

    /**
     * useful for writing tests to check if a certain notification was created
     *
     * @param string $type
     * @return Notification|null
     * @throws Exception
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

    /**
     * A shortcut to check if a field has a value set or not. This is something very commonly used, so this short
     * function is available for this recurring task
     *
     * @param string $field
     * @param int $level
     * @param string $message
     * @return void
     * @throws Exception
     * @throws \Atk4\Core\Exception
     */
    protected function createNotificationIfFieldEmpty(string $field, int $level = 2, string $message = ''): void
    {
        $this->loadNotifications();

        if (empty($this->get($field))) {
            $this->createNotification(
                'NO_' . strtoupper($field),
                $message ?: 'The field ' . $this->getField($field)->getCaption() . ' is empty.',
                $field,
                $level
            );
        } else {
            $this->deleteNotification('NO_' . strtoupper($field), $field);
        }
    }

    /**
     * Shortcut function for deleting a notification that a field has no value set
     *
     * @param string $field
     * @return void
     * @throws Exception
     */
    protected function deleteNotificationForField(string $field): void
    {
        $this->loadNotifications();

        $this->deleteNotification('NO_' . strtoupper($field), $field);
    }

    /**
     * For Performance saving, all notifications are loaded into an array. This way, only one request has to be made to
     * persistence in order to check if all existing notifications are still valid. The function checks if the notifications
     * are already loaded to the array. If not, it loads them from persistence.
     *
     * @return void
     * @throws Exception
     */
    public function loadNotifications(): void
    {
        $this->assertIsLoaded();

        if (
            $this->notificationsLoaded
            || !$this->_checkSkipNotifications()
        ) {
            return;
        }

        $this->notifications = [];

        foreach ($this->ref(Notification::class) as $notification) {
            $this->notifications[$notification->getId()] = clone $notification;
        }
        $this->notificationsLoaded = true;
    }

    /**
     * Can be used to reset the loaded notifications. It will cause a reload of all notifications for the current entity
     * the next time some notification action is performed.
     *
     * @return void
     */
    public function resetLoadedNotifications(): void
    {
        $this->notificationsLoaded = false;
        $this->notifications = [];
    }


    /**
     * Used to add an expression for the maximum notification level to the Model.
     *
     * @return Model
     */
    public function addMaxNotificationLevelExpression(): static
    {
        $this->addExpression(
            'max_notification_level',
            [
                'expr' => $this->refLink(Notification::class)
                    ->addCondition('deactivated', '!=', true)
                    ->action('fx0', ['max', 'level']),
                'type' => 'integer',
                'default' => 0
            ]
        );

        return $this;
    }

    /**
     * returns an array containing all active notifications of the model.
     * Format:  [field name => notification level]
     *
     * @return array<string, int>
     * @throws Exception
     */
    public function exportNotificationFieldLevels(): array
    {
        $return = [];
        $this->loadNotifications();
        foreach ($this->notifications as $notification) {
            if (
                $notification->get('deactivated') === true
                || !$notification->get('field')
            ) {
                continue;
            }
            $return[$notification->get('field')] = $notification->get('level');
        }


        return $return;
    }
}