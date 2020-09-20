<?php

declare(strict_types=1);

namespace notificationforatk;

use secondarymodelforatk\SecondaryModel;

class Notification extends SecondaryModel
{

    public $table = 'notification';

    public function init(): void
    {
        parent::init();

        $this->addFields(
            [
                [
                    'field',
                    'type' => 'array',
                    'serialize' => 'json'
                ],
                [
                    'level',
                    'type' => 'integer'
                ],
                [
                    'extra_data',
                    'type' => 'array',
                    'serialize' => 'json'
                ],
                [
                    'deactivated',
                    'type' => 'boolean',
                    'default' => 0
                ],
            ]
        );

        $this->setOrder(['deactivated', 'level desc']);
    }


    /*
     * Returns the String given in user_config.php if found,
     * if not the raw type.
     *
     * @return string
     */
    public function translateType()
    {
        if (!array_key_exists($this->get('model_class'), NOTIFICATION_TEXTS)) {
            return $this->get('value');
        }
        if (!array_key_exists($this->get('value'), NOTIFICATION_TEXTS[$this->get('model_class')])) {
            return $this->get('value');
        }

        //special for guide tours overlap notification
        if ($this->get('model_class') == 'EOO\\Data\Guide'
            && $this->get('value') == 'TOURS_OVERLAP') {
            $text = NOTIFICATION_TEXTS['EOO\\Data\\Guide']['TOURS_OVERLAP'];
            $text = str_replace('%start_overlap%', $this->get('extra_data')['start_overlap'], $text);
            $text = str_replace('%end_overlap%', $this->get('extra_data')['end_overlap'], $text);
            return $text;
        }

        //special for tour Canyoning Calendar Export Error
        if ($this->get('model_class') == 'EOO\\Data\Tour'
            && $this->get('value') == 'CANYONING_CALENDAR_EXPORT_ERROR') {
            $text = NOTIFICATION_TEXTS['EOO\\Data\\Tour']['CANYONING_CALENDAR_EXPORT_ERROR'];
            $text = str_replace('%error_message%', $this->get('extra_data')['error_message'], $text);
            return $text;
        }

        return NOTIFICATION_TEXTS[$this->get('model_class')][$this->get('value')];
    }
}
