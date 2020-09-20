<?php

declare(strict_types=1);

namespace notificationforatk\tests;

use atk4\core\AtkPhpunit\TestCase;


class NotificationTest extends TestCase {

    /*
     *
     */
    public function testTranslateType() {
        $n = new \EOO\Data\Notification(self::$app->db);
        $n->set('value', 'MUGGU');
        $n->set('model_class', 'Duggu');
        $this->assertEquals('MUGGU', $n->translateType());
        $n->set('model_class', 'EOO\\Data\\GroupOrigin');
        $n->set('model_id', 1);
        $this->assertEquals('MUGGU', $n->translateType());
        $n->set('value', 'NO_NAME');
        $this->assertEquals('Kein Name angegeben.', $n->translateType());
    }
}
