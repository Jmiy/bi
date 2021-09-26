<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace Hyperf\Database\Connectors;

use InvalidArgumentException;

use Hyperf\Database\Connection;
use Hyperf\Database\MySqlConnection;
use Hyperf\Database\PostgresConnection;
use Hyperf\Database\Connectors\ConnectionFactory as HyperfDatabaseConnectionFactory;

use Hyperf\Di\Annotation\Aspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;

/**
 * @Aspect
 */
class ConnectionFactory extends AbstractAspect
{
    // 要切入的类，可以多个，亦可通过 :: 标识到具体的某个方法，通过 * 可以模糊匹配
    public $classes = [
        HyperfDatabaseConnectionFactory::class . '::createConnector',
        HyperfDatabaseConnectionFactory::class . '::createConnector',
    ];

    // 要切入的注解，具体切入的还是使用了这些注解的类，仅可切入类注解和类方法注解
    public $annotations = [
    ];

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        return call([$this, "aop_" . $proceedingJoinPoint->methodName], [$proceedingJoinPoint]);
    }

    /**
     * Create a connector instance based on the configuration.
     *
     * @throws \InvalidArgumentException
     * @return ConnectorInterface
     */
    public function aop_createConnector(ProceedingJoinPoint $proceedingJoinPoint)//,array $config
    {

        $config = data_get($proceedingJoinPoint->arguments, 'keys.config', []);

        if (! isset($config['driver'])) {
            throw new InvalidArgumentException('A driver must be specified.');
        }

        if ($this->container->has($key = "db.connector.{$config['driver']}")) {
            return $this->container->get($key);
        }

        switch ($config['driver']) {
            case 'mysql':
                return new MySqlConnector();
            case 'pgsql':
                return new PostgresConnector();
        }

        throw new InvalidArgumentException("Unsupported driver [{$config['driver']}]");
    }

    /**
     * Create a new connection instance.
     *
     * @param string $driver
     * @param \Closure|\PDO $connection
     * @param string $database
     * @param string $prefix
     * @throws \InvalidArgumentException
     * @return \Hyperf\Database\Connection
     */
    protected function aop_createConnection(ProceedingJoinPoint $proceedingJoinPoint)//$driver, $connection, $database, $prefix = '', array $config = []
    {

        $driver=data_get($proceedingJoinPoint->arguments, 'keys.driver');
        $connection=data_get($proceedingJoinPoint->arguments, 'keys.connection');
        $database=data_get($proceedingJoinPoint->arguments, 'keys.database');
        $prefix =data_get($proceedingJoinPoint->arguments, 'keys.prefix','');
        $config =data_get($proceedingJoinPoint->arguments, 'keys.config',[]);

        if ($resolver = Connection::getResolver($driver)) {
            return $resolver($connection, $database, $prefix, $config);
        }

        switch ($driver) {
            case 'mysql':
                return new MySqlConnection($connection, $database, $prefix, $config);
            case 'pgsql':
                return new PostgresConnection($connection, $database, $prefix, $config);
        }

        throw new InvalidArgumentException("Unsupported driver [{$driver}]");
    }
}