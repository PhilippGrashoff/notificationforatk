<?php

declare(strict_types=1);

namespace notificationforatk\tests;

use atk4\core\AtkPhpunit\TestCase;

class ModelWithNotificationTraitTest extends TestCase {

    /*
     *
     */
    public function testCheckNotificationForEmail() {
        $g = new \EOO\Data\Group(self::$app->db);
        $g->save();
        $g->checkNotificationForEmail(3);
        $this->assertTrue($this->_notificationFound($g, 'NO_EMAIL'));
        $n = $g->getNotificationByType('NO_EMAIL');
        $this->assertEquals($n->get('level'), 3);
        $g->addEmail('test1@easyoutdooroffice.com');
        $g->checkNotificationForEmail(3);
        $this->assertFalse($this->_notificationFound($g, 'NO_EMAIL'));
    }


    /*
     *
     */
    public function testCheckNotificationForPhone() {
        $g = new \EOO\Data\Group(self::$app->db);
        $g->save();
        $g->checkNotificationForPhone(3);
        $this->assertTrue($this->_notificationFound($g, 'NO_PHONE'));
        $n = $g->getNotificationByType('NO_PHONE');
        $this->assertEquals($n->get('level'), 3);
        $g->addPhone('12345');
        $g->checkNotificationForPhone(3);
        $this->assertFalse($this->_notificationFound($g, 'NO_PHONE'));
    }


    /*
     *
     */
    public function testCheckNotificationForAddress() {
        $g = new \EOO\Data\Group(self::$app->db);
        $g->save();
        $g->checkNotificationForAddress(3);
        $this->assertTrue($this->_notificationFound($g, 'NO_ADDRESS'));
        $n = $g->getNotificationByType('NO_ADDRESS');
        $this->assertEquals($n->get('level'), 3);
        $g->addAddress('test1@easyoutdooroffice.com');
        $g->checkNotificationForAddress(3);
        $this->assertFalse($this->_notificationFound($g, 'NO_ADDRESS'));
    }


    /*
     *
     */
    public function testgetMaxNotificationLevel()
    {
        $g = new \EOO\Data\Tour(self::$app->db);
        $g->save();
        $this->assertEquals(3, $g->getMaxNotificationLevel());
    }


    /*
     *
     */
    public function testgetNotificationArray()
    {
        $g = new \EOO\Data\Group(self::$app->db);
        $g->save();
        $this->assertTrue(is_array($g->exportNotificationArray()));
    }

    /*
     *
     */
    public function testgetNotificationByType() {
        $g = new \EOO\Data\Group(self::$app->db);
        $g->save();
        $g->notificationsLoaded = false;
        $res = $g->getNotificationByType('NO_NAME');
        self::assertInstanceOf(\EOO\Data\Notification::class, $res);
        $res = $g->getNotificationByType('FGGSFDFF');
        self::assertNull($res);
    }
}
