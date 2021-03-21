<?php

namespace GuzzleHttp\Tests\Psr7\Integration;

use GuzzleHttp\Tests\Psr7\BaseTest;

class ServerRequestFromGlobalsTest extends BaseTest
{
    /**
     * @before
     */
    protected function setUpTest()
    {
        if (false === $this->getServerUri()) {
            self::markTestSkipped();
        }
        parent::setUp();
    }

    public function testBodyExists()
    {
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $this->getServerUri());
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, 'foobar');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);
        curl_close($curl);

        self::assertNotFalse($response);
        $data = json_decode($response, true);
        self::assertIsArray($data);
        self::assertArrayHasKey('method', $data);
        self::assertArrayHasKey('uri', $data);
        self::assertArrayHasKey('body', $data);

        self::assertEquals('foobar', $data['body']);
    }

    private function getServerUri()
    {
        return isset($_SERVER['TEST_SERVER']) ? $_SERVER['TEST_SERVER'] : false;
    }
}
