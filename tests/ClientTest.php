<?php

use FoneroRPC\Fonero;
use FoneroRPC\Fonero\Exceptions;
use GuzzleHttp\Psr7\Response;

class ClientTest extends TestCase
{
    /**
     * Set up test.
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $this->fonerod = new Fonero\Client();
    }

    /**
     * Test url parser.
     *
     * @param string $url
     * @param string $scheme
     * @param string $host
     * @param int    $port
     * @param string $user
     * @param string $pass
     *
     * @return void
     *
     * @dataProvider urlProvider
     */
    public function testUrlParser($url, $scheme, $host, $port, $user, $pass)
    {
        $fonerod = new Fonero\Client($url);

        $this->assertInstanceOf(Fonero\Client::class, $fonerod);

        $base_uri = $fonerod->getConfig('base_uri');

        $this->assertEquals($base_uri->getScheme(), $scheme);
        $this->assertEquals($base_uri->getHost(), $host);
        $this->assertEquals($base_uri->getPort(), $port);

        $auth = $fonerod->getConfig('auth');
        $this->assertEquals($auth[0], $user);
        $this->assertEquals($auth[1], $pass);
    }

    /**
     * Data provider for url expander test.
     *
     * @return array
     */
    public function urlProvider()
    {
        return [
            ['https://localhost', 'https', 'localhost', 19191, '', ''],
            ['https://localhost:8000', 'https', 'localhost', 8000, '', ''],
            ['http://localhost', 'http', 'localhost', 19191, '', ''],
            ['http://localhost:8000', 'http', 'localhost', 8000, '', ''],
            ['http://testuser@127.0.0.1:8000/', 'http', '127.0.0.1', 8000, 'testuser', ''],
            ['http://testuser:testpass@localhost:8000', 'http', 'localhost', 8000, 'testuser', 'testpass'],
        ];
    }

    /**
     * Test url parser with invalid url.
     *
     * @return array
     */
    public function testUrlParserWithInvalidUrl()
    {
        try {
            $fonerod = new Fonero\Client('cookies!');

            $this->expectException(Exceptions\ClientException::class);
        } catch (Exceptions\ClientException $e) {
            $this->assertEquals('Invalid url', $e->getMessage());
        }
    }

    /**
     * Test client getter and setter.
     *
     * @return void
     */
    public function testClientSetterGetter()
    {
        $fonerod = new Fonero\Client('http://old_client.org');
        $this->assertInstanceOf(Fonero\Client::class, $fonerod);

        $base_uri = $fonerod->getConfig('base_uri');
        $this->assertEquals($base_uri->getHost(), 'old_client.org');

        $oldClient = $fonerod->getClient();
        $this->assertInstanceOf(\GuzzleHttp\Client::class, $oldClient);

        $newClient = new \GuzzleHttp\Client(['base_uri' => 'http://new_client.org']);
        $fonerod->setClient($newClient);

        $base_uri = $fonerod->getConfig('base_uri');
        $this->assertEquals($base_uri->getHost(), 'new_client.org');
    }

    /**
     * Test ca config option.
     *
     * @return void
     */
    public function testCaOption()
    {
        $fonerod = new Fonero\Client();

        $this->assertEquals(null, $fonerod->getConfig('ca'));

        $fonerod = new Fonero\Client([
            'ca' => __FILE__,
        ]);

        $this->assertEquals(__FILE__, $fonerod->getConfig('verify'));
    }

    /**
     * Test simple request.
     *
     * @return void
     */
    public function testRequest()
    {
        $guzzle = $this->mockGuzzle([
            $this->getBlockResponse(),
        ]);

        $response = $this->fonerod
            ->setClient($guzzle)
            ->request(
                'getblockheader',
                '000000000019d6689c085ae165831e934ff763ae46a2a6c172b3f1b60a8ce26f'
            );

        $this->assertEquals(self::$getBlockResponse, $response->get());
    }

    /**
     * Test async request.
     *
     * @return void
     */
    public function testAsyncRequest()
    {
        $guzzle = $this->mockGuzzle([
            $this->getBlockResponse(),
        ]);

        $onFulfilled = $this->mockCallable([
            $this->callback(function (Fonero\FonerodResponse $response) {
                return $response->get() == self::$getBlockResponse;
            }),
        ]);

        $promise = $this->fonerod
            ->setClient($guzzle)
            ->requestAsync(
                'getblockheader',
                '000000000019d6689c085ae165831e934ff763ae46a2a6c172b3f1b60a8ce26f',
                function ($response) use ($onFulfilled) {
                    $onFulfilled($response);
                }
            );

        $promise->wait();
    }

    /**
     * Test magic request.
     *
     * @return void
     */
    public function testMagic()
    {
        $guzzle = $this->mockGuzzle([
            $this->getBlockResponse(),
        ]);

        $response = $this->fonerod
            ->setClient($guzzle)
            ->getBlockHeader(
                '000000000019d6689c085ae165831e934ff763ae46a2a6c172b3f1b60a8ce26f'
            );

        $this->assertEquals(self::$getBlockResponse, $response->get());
    }

