<?php
/**
 * API for Billing
 *
 * @link      https://github.com/hiqdev/billing-hiapi
 * @package   billing-hiapi
 * @license   BSD-3-Clause
 * @copyright Copyright (c) 2017-2020, HiQDev (http://hiqdev.com/)
 */

namespace hiqdev\billing\hiapi\tests\behat\bootstrap;

use Dotenv\Dotenv;
use Exception;
use hipanel\hiart\Connection;
use hiqdev\hiart\guzzle\Request;
use hiqdev\hiart\RequestInterface;
use Yii;
use yii\di\Container;
use yii\web\Application;

class ApiClient
{
    private Connection $connection;

    private Application $application;

    public function __construct()
    {
        $this->bootstrap();
        $application = $this->application ?? $this->mockApplication();
        $this->connection = new Connection($application);
        $this->connection->requestClass = Request::class;
        $this->connection->baseUri = $_ENV['HIART_BASEURI'];
    }

    private function bootstrap(): void
    {
        $dir = dirname(__DIR__, 6);
        $pathToYii = $dir . '/vendor/yiisoft/yii2/Yii.php';
        require_once $pathToYii;
        (Dotenv::createImmutable($dir))->load();
        if (empty($_ENV['HIART_BASEURI'])) {
            throw new Exception('HIART_BASEURI must be set in environment');
        }
    }

    public function make(string $command, array $payload, string $performer): array
    {
        //var_dump(compact('command', 'payload', 'performer'));

        $res = $this->buildRequest($command, $payload, $performer ?? $this->reseller)->send()->getData();
        if (!is_array($res)) {
            throw new Exception('API returned not array: ' . $res);
        }
        if (!empty($res['_error'])) {
            // var_dump(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__, $command, $performer, $this->lastQuery, $res);die;
            $error = is_array($res['_error']) ? reset($res['_error']) : (string)$res['_error'];
            throw new Exception("API returned error: $error");
        }

        return $res;
    }

    private function buildRequest(string $command, array $body = [], ?string $performer = null): RequestInterface
    {
        $body['auth_login'] = $performer;
        $body['auth_password'] = 'random';
        $this->lastQuery = $this->connection->baseUri . $command . '?' . http_build_query($body);

        return $this->connection->callWithDisabledAuth(
            function () use ($command, $body) {
                $request = $this->connection
                    ->createCommand()
                    ->db
                    ->getQueryBuilder()
                    ->perform($command, null, $body);
                $request->build();
                $request->addHeader('Cookie', 'XDEBUG_SESSION=XDEBUG_ECLIPSE');

                return $request;
            }
        );
    }

    private function mockApplication(): Application
    {
        Yii::$container = new Container();

        return new Application(
            [
                'id'         => 'behat-test-application',
                'basePath'   => __DIR__,
                'vendorPath' => __DIR__ . '../../../../../',
            ]
        );
    }
}
