# blyninė

Kiekvieną kartą – kita istorija. PHP pagrindinis puslapis kiekvieną kartą nepriklausomai ir vienoda tikimybe parenka vieną iš trijų patirčių:

- nukreipimą į https://pirmasblynas.lt/;
- keiksmažodžių žodyną;
- blynų vartymo žaidimą.

Pakartojimai galimi. Pagrindinio puslapio ir nukreipimo atsakymai nekaupiami podėlyje. Tiesioginiai `/keiksmazodziai/`, `/zaidimas/` ir `/admin/` adresai atsitiktinės atrankos nenaudoja. Naujos patirtys įtraukiamos į `lib/experiences.php` sąrašą; kiekvienai automatiškai tenka `1/N` tikimybė.

## Reikalavimai

- Apache arba su `.htaccess` suderinamas Hostinger serveris; įjungti `mod_rewrite` ir `mod_headers`, leidžiami `.htaccess` pakeitimai.
- PHP 8.2 ar naujesnis, `pdo_mysql`, `mbstring`, sesijos; esamai naujienlaiškio integracijai – `curl`.
- MySQL 8 arba suderinama MariaDB, `utf8mb4`.
- Node.js ir npm žaidimui sukurti. Gamybiniame serveryje Node nereikalingas.

## Vietinis paleidimas ir patikra

Izoliuota Docker aplinka naudoja tik savo bandymų duomenų bazę. Ji **nenaudoja** esamo `env.local.php` ir nesijungia prie gyvos svetainės.

```sh
npm --prefix game ci
npm --prefix game run build
docker compose -f tests/compose.yaml up -d --build
docker compose -f tests/compose.yaml exec -T web php scripts/migrate.php
docker compose -f tests/compose.yaml exec -T web php scripts/seed.php
php tests/unit.php
docker compose -f tests/compose.yaml exec -T web php tests/integration.php
docker compose -f tests/compose.yaml exec -T web php tests/failure-modes.php
```

Svetainė: `http://127.0.0.1:8087/keiksmazodziai/`. Žaidimas: `http://127.0.0.1:8087/zaidimas/`.

Vietinis bandymų administratorius: `test-admin`, slaptažodis `test-password-only`. Šie duomenys galioja tik Docker bandymų konfigūracijai, kuri nepatenka į leidinio archyvą.

```sh
docker compose -f tests/compose.yaml down --volumes
```

Ši komanda pašalina tik projekto bandymų konteinerius ir bandymų duomenis.

## Paruošimas Hostinger

1. Sukurk žaidimo failus ir leidinio archyvą:

   ```sh
   npm --prefix game ci
   npm --prefix game run build
   python3 scripts/package.py
   ```

2. Išbandyk atskiroje bandomojoje svetainėje. Įkelk `dist/blynine-hostinger.zip` turinį į dokumentų šakninį katalogą, įskaitant paslėptus `.htaccess` failus. Prieš keisdamas gyvą svetainę išsaugok ankstesnių failų ir duomenų bazės kopiją.
3. Išsaugok esamą `env.local.php` su dabartiniais DB ir Sender nustatymais. Archyvas jo nekeičia. Naujai aplinkai nukopijuok `env.example.php` į `env.local.php` ir įrašyk jos prisijungimo duomenis.
4. Paleisk `php scripts/create-admin.php`, sugeneruok administratoriaus slaptažodžio maišą ir `APP_SECRET`, tada pridėk išvestus nustatymus prie `env.local.php`. Slaptažodžio ar tikrojo konfigūracijos failo nedėk į Git.
5. Per SSH arba Hostinger terminalą paleisk:

   ```sh
   php scripts/migrate.php
   php scripts/seed.php
   ```

   Migravimą ir pradinį užpildymą galima kartoti. Esami pasiūlymai, prenumeratos ir redaguoti žodyno įrašai neperrašomi.
6. Įjunk HTTPS. Jei Hostinger ar CDN kaupia visus puslapius, nustatyk podėlio išimtis `/`, `/index.php`, `/keiksmazodziai/*` ir `/admin/*`; išsaugok serverio `Cache-Control: no-store` antraštes. Statiniai žaidimo, šriftų ir stiliaus failai gali būti talpinami podėlyje.
7. Patikrink nukreipimą, abi tiesiogines patirtis, pateikimą ir administratoriaus patvirtinimą. `.git`, konfigūracija, `lib`, `scripts`, `migrations` ir `tests` neturi būti pasiekiami naršykle.

Vien prisijungus prie GitHub šakos gyva svetainė nesikeičia. Šiame darbe gamybinis diegimas neatliekamas. Grįžimui į vietos rezervavimo puslapį atkurk ankstesnį failų leidinį; naujos žodyno lentelės gali likti duomenų bazėje.

## Turinys ir administratorius

Pateikti įrašai visada laukia patvirtinimo. Administratorius gali juos redaguoti, patvirtinti, atmesti arba išimti iš viešo žodyno, taip pat kurti įrašus ir keisti žymas. Žymų kalba gali skirtis nuo sąsajos: pradinės kilmės žymos yra `Lithuanian`, `English`, `fuck ruzia`. Pervadinus žymą jos ryšiai su įrašais išlieka.

Reikšmės ir pavyzdžiai – originalūs redakciniai paaiškinimai. Pradinį rinkinį galima keisti administratoriaus skydelyje. Žaidimo kilmė ir autoriaus nuoroda pateikta `game/UPSTREAM.md`; naudojimo leidimas patvirtintas projekto užsakovo.