    /**
     * Test magic request.
     *
     * @return void
     */
    public function testAsyncMagic()
    {
        $guzzle = $this->mockGuzzle([
            $this->getBlockResponse(),
        ]);

        $onFulfilled = $this->mockCallable([
            $this->callback(function (Fonero\FonerodResponse $response) {
                return $response->get() == self::$getBlockResponse;
            }),
        ]);

        $promise = $this->fonerod
            ->setClient($guzzle)
            ->getBlockHeaderAsync(
                '000000000019d6689c085ae165831e934ff763ae46a2a6c172b3f1b60a8ce26f',
                function ($response) use ($onFulfilled) {
                    $onFulfilled($response);
                }
            );

        $promise->wait();
    }

    /**
     * Test fonerod exception.
     *
     * @return void
     */
    public function testFonerodException()
    {
        $guzzle = $this->mockGuzzle([
            $this->rawTransactionError(200),
        ]);

        try {
            $response = $this->fonerod
                ->setClient($guzzle)
                ->getRawTransaction(
                    '4a5e1e4baab89f3a32518a88c31bc87f618f76673e2cc77ab2127b7afdeda33b'
                );

            $this->expectException(Exceptions\FonerodException::class);
        } catch (Exceptions\FonerodException $e) {
            $this->assertEquals(self::$rawTransactionError['message'], $e->getMessage());
            $this->assertEquals(self::$rawTransactionError['code'], $e->getCode());
        }
    }

    /**
     * Test async fonerod exception.
     *
     * @return void
     */
    public function testAsyncFonerodException()
    {
        $guzzle = $this->mockGuzzle([
            $this->rawTransactionError(200),
        ]);

        $onFulfilled = $this->mockCallable([
            $this->callback(function (Exceptions\FonerodException $exception) {
                return $exception->getMessage() == self::$rawTransactionError['message'] &&
                    $exception->getCode() == self::$rawTransactionError['code'];
            }),
        ]);

        $promise = $this->fonerod
            ->setClient($guzzle)
            ->requestAsync(
                'getrawtransaction',
                '4a5e1e4baab89f3a32518a88c31bc87f618f76673e2cc77ab2127b7afdeda33b',
                function ($response) use ($onFulfilled) {
                    $onFulfilled($response);
                }
            );

        $promise->wait();
    }

    /**
     * Test request exception with error code.
     *
     * @return void
     */
    public function testRequestExceptionWithServerErrorCode()
    {
        $guzzle = $this->mockGuzzle([
            $this->rawTransactionError(500),
        ]);

        try {
            $this->fonerod
                ->setClient($guzzle)
                ->getRawTransaction(
                    '4a5e1e4baab89f3a32518a88c31bc87f618f76673e2cc77ab2127b7afdeda33b'
                );

            $this->expectException(Exceptions\FonerodException::class);
        } catch (Exceptions\FonerodException $exception) {
            $this->assertEquals(
                self::$rawTransactionError['message'],
                $exception->getMessage()
            );
            $this->assertEquals(
                self::$rawTransactionError['code'],
                $exception->getCode()
            );
        }
    }

    /**
     * Test async request exception with error code.
     *
     * @return void
     */
    public function testAsyncRequestExceptionWithServerErrorCode()
    {
        $guzzle = $this->mockGuzzle([
            $this->rawTransactionError(500),
        ]);

        $onRejected = $this->mockCallable([
            $this->callback(function (Exceptions\FonerodException $exception) {
                return $exception->getMessage() == self::$rawTransactionError['message'] &&
                    $exception->getCode() == self::$rawTransactionError['code'];
            }),
        ]);

        $promise = $this->fonerod
            ->setClient($guzzle)
            ->requestAsync(
                'getrawtransaction',
                '4a5e1e4baab89f3a32518a88c31bc87f618f76673e2cc77ab2127b7afdeda33b',
                null,
                function ($exception) use ($onRejected) {
                    $onRejected($exception);
                }
            );

        $promise->wait(false);
    }

    /**
     * Test request exception with empty response body.
     *
     * @return void
     */
    public function testRequestExceptionWithEmptyResponseBody()
    {
        $guzzle = $this->mockGuzzle([
            new Response(500),
        ]);

        try {
            $response = $this->fonerod
                ->setClient($guzzle)
                ->getRawTransaction(
                    '4a5e1e4baab89f3a32518a88c31bc87f618f76673e2cc77ab2127b7afdeda33b'
                );

            $this->expectException(Exceptions\ClientException::class);
        } catch (Exceptions\ClientException $exception) {
            $this->assertEquals(
                $this->error500(),
                $exception->getMessage()
            );
            $this->assertEquals(500, $exception->getCode());
        }
    }

