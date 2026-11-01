# blyninė

Kiekvieną kartą – kita istorija. Pagrindinis puslapis kiekvieną kartą nepriklausomai ir vienoda tikimybe parenka nukreipimą į https://pirmasblynas.lt/, keiksmažodžių žodyną arba blynų vartymo žaidimą. Pakartojimai galimi. Tiesioginiai `/keiksmazodziai/`, `/zaidimas/` ir `/admin/` adresai atrankos nenaudoja.

## Paleidimas Hostinger: aplinkos failas ir tuščia DB

1. **Sukurk tuščią MySQL duomenų bazę** ir naudotoją, turintį teises kurti bei keisti jos lenteles.
2. **Serveryje įkelk `.env`** pagal `.env.example` šabloną. Įrašyk duomenų bazės prisijungimą ir pasirinktą administratoriaus slaptažodį:

   ```dotenv
   DB_HOST=localhost
   DB_PORT=3306
   DB_NAME=tavo_duomenu_baze
   DB_USER=tavo_naudotojas
   DB_PASS="tavo DB slaptažodis"
   ADMIN_USERNAME=admin
   ADMIN_PASSWORD="tavo ilgas unikalus slaptažodis"
   ```

   `.env` turi būti svetainės šakniniame kataloge, šalia `index.php`. Administratoriaus slaptažodis turi būti bent 12 ženklų ir ne ilgesnis kaip 128 baitai. Kabutėse `$`, `#` ir `=` išsaugomi tiesiogiai; kintamieji ir komandos nevykdomi.
3. **Vieną kartą prijunk Git diegimą:** Hostinger → Websites → blynine.lt → Manage site → Advanced → Git → Connect with GitHub. Pasirink `KazimierasJasaitis/blynine.lt`, šaką **`codex/hostinger-deploy`**, katalogą `public_html` ir įjunk automatinį diegimą. [Oficiali Hostinger instrukcija](https://www.hostinger.com/support/1583302-how-to-deploy-a-git-repository-in-hostinger/).

Atidarius žodyną pirmą kartą, lentelės ir 30 pradinių įrašų sukuriami automatiškai. Prisijungimas – `/admin/`, naudojant `.env` pasirinktus duomenis. **SQL importo, terminalo komandų, slaptažodžio maišo ar atskiro APP_SECRET generuoti nereikia.** Žaidimas diegimo šakoje jau sukurtas; Node.js serveryje nereikalingas.

Jei serveryje jau yra senasis `env.local.php`, jis veiks, kol nėra `.env`. Naujas `.env` turi pirmenybę. Į `.env` perkelk norimus naudoti DB ir administratoriaus duomenis. Prieš pirminį prijungimą ar diegimo šakos keitimą išsaugok serverio konfigūracijos kopiją; patys konfigūracijos failai į Git ar leidinio archyvą niekada neįtraukiami.

## Kas vyksta po kodo pakeitimo

```text
push į codex/random-website arba main
  → GitHub Actions sukuria žaidimą ir patikrina svetainę
  → publikuoja tik paleidimui reikalingus failus į codex/hostinger-deploy
  → Hostinger automatiškai pasiima šios šakos pakeitimus
```

Kodas redaguojamas šaltinio šakoje; `codex/hostinger-deploy` sugeneruojama automatiškai. GitHub nereikia Hostinger slaptažodžių ar diegimo paslapčių. Diegimo šakoje nėra testų, Node priklausomybių ar tikros konfigūracijos. `.env` ir `env.local.php` lieka neįtraukti į Git.

Naujos migracijos pritaikomos automatiškai per kitą DB naudojimą. Vienu metu atėjusius lankytojus valdo MySQL užraktas. Baigtas paruošimas pažymimas DB, todėl įprasti apsilankymai nekartoja lentelių kūrimo ar pradinio užpildymo. Administratoriaus pataisyti, atmesti ir iš viešo žodyno išimti įrašai neperrašomi.

GitHub procesas paruošia diegimo šaką; Hostinger ryšį reikia vieną kartą įjungti savo hPanel paskyroje. Šakos paruošimas savaime nekeičia gyvos svetainės, kol ji neprijungta Hostinger.

## Reikalavimai

- PHP 8.2 ar naujesnis, `pdo_mysql`, `mbstring`, sesijos. Esamai Sender integracijai – `curl`.
- MySQL 8 arba suderinama MariaDB su vardinių užraktų palaikymu.
- Apache arba su `.htaccess` suderinamas Hostinger serveris, įjungti `mod_rewrite` ir `mod_headers`.
- HTTPS. Pagrindinio puslapio, žodyno ir administravimo `Cache-Control: no-store` antraštės turi būti išsaugotos; šių adresų nekaupk viso puslapio CDN podėlyje.

## Vietinė patikra

Izoliuota Docker aplinka naudoja savo bandymų DB ir `.env` formato konfigūraciją. Ji nesijungia prie gyvos DB ir nenaudoja tikrojo `env.local.php`.

```sh
npm --prefix game ci
npm --prefix game run build
docker compose -f tests/compose.yaml up -d --build --wait
docker compose -f tests/compose.yaml exec -T web php tests/unit.php
docker compose -f tests/compose.yaml exec -T web php tests/bootstrap.php
docker compose -f tests/compose.yaml exec -T web php tests/auto-setup.php
docker compose -f tests/compose.yaml exec -T web php tests/integration.php
docker compose -f tests/compose.yaml exec -T web php tests/failure-modes.php
python3 tests/deployment.py
```

Vietinis žodynas – `http://127.0.0.1:8087/keiksmazodziai/`, žaidimas – `/zaidimas/`. Bandymų administratorius: `test-admin`, slaptažodis `test-password-only`. Šie duomenys nepatenka į leidinį.

```sh
docker compose -f tests/compose.yaml down --volumes
```

Ši komanda pašalina tik projekto bandymų konteinerius ir jų laikiną DB.

## Alternatyva: ZIP leidinys

Jei nenaudoji Git diegimo:

```sh
npm --prefix game ci
npm --prefix game run build
python3 scripts/package.py
```

Įkelk `dist/blynine-hostinger.zip` turinį, įskaitant `.htaccess`, pridėk `.env` ir sukurk tuščią DB. Toliau svetainė susitvarko pati. CLI migravimo, pradinio užpildymo ir seno administratoriaus maišo generavimo įrankiai palikti tik pasirinktiniam naudojimui.

## Turinys

Pateikti keiksmažodžiai laukia administratoriaus patvirtinimo. Administratorius gali juos taisyti, patvirtinti, atmesti ar išimti iš viešo žodyno, kurti naujus įrašus ir keisti žymas. Pradinės kilmės žymos: `Lithuanian`, `English`, `fuck ruzia`. Pervadinus žymą jos ryšiai išlieka.

Žaidimo autorius ir pradinė versija nurodyti `game/UPSTREAM.md`; naudojimo leidimas patvirtintas projekto užsakovo.
