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


    /*
     *
     */
    public function testToursOverlapRender() {
        $t1 = new \EOO\Data\Tour(self::$app->db);
        $t1->set('start_date', '2020-10-10');
        $t1->set('end_date', '2020-10-10');
        $t1->set('start_time', '10:00');
        $t1->set('end_time', '14:00');
        $t1->save();
                
        $t2 = new \EOO\Data\Tour(self::$app->db);
        $t2->set('start_date', '2020-10-10');
        $t2->set('end_date', '2020-10-10');
        $t2->set('start_time', '12:00');
        $t2->set('end_time', '16:00');
        $t2->save();

        $g = new \EOO\Data\Guide(self::$app->db);
        $g->save();
        $g->addTour($t1);
        $g->addTour($t2);

        $n = $g->ref('Notification');
        $n->addCondition('value', 'TOURS_OVERLAP');
        $n->loadAny();

        $expected = 'Der Guide ist vom 10.10.2020 12:00 bis zum 10.10.2020 14:00 für mehrere gleichzeitige Touren eingetragen.';
        self::assertEquals($expected, $n->translateType());
    }


    /**
     *
     */
    public function testToursCCExportErrorRender() {
        $n = new \EOO\Data\Notification(self::$app->db);
        $n->set('model_class', 'EOO\\Data\\Tour');
        $n->set('value', 'CANYONING_CALENDAR_EXPORT_ERROR');
        $n->set('extra_data', ['error_message' => 'BlaDu']);

        $expected = 'Ein Fehler beim Export zum Canyoning Kalender ist aufgetreten: BlaDu';
        self::assertEquals($expected, $n->translateType());
    }
}