    /**
     * Test async request exception with empty response body.
     *
     * @return void
     */
    public function testAsyncRequestExceptionWithEmptyResponseBody()
    {
        $guzzle = $this->mockGuzzle([
            new Response(500),
        ]);

        $onRejected = $this->mockCallable([
            $this->callback(function (Exceptions\ClientException $exception) {
                return $exception->getMessage() == $this->error500() &&
                    $exception->getCode() == 500;
            }),
        ]);

        $promise = $this->fonerod
            ->setClient($guzzle)
            ->requestAsync(
                'getrawtransaction',
                '4a5e1e4baab89f3a32518a88c31bc87f618f76673e2cc77ab2127b7afdeda33b',
                null,
                function ($exception) use ($onRejected) {
                    $onRejected($exception);
                }
            );

        $promise->wait(false);
    }

    /**
     * Test request exception with response.
     *
     * @return void
     */
    public function testRequestExceptionWithResponseBody()
    {
        $guzzle = $this->mockGuzzle([
            $this->requestExceptionWithResponse(),
        ]);

        try {
            $response = $this->fonerod
                ->setClient($guzzle)
                ->getRawTransaction(
                    '4a5e1e4baab89f3a32518a88c31bc87f618f76673e2cc77ab2127b7afdeda33b'
                );

            $this->expectException(Exceptions\FonerodException::class);
        } catch (Exceptions\FonerodException $exception) {
            $this->assertEquals(
                self::$rawTransactionError['message'],
                $exception->getMessage()
            );
            $this->assertEquals(
                self::$rawTransactionError['code'],
                $exception->getCode()
            );
        }
    }

    /**
     * Test async request exception with response.
     *
     * @expectedException GuzzleHttp\Exception\RequestException
     *
     * @return void
     */
    public function testAsyncRequestExceptionWithResponseBody()
    {
        $guzzle = $this->mockGuzzle([
            $this->requestExceptionWithResponse(),
        ]);

        $onRejected = $this->mockCallable([
            $this->callback(function (Exceptions\FonerodException $exception) {
                return $exception->getMessage() == self::$rawTransactionError['message'] &&
                    $exception->getCode() == self::$rawTransactionError['code'];
            }),
        ]);

        $promise = $this->fonerod
            ->setClient($guzzle)
            ->requestAsync(
                'getrawtransaction',
                '4a5e1e4baab89f3a32518a88c31bc87f618f76673e2cc77ab2127b7afdeda33b',
                null,
                function ($exception) use ($onRejected) {
                    $onRejected($exception);
                }
            );

        $promise->wait();
    }

    /**
     * Test request exception with no response.
     *
     * @return void
     */
    public function testRequestExceptionWithNoResponseBody()
    {
        $guzzle = $this->mockGuzzle([
            $this->requestExceptionWithoutResponse(),
        ]);

        try {
            $response = $this->fonerod
                ->setClient($guzzle)
                ->getRawTransaction(
                    '4a5e1e4baab89f3a32518a88c31bc87f618f76673e2cc77ab2127b7afdeda33b'
                );

            $this->expectException(Exceptions\ClientException::class);
        } catch (Exceptions\ClientException $exception) {
            $this->assertEquals(
                'test',
                $exception->getMessage()
            );
            $this->assertEquals(0, $exception->getCode());
        }
    }

    /**
     * Test async request exception with no response.
     *
     * @expectedException GuzzleHttp\Exception\RequestException
     *
     * @return void
     */
    public function testAsyncRequestExceptionWithNoResponseBody()
    {
        $guzzle = $this->mockGuzzle([
            $this->requestExceptionWithoutResponse(),
        ]);

        $onRejected = $this->mockCallable([
            $this->callback(function (Exceptions\ClientException $exception) {
                return $exception->getMessage() == 'test' &&
                    $exception->getCode() == 0;
            }),
        ]);

        $promise = $this->fonerod
            ->setClient($guzzle)
            ->requestAsync(
                'getrawtransaction',
                '4a5e1e4baab89f3a32518a88c31bc87f618f76673e2cc77ab2127b7afdeda33b',
                null,
                function ($exception) use ($onRejected) {
                    $onRejected($exception);
                }
            );

        $promise->wait();
    }

    public function testToBtc()
    {
        $this->assertEquals(0.00005849, Fonero\Client::toBtc(310000 / 53));
    }

    public function testToSatoshi()
    {
        $this->assertEquals(5849, Fonero\Client::toSatoshi(0.00005849));
    }

    public function testToFixed()
    {
        $this->assertSame('1', Fonero\Client::toFixed(1.2345678910, 0));
        $this->assertSame('1.23', Fonero\Client::toFixed(1.2345678910, 2));
        $this->assertSame('1.2345', Fonero\Client::toFixed(1.2345678910, 4));
        $this->assertSame('1.23456789', Fonero\Client::toFixed(1.2345678910, 8));
    }
}