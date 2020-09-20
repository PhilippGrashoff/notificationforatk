<?php

declare(strict_types=1);

namespace notificationforatk;


use atk4\data\Model;

trait ModelWithNotificationTrait {

    protected $notifications = [];
    protected $notificationsLoaded = false;


    protected function _addCheckNotificationHooks() {
        $this->onHook(
            Model::HOOK_BEFORE_SAVE,
            function(Model $model, $is_update) {
            if(!$is_update) {
                return;
            }
            $model->checkNotifications();
        });

        $this->onHook(
            Model::HOOK_AFTER_SAVE,
            function($model, $is_update) {
            if($is_update) {
                return;
            }
            $model->checkNotifications();
        });
    }


    /*
     * checks if the very same notification already exists
     * if not it creates the new Notification
     */
    protected function createNotification(string $type, $field = '', int $level = 1, array $extra_data = []) {
        if(!$this->notificationsLoaded) {
            $this->loadNotifications();
        }

        $notification_exists = false;

        if(!is_array($field)) {
            $field = ($field !== '' ? [$field] : []);
        }

        //check if notification already exists
        if($this->notifications) {
            foreach($this->notifications as $n) {
                //notification found
                if($n->get('value') === $type
                && ($field ? !array_diff($field, $n->get('field')) : true)) {
                    $notification_exists = true;
                    //update level if neccessary
                    if($n->get('level') !== $level) {
                        $n->set('level', $level);
                        $n->save();
                    }
                }
            }
        }

        //create notification if it does not exist already
        if(!$notification_exists) {
            $new_notification = new Notification($this->persistence);
            $new_notification->reload_after_save = false;
            $new_notification->set('model_class',  get_class($this));
            $new_notification->set('model_id',     $this->get('id'));
            $new_notification->set('value',         $type);
            $new_notification->set('level',        $level);
            $new_notification->set('field',        $field);
            $new_notification->set('extra_data',   $extra_data);
            $new_notification->save();
            $this->notifications[$new_notification->get('id')] = clone $new_notification;
        }
    }


    /*
     * deletes a notification if it exists
     */
    protected function deleteNotification(string $type, string $field = '') {
        if(!$this->notificationsLoaded) {
            $this->loadNotifications();
        }

        if($this->notifications) {
            foreach($this->notifications as $key => $n) {
                //notification found
                if($n->get('value') === $type && ($field !== '' ? in_array($field, $n->get('field')) : true)) {
                    $n->delete();
                    unset($this->notifications[$key]);
                }
            }
        }
    }


    /*
     * returns the maximum notification level
     */
    public function getMaxNotificationLevel():int {
        if(!$this->notificationsLoaded) {
            $this->loadNotifications();
        }
        $level = 0;
        foreach($this->notifications as $n) {
            //notification found
            if(!$n->get('deactivated') && $n->get('level') > $level) {
                $level = $n->get('level');
            }
        }

        return $level;
    }



    /*
     * returns an array containing all active notifications of the model.
     * Format: $return[] = ['id' => $notification fieldname, 'level' => notification level]
     *
     * @return array
     */
    public function exportNotificationArray():array {
        $return = [];
        if(!$this->notificationsLoaded) {
            $this->loadNotifications();
        }
        foreach ($this->notifications as $n) {
            if ($n->get('deactivated') != 1) {
                foreach ($n->get('field') as $f) {
                    $return[] = ['id' => $f, 'level' => $n->get('level')];
                }
            }
        }
        return $return;
    }


    /*
     * returns an array containing all active (not deactivated) notifications
     *
     * @return object|null
     */
    public function getNotificationByType($type) {
        if(!$this->notificationsLoaded) {
            $this->loadNotifications();
        }
        if($this->notifications) {
            foreach($this->notifications as $n) {
                if($n->get('value') == $type) {
                    return clone $n;
                }
            }
        }
        return null;
    }



    /*
     * check if a field is empty or not. If its empty, it creates a new
     * notification, otherwise deletes it
     *
     * @param string
     * @param int
     *
     * @return bool
     */

    public function checkNotificationForField($field, $level = 2) {
        if(empty($this->get($field))) {
            $this->createNotification('NO_'.strtoupper($field), $field, $level);
        }
        else {
            $this->deleteNotification('NO_'.strtoupper($field));
        }
        return true;
    }


    /*
     * delete a notification that might be created by checkNotificationForField
     */
    public function deleteNotificationForField($field) {
        $this->deleteNotification('NO_'.strtoupper($field));
    }


    /*
     * check if model has at least one non-empty Email Address referenced
     *
     * if an email_address is set but seems invalid, it creates an
     * EMAIL_INVALID notification
     *
     * @param int
     *
     * @return bool
     */

    public function checkNotificationForEmail($level = 1) {
        $email = $this->getFirstEmail();
        if(empty($email)) {
            $this->createNotification('NO_EMAIL', 'Email', $level);
        }
        else {
            $this->deleteNotification('NO_EMAIL');
        }
        return true;
    }


    /*
     * check if model has at least one non-empty Phone Number referenced
     *
     * @param int
     *
     * @return bool
     */

    public function checkNotificationForPhone($level = 1) {
        if(empty($this->getFirstPhone())) {
            $this->createNotification('NO_PHONE', 'Phone', $level);
        }
        else {
            $this->deleteNotification('NO_PHONE');
        }
        return true;
    }



    /*
     * check if model has at least one non-empty Address referenced
     *
     * @param int
     *
     * @return bool
     */

    public function checkNotificationForAddress($level = 1) {
        if(empty($this->getFirstAddress())) {
            $this->createNotification('NO_ADDRESS', 'Address', $level);
        }
        else {
            $this->deleteNotification('NO_ADDRESS');
        }
        return true;
    }


    /*
     * Loads all notifications for this object
     */
    public function loadNotifications() {
        $ns = new Notification($this->persistence);
        $ns->reload_after_save = false;
        $ns->addCondition('model_class', get_class($this));
        $ns->addCondition('model_id', $this->get('id'));

        $this->notifications = [];

        foreach($ns as $n) {
            $this->notifications[$n->get('id')] = clone $n;
        }
        $this->notificationsLoaded = true;
    }
}
