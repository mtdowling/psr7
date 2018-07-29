<?php
namespace GuzzleHttp\Tests\Psr7;

use PHPUnit\Framework\TestCase;

abstract class BaseTest extends TestCase
{
    /**
     * Make sure expectException always exists, even on PHPUnit 4
     * @param string      $exception
     * @param string|null $message
     */
    public function expectException($exception, $message = null)
    {
        if (method_exists($this, 'setExpectedException')) {
            $this->setExpectedException($exception, $message);
        } else {
            parent::expectException($exception);
            if (null !== $message) {
                $this->expectExceptionMessage($message);
            }
        }
    }
}
