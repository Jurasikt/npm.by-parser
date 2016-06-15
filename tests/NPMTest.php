<?php

class NPMTest extends PHPUnit_Framework_TestCase
{

    private $npm;

    public function setUp()
    {
        $this->npm = new NPMParser();
    }

    public function testStations()
    {
        $data = $this->npm->generateStation();
        $this->assertArrayHasKey(10, $data);
    }

    public function testGetPlaces()
    {
        $places = $this->npm->getPlaces(41, 39, '30-04-2016');
        $this->assertContains(array('time' => '11:21', 'count' => 6), $places);
    }

    public function testFreePlaces()
    {
        $result = $this->npm->getFreePlaces(41, 39, '30-04-2016', array('15'));
        $this->assertSame(array('15' => array('time' => '15:21', 'count' => 1)), $result);
    }

    public function testNotFreePlaces()
    {
        $result = $this->npm->getFreePlaces(41, 39, '30-04-2016', array('17'));
        $this->assertSame(array(), $result);
    }

    public function testOath()
    {
        $sid = $this->npm->oath("test", "test");
        $this->assertArrayHasKey('SID', $sid);
    }

    public function testCheckSid()
    {
        $sid = $this->npm->oath("sddfsdfsdfsdf", "fsdfsdfsdfs")['SID'];
        $result = $this->npm->checkSid($sid);
        $this->assertSame(false, $result);
    }

    

}