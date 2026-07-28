# web-db-play

Minimalus PHP puslapis PostgreSQL 18 ryšiui ir užklausų įrašymui tikrinti.

## Paleidimas

1. Nukopijuokite `.env.example` į `.env` ir, jei reikia, pakeiskite slaptažodį.
2. Paleiskite `docker compose up --build`.
3. Atidarykite [http://localhost:8080](http://localhost:8080).

Puslapis rodo web konteinerio host'ą bei laiką, prisijungimo prie PostgreSQL būseną ir, gedimo atveju, paskutinį sėkmingą prisijungimą. Sėkmės laikas saugomas atskirame aplikacijos volume, todėl lieka pasiekiamas, kai DB nebeveikia.

## Duomenų bazė

`db/init/001_schema.sql` sukuria dvi lenteles:

- `request_clients` — vienas įrašas kiekvienam naršyklės klientui; kiekvienos užklausos metu atnaujinami `last_request_at` ir `request_count`.
- `request_log` — atskiras kiekvienos užklausos žurnalo įrašas.

Pradinis SQL skriptas vykdomas tik kuriant naują PostgreSQL duomenų volume. Jei schemą keičiate jau paleidę konteinerius, įvykdykite SQL ranka arba pašalinkite tik šio projekto Docker volume ir paleiskite iš naujo.
