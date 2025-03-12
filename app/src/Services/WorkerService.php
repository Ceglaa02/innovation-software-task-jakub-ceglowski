<?php

namespace App\Services;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpKernel\Log\Logger;

class WorkerService
{
    private Connection $connection;
    public function __construct()
    {
        $dotenv = new Dotenv();
        $dotenv->load('/var/www/app/.env');

        $dbUser = $_ENV['MYSQL_DB_USER'];
        $dbPassword = $_ENV['MYSQL_DB_PASSWORD'];
        $dbName = $_ENV['MYSQL_DB_NAME'];
        $dbHost = $_ENV['MYSQL_DB_HOST'];
        $dbPort = $_ENV['MYSQL_DB_PORT'];

        $connectionParams = [
            'dbname' => $dbName,
            'user' => $dbUser,
            'password' => $dbPassword,
            'host' => $dbHost,
            'port' => $dbPort,
            'driver' => 'pdo_mysql',
        ];

        $this->connection = DriverManager::getConnection($connectionParams);
    }

    public function add(array $data = []): ?string
    {
        if (empty($data)) {
            return null;
        }

        try {
            $this->connection->beginTransaction();
            $this->connection->insert('Workers', $data);
            $this->connection->commit();

            return $data['id'];
        }
        catch (Exception $e) {
            (new Logger)->error($e->getMessage());
            return null;
        }
    }
}