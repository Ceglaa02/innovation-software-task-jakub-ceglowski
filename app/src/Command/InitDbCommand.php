<?php

namespace App\Command;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Schema;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Dotenv\Dotenv;
use Doctrine\DBAL\Connection;

#[AsCommand(name: 'app:create-db')]
class InitDbCommand extends Command
{
    private Connection $connection;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $dotenv = new Dotenv();
            $dotenv->load('/var/www/app/.env');

            $dbUser = $_ENV['MYSQL_DB_USER'];
            $dbPassword = $_ENV['MYSQL_DB_PASSWORD'];
            $dbName = $_ENV['MYSQL_DB_NAME'];
            $dbHost = $_ENV['MYSQL_DB_HOST'];
            $dbPort = $_ENV['MYSQL_DB_PORT'];

            $dbRootUser = $_ENV['MYSQL_DB_ROOT_USER'];
            $dbRootPassword = $_ENV['MYSQL_DB_ROOT_PASSWORD'];

            $connectionParams = [
                'dbname' => null,
                'user' => $dbRootUser,
                'password' => $dbRootPassword,
                'host' => $dbHost,
                'port' => $dbPort,
                'driver' => 'pdo_mysql',
            ];

            $this->connection = DriverManager::getConnection($connectionParams);

            $this->connection->executeStatement('CREATE DATABASE IF NOT EXISTS ' . $dbName);
            $this->connection->executeStatement('USE ' . $dbName);

            $schema = new Schema();

            $worker = $schema->createTable('Workers');
            $worker->addColumn('id', 'string', ['length' => 36]);
            $worker->addColumn('name', 'string', ['length' => 50]);
            $worker->addColumn('surname', 'string', ['length' => 50]);
            $worker->setPrimaryKey(['id']);

            $workTime = $schema->createTable('WorkTime');
            $workTime->addColumn('id', 'integer', ['autoincrement' => true]);
            $workTime->addColumn('worker_id', 'string', ['length' => 36]);
            $workTime->addColumn('start', 'datetime');
            $workTime->addColumn('end', 'datetime');
            $workTime->addColumn('day', 'date');
            $workTime->setPrimaryKey(['id']);
            $workTime->addForeignKeyConstraint('Worker', ['worker_id'], ['id'], ['onDelete' => 'CASCADE']);

            $platform = $this->connection->getDatabasePlatform();
            $queries = $schema->toSql($platform);

            foreach ($queries as $query) {
                $this->connection->executeStatement($query);
            }

            $this->connection->executeStatement("CREATE USER IF NOT EXISTS '$dbUser'@'%' IDENTIFIED BY '$dbPassword'");
            $this->connection->executeStatement("GRANT ALL PRIVILEGES ON $dbName.* TO '$dbUser'@'%'");
            $this->connection->executeStatement("FLUSH PRIVILEGES");

            $output->writeln('Database created!');
            return Command::SUCCESS;
        } catch (Exception $e) {
            $output->writeln($e->getMessage());
            return Command::FAILURE;
        }

    }
}