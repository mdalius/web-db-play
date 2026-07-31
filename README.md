# web-db-play

Minimalus PHP puslapis PostgreSQL 18 ryšiui ir užklausų įrašymui tikrinti.

## Paleidimas

1. Nukopijuokite `.env.example` į `.env` ir, jei reikia, pakeiskite slaptažodį.
2. Paleiskite `docker compose up --build`.
3. Atidarykite [http://localhost:8080](http://localhost:8080).

Puslapis rodo web konteinerio host'ą bei laiką, prisijungimo prie PostgreSQL būseną ir paskutinio sėkmingo įrašo į DB laiką. Ši informacija saugoma atskirame aplikacijos volume, todėl lieka pasiekiama, kai DB nebeveikia.

Kiekvienam DB įrašui taip pat išsaugomas įrašo laikas, PostgreSQL node'as ir PostgreSQL serverio hostname. Node'o vardas imamas iš `app.node_name`, o hostname — iš `app.node_hostname` PostgreSQL nustatymų. Jei nustatymai neįrašyti, naudojamas DB serverio adresas (`inet_server_addr()`) kaip atsarginė reikšmė. Kiekviename clusterio node'e galima nustatyti, pavyzdžiui:

```sql
ALTER SYSTEM SET app.node_name = 'pg-node-1';
ALTER SYSTEM SET app.node_hostname = 'postgres-primary-1';
SELECT pg_reload_conf();
```

## Duomenų bazė

`db/init/001_schema.sql`, `db/init/002_add_db_node.sql` ir `db/init/003_add_db_hostname.sql` sukuria bei papildo dvi lenteles:

- `request_clients` — vienas įrašas kiekvienam naršyklės klientui; kiekvienos užklausos metu atnaujinami `last_request_at` ir `request_count`.
- `request_log` — atskiras kiekvienos užklausos žurnalo įrašas su `requested_at`, `db_node` ir `db_hostname` reikšmėmis.

Pradiniai SQL skriptai vykdomi tik kuriant naują PostgreSQL duomenų volume. Jei volume jau egzistuoja, `002_add_db_node.sql` ir `003_add_db_hostname.sql` reikia įvykdyti ranka arba pašalinti tik šio projekto Docker volume ir paleisti iš naujo.
