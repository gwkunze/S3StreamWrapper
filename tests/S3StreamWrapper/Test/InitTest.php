<?php
/**
 * Copyright (c) 2013 Gijs Kunze
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace S3StreamWrapper\Test;

use S3StreamWrapper\S3StreamWrapper;

class InitTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        S3StreamWrapper::register();

        $options = array();

        $options = array_merge($GLOBALS['S3_TESTDATA'], $options);

        stream_context_set_default(array('s3' => $options));
    }

    protected function tearDown()
    {
        S3StreamWrapper::unregister();
    }


    public function testRegister() {
        $this->assertTrue(in_array("s3", stream_get_wrappers()), "S3 Stream Wrapper is registered");
    }

    public function testUnregister() {
        $this->assertTrue(in_array("s3", stream_get_wrappers()), "S3 Stream Wrapper is registered");
        S3StreamWrapper::unregister();
        $this->assertFalse(in_array("s3", stream_get_wrappers()), "S3 Stream Wrapper is unregistered");
    }

    public function testSetCustomClass() {
        $this->assertEquals("Aws\\S3\\S3Client", S3StreamWrapper::getClientClass());

        S3StreamWrapper::setClientClass("Foo\\Bar");

        $this->assertEquals("Foo\\Bar", S3StreamWrapper::getClientClass());

        S3StreamWrapper::setClientClass();

        $this->assertEquals("Aws\\S3\\S3Client", S3StreamWrapper::getClientClass());
    }

}
