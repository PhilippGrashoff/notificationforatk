<?php declare(strict_types=1);

namespace PhilippR\Atk4\Notification;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Throwable;

class NotificationController
{

    protected Model $entity;

    /** @var array<int,Notification> $notifications */
    protected array $notifications = [];

    /**
     * @var bool $notificationsLoaded Indicates if the notifications for this entity were already loaded to
     * $notifications array
     */
    protected bool $notificationsLoaded = false;

    public function __construct(Model $entity)
    {
        $this->entity = $entity;
    }

    /**
     * implement logic for Notification checks in child classes.
     * In here, the logic of the notification calculation is stored. This means checking
     * values, set references or some other logic you want to implement.
     */
    public function recheckNotifications(): void
    {
        if (!$this->skipNotificationCreation()) {
            //TODO
        }
    }

    /**
     * Can be used to disable notification creation, e.g. to speed up tests
     *
     * @return bool
     */
    protected function skipNotificationCreation(): bool
    {
        if (
            isset($_ENV['createNotifications'])
            && $_ENV['createNotifications'] === false
        ) {
            return true;
        }

        return false;
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
     * @throws \Atk4\Core\Exception|Throwable
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
                $notification->get('type') === $type
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
        $newNotification = (new Notification($this->entity->getModel()->getPersistence()))->createEntity();
        $newNotification->setParentEntity($this->entity);
        $newNotification->set('type', $type);
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
     * @throws Throwable
     */
    protected function deleteNotification(string $type, ?string $field = null): void
    {
        $this->loadNotifications();

        foreach ($this->notifications as $key => $notification) {
            if (
                $notification->get('type') === $type
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
     * @throws Throwable
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
     * @throws Throwable
     */
    public function getNotificationByType(string $type): ?Notification
    {
        $this->loadNotifications();

        foreach ($this->notifications as $notification) {
            if ($notification->get('type') == $type) {
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
     * @throws \Atk4\Core\Exception|Throwable
     */
    protected function createNotificationIfFieldEmpty(string $field, int $level = 2, string $message = ''): void
    {
        $this->loadNotifications();

        if (empty($this->entity->get($field))) {
            $this->createNotification(
                'NO_' . strtoupper($field),
                $message ?: 'The field ' . $this->entity->getField($field)->getCaption() . ' is empty.',
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
     * @throws Throwable
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
     * @throws Exception|Throwable
     */
    public function loadNotifications(): void
    {
        $this->entity->assertIsLoaded();

        if (
            $this->notificationsLoaded
            || $this->skipNotificationCreation()
        ) {
            return;
        }

        $this->notifications = [];

        foreach ($this->entity->ref(Notification::class) as $notification) {
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
        $this->entity->addExpression(
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
     * @throws Throwable
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