<?php

namespace App\Services;

use DateTime;
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

    private function validateDateInterval(string $start, string $end): bool
    {
        try {
            $startTime = new DateTime($start);
            $endTime = new DateTime($end);
            $interval = $startTime->diff($endTime);

            return $interval->h > 12 || ($interval->h == 12 && $interval->i > 0);
        }
        catch (\Exception) {
            return false;
        }
    }

    private function dateIsAvailableForUser(string $workerId, string $start): bool {

        try {
            $dayStart = new DateTime($start);
            $dayStart = $dayStart->format('Y-m-d');

            $query = $this->connection->createQueryBuilder();
            $query
                ->select('*')
                ->from('WorkTime')
                ->where('worker_id = ?')
                ->andWhere('DATE(start) = ?')
                ->setParameter(0, $workerId)
                ->setParameter(1, $dayStart);

            $result = $query->executeQuery()->rowCount();

            return $result === 0;
        }
        catch (\Exception) {
            return false;
        }
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
        } catch (Exception $e) {
            (new Logger)->error($e->getMessage());
            return null;
        }
    }

    public function registerTime(array $data = []): string
    {

        $failureMessage = 'Czas pracy nie został dodany.';
        $successMessage = 'Czas pracy został dodany!';

        if (empty($data) || !$this->validateDateInterval($data['start'], $data['end'])
            || !$this->dateIsAvailableForUser($data['worker_id'] , $data['start'])) {
            return $failureMessage;
        }

        unset($data['worker_id']);

        try {
            $start = new DateTime($data['start']);
            $dayStart = $start->format('Y-m-d');

            $data['day'] = $dayStart;

            $this->connection->beginTransaction();
            $this->connection->insert('WorkTime', $data);
            $this->connection->commit();

            return $successMessage;
        } catch (Exception $e) {
            (new Logger)->error($e->getMessage());
            return $failureMessage;
        }
    }
}