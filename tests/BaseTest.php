<?php

namespace GuzzleHttp\Tests\Psr7;

use PHPUnit\Framework\TestCase;

abstract class BaseTest extends TestCase
{
    /**
     * @param string      $exception
     * @param string|null $message
     */
    public function expectExceptionGuzzle($exception, $message = null)
    {
        if (method_exists($this, 'setExpectedException')) {
            $this->setExpectedException($exception, $message);
        } else {
            $this->expectException($exception);
            if (null !== $message) {
                $this->expectExceptionMessage($message);
            }
        }
    }

    public function expectWarningGuzzle()
    {
        if (method_exists($this, 'expectWarning')) {
            $this->expectWarning();
        } elseif (class_exists('PHPUnit\Framework\Error\Warning')) {
            $this->expectExceptionGuzzle('PHPUnit\Framework\Error\Warning');
        } else {
            $this->expectExceptionGuzzle('PHPUnit_Framework_Error_Warning');
        }
    }

    /**
     * @param string $type
     * @param mixed  $input
     */
    public function assertInternalTypeGuzzle($type, $input)
    {
        switch ($type) {
            case 'array':
                if (method_exists($this, 'assertIsArray')) {
                    self::assertIsArray($input);
                } else {
                    self::assertInternalType('array', $input);
                }
                break;
            case 'bool':
            case 'boolean':
                if (method_exists($this, 'assertIsBool')) {
                    self::assertIsBool($input);
                } else {
                    self::assertInternalType('bool', $input);
                }
                break;
            case 'double':
            case 'float':
            case 'real':
                if (method_exists($this, 'assertIsFloat')) {
                    self::assertIsFloat($input);
                } else {
                    self::assertInternalType('float', $input);
                }
                break;
            case 'int':
            case 'integer':
                if (method_exists($this, 'assertIsInt')) {
                    self::assertIsInt($input);
                } else {
                    self::assertInternalType('int', $input);
                }
                break;
            case 'numeric':
                if (method_exists($this, 'assertIsNumeric')) {
                    self::assertIsNumeric($input);
                } else {
                    self::assertInternalType('numeric', $input);
                }
                break;
            case 'object':
                if (method_exists($this, 'assertIsObject')) {
                    self::assertIsObject($input);
                } else {
                    self::assertInternalType('object', $input);
                }
                break;
            case 'resource':
                if (method_exists($this, 'assertIsResource')) {
                    self::assertIsResource($input);
                } else {
                    self::assertInternalType('resource', $input);
                }
                break;
            case 'string':
                if (method_exists($this, 'assertIsString')) {
                    self::assertIsString($input);
                } else {
                    self::assertInternalType('string', $input);
                }
                break;
            case 'scalar':
                if (method_exists($this, 'assertIsScalar')) {
                    self::assertIsScalar($input);
                } else {
                    self::assertInternalType('scalar', $input);
                }
                break;
            case 'callable':
                if (method_exists($this, 'assertIsCallable')) {
                    self::assertIsCallable($input);
                } else {
                    self::assertInternalType('callable', $input);
                }
                break;
            case 'iterable':
                if (method_exists($this, 'assertIsIterable')) {
                    self::assertIsIterable($input);
                } else {
                    self::assertInternalType('iterable', $input);
                }
                break;
        }
    }

    /**
     * @param string $needle
     * @param string $haystack
     */
    public function assertStringContainsStringGuzzle($needle, $haystack)
    {
        if (method_exists($this, 'assertStringContainsString')) {
            self::assertStringContainsString($needle, $haystack);
        } else {
            self::assertContains($needle, $haystack);
        }
    }
}
