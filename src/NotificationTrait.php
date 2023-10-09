<?php

declare(strict_types=1);

namespace PhilippR\Atk4\Notification;

use Atk4\Data\Model;
use Atk4\Data\Reference;

/**
 * @extends Model<Model>
 */
trait NotificationTrait
{

    /** @var class-string<NotificationController> $notificationControllerClass */
    protected string $notificationControllerClass = NotificationController::class;

    protected ?NotificationController $notificationController = null;

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
                $entity->getNotificationController()->recheckNotifications();
            }
        );

        return $ref;
    }

    /**
     * @return NotificationController
     */
    public function getNotificationController(): NotificationController
    {
        if (!$this->notificationController) {
            $this->notificationController = new $this->notificationControllerClass($this);
        }
        return $this->notificationController;
    }

    /**
     * Used to add an expression for the maximum notification level to the Model.
     *
     * @return NotificationTrait
     */
    public function addMaxNotificationLevelExpression(): static
    {
        $this->assertIsModel();
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
}