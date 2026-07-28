# Projekto kontekstas

Tai minimalus PHP web projektas, skirtas PostgreSQL 18 veikimui ir ryšiui tikrinti.

## Paskirtis

- Naršyklėje parodyti, kuris web serveris (host'as) priėmė užklausą ir dabartinį laiką (`Europe/Vilnius`).
- Patikrinti ryšį su PostgreSQL ir aiškiai parodyti būseną: žalia `OK` arba raudona klaida.
- Kai DB nepasiekiama, rodyti paskutinio sėkmingo prisijungimo laiką.
- Kiekvieną sėkmingą HTTP užklausą įrašyti į DB.

## Architektūra

- PHP 8.4 su Apache; viešas įėjimo taškas: `public/index.php`.
- PostgreSQL 18 paleidžiamas per `docker-compose.yml` kaip paslauga `db`.
- DB schema: `db/init/001_schema.sql`.
  - `request_clients` saugo vieną įrašą klientui ir atnaujina `last_request_at` bei `request_count`.
  - `request_log` saugo kiekvienos užklausos žurnalo įrašą.
- Prisijungimo duomenys gaunami tik iš aplinkos kintamųjų; `.env` nekomituojamas, naudokite `.env.example` kaip šabloną.
- Paskutinis sėkmingas DB ryšys saugomas `storage/last-db-success.json`, Docker volume'e.

## Darbo taisyklės

- Laikykite projektą paprastą: nenaudokite framework'o ar papildomų PHP bibliotekų, nebent to aiškiai paprašoma.
- DB užklausoms naudokite PDO ir paruoštas užklausas (prepared statements).
- Neįrašykite slaptažodžių, tikro `.env` turinio ar DB duomenų į repozitoriją.
- Keičiant DB schemą, pridėkite naują numeruotą SQL failą į `db/init/`; nekeiskite esamo inicializavimo failo, jei schema jau naudojama.
- Prieš užbaigiant pakeitimus, jei įrankiai prieinami, vykdykite `php -l public/index.php` ir `docker compose config`.
