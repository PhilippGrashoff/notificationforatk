<?php

declare(strict_types=1);

namespace PhilippR\Atk4\Notification;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Reference;

/**
 * @extends Model<Model>
 */
trait NotificationTrait
{

    /** @var class-string<NotificationController> $notificationControllerClass */
    protected string $notificationControllerClass = NotificationController::class;

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
                (new $this->notificationControllerClass($entity))->recheckNotifications();
            }
        );

        return $ref;
    }
}