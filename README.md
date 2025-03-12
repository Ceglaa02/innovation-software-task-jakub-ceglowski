# innovation-software-task-jakub-ceglowski

Instalacja:

1. Po pobraniu projektu proszę odpalić dockera z folderu z projektem: docker-compose up -d --build
2. Nastepnie proszę wejść do kontenera: docker exec -it php-fpm bash
3. Po zbudowaniu kontenera php-fpm proszę wykonać w nim komende: php composer.phar install
4. Po zainstalowaniu zależności composera proszę wpisać: php bin/console app:create-db
5. Można usunąć kontener composer ponieważ miał on pobrać tylko jego zależności

Endpointy:

1. Dodawanie użytkownika: url = https://localhost/worker/add, metoda = POST, parametry = name, surname
2. Rejestrowanie czasu użytkownika: url = https://localhost/worker-time/register, metoda = POST, parametry = worker_id, start, end
3. Podsumowanie czasu użytkownika: url = https://localhost/worker-time/summary, metoda = POST, parametry = worker_id, day

