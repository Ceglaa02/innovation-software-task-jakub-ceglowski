<?php

namespace App\Services;

use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;
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

            $hours = $interval->h;
            $minutes = $interval->i;

            return !($hours > 12 || ($hours === 12 && $minutes > 0));
        } catch (\Exception) {
            return false;
        }
    }

    private function dateIsAvailableForUser(string $workerId, string $start): bool
    {

        try {
            $dayStart = new DateTime($start);
            $dayStart = $dayStart->format('Y-m-d');

            $query = $this->connection->createQueryBuilder();
            $query
                ->select('*')
                ->from('WorkTime')
                ->where('worker_id = ?')
                ->andWhere('day = ?')
                ->setParameter(0, $workerId)
                ->setParameter(1, $dayStart);

            $result = $query->executeQuery()->rowCount();

            return $result === 0;
        } catch (\Exception) {
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

        $failureMessage = 'Czas pracy nie zostal dodany.';
        $successMessage = 'Czas pracy zostal dodany!';

        if (empty($data) || !$this->validateDateInterval($data['start'], $data['end'])
            || !$this->dateIsAvailableForUser($data['worker_id'], $data['start'])) {
            return $failureMessage;
        }

        try {
            $start = new DateTime($data['start']);
            $end = new DateTime($data['end']);

            $dayStart = $start->format('Y-m-d');

            $data['day'] = $dayStart;
            $data['start'] = $start->format('Y-m-d H:i');
            $data['end'] = $end->format('Y-m-d H:i');

            $this->connection->beginTransaction();
            $this->connection->insert('WorkTime', $data);
            $this->connection->commit();

            return $successMessage;
        } catch (Exception $e) {
            (new Logger)->error($e->getMessage());
            return $e->getMessage();
        }
    }

    public function summary(string $workerId, string $date): array
    {

        function isFullDate(string $date): bool
        {
            $d = DateTime::createFromFormat('Y-m-d', $date);
            if ($d && $d->format('Y-m-d') === $date) {
                return true;
            }

            return false;
        }

        $filesystem = new Filesystem();

        try {
            $query = $this->connection->createQueryBuilder();
            $query
                ->select('SUM(ROUND(TIMESTAMPDIFF(MINUTE, start, end) / 30) * 30 / 60) AS total_work_hours')
                ->from('WorkTime')
                ->where('worker_id = ?')
                ->groupBy('worker_id')
                ->setParameter(0, $workerId);

            $fullDate = isFullDate($date);
            $searchDateFormat = $fullDate  ? "DATE_FORMAT(day, '%Y-%m-%d') = ?" : "DATE_FORMAT(day, '%Y-%m') = ?";

            $query
                ->andWhere($searchDateFormat)
                ->setParameter(1, $date);

            $hours = (int)$query->executeQuery()->fetchOne();
            $system = parse_ini_file('/var/www/app/config/system.ini');

            $result = ["stawka" => $system['rate_per_hour_pln'] . " PLN"];

            if ($fullDate) {
                return array_merge($result, [
                    "suma po przeliczeniu" => ($system['rate_per_hour_pln'] * $hours) . " PLN",
                    "ilość godzin z danego dnia" => $hours,
                ]);
            }

            $additionalHours = $hours > 40 ? $hours - 40 : 0;
            $normalHours = $hours > 40 ? 40 : $hours;

            $additionalRatePerHour = $system['rate_per_hour_pln'] * ($system['additional_per_hour_percent_rate'] / 100);

            $sum = $normalHours * $system['additional_per_hour_percent_rate'];

            if ($additionalHours > 0) {
                $sum += $additionalRatePerHour * $additionalHours;
            }

            return array_merge($result,  [
                "ilość normalnych godzin z danego miesiąca" => $normalHours,
                "ilość nadgodzin z danego miesiąca" => $additionalHours,
                "stawka nadgodzinowa" => $additionalRatePerHour . " PLN",
                "suma po przeliczeniu" => $sum . " PLN"
            ]);



        } catch (Exception $e) {
            (new Logger)->error($e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
}