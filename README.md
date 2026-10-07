# OpiskelijaHelpdesk

OpiskelijaHelpdesk on PHP-, JavaScript- ja MariaDB-pohjainen tikettijärjestelmä opiskelijoiden teknisten tukipyyntöjen hallintaan. Sovelluksessa on myös tekoälychat, joka antaa ensiapua IT- ja ohjelmointiongelmiin paikallisen [Ollama](https://ollama.com)-kielimallin avulla.

Projektin tavoitteena on harjoitella web-sovelluksen kehittämistä, tietokantoja, Git-versionhallintaa, GitHub Issues -työskentelyä, brancheja ja Pull Requesteja.

## Sisällys

- [Ominaisuudet](#ominaisuudet)
- [Käyttäjäroolit](#käyttäjäroolit)
- [Teknologiat](#teknologiat)
- [Projektin rakenne](#projektin-rakenne)
- [Tietokanta](#tietokanta)
- [Asennus ja käynnistys](#asennus-ja-käynnistys)
- [Tekoälychat ja Ollama](#tekoälychat-ja-ollama)
- [Vianmääritys](#vianmääritys)
- [Tietoturva](#tietoturva)
- [Käyttöliittymä](#käyttöliittymä)
- [Testaus](#testaus)
- [Git-työskentely](#git-työskentely)
- [Projektin tila ja jatkokehitys](#projektin-tila-ja-jatkokehitys)

## Ominaisuudet

**Kaikki käyttäjät**

- Rekisteröityminen, kirjautuminen ja uloskirjautuminen
- Tekoälychat etusivulla: mallin valinta, sanelu ja vastausten ääneen luku
- Responsiivinen ja näppäimistöllä käytettävä käyttöliittymä

**Opiskelija**

- Uuden tukipyynnön luominen
- Omat tiketit jaettuna aktiivisiin sekä ratkaistuihin ja suljettuihin
- Tikettien haku ja suodatus tilan, prioriteetin ja kategorian mukaan
- Keskustelu tukihenkilön kanssa tiketin kommenteissa
- Siirtyminen chatista tukipyyntöön, jolloin otsikko ja kuvaus esitäytetään keskustelusta

**Tukihenkilö (support)**

- Support-dashboard, jossa kaikki tiketit ovat tilasuodattimien takana lukumäärineen
- Opiskelijoille vastaaminen
- Tiketin tilan ja prioriteetin muuttaminen
- Käsittelijän määrittäminen

**Ylläpitäjä (admin)**

- Tikettien analytiikka kaavioina: tiketit kategorioittain, tilajakauma ja luodut tiketit kuukausittain
- Avoimien tikettien hallinta ja käsittelijän määrittäminen
- Käyttäjien ja roolien hallinta
- Kategorioiden lisääminen, nimeäminen ja poistaminen

## Käyttäjäroolit

| Rooli | Toiminnot |
|---|---|
| `student` | Luo tikettejä, tarkastelee omia tikettejä, kommentoi ja käyttää tekoälychattia |
| `support` | Käsittelee kaikkia tikettejä, vastaa opiskelijoille, muuttaa tiketin tilaa ja prioriteettia sekä määrittää käsittelijän |
| `admin` | Seuraa analytiikkaa, määrittää käsittelijöitä sekä hallitsee käyttäjiä, rooleja ja kategorioita |

Uusi käyttäjä rekisteröityy aina opiskelijaksi. Ylläpitäjä voi vaihtaa roolin hallintapaneelissa.

## Teknologiat

- PHP 8.4 (Apache), PDO ja cURL
- MariaDB 11
- HTML5, CSS3 ja JavaScript ilman sovelluskehyksiä
- [Chart.js 4.4.1](https://www.chartjs.org): tallennettu projektiin (`public/js/vendor/`), joten CDN:ää ei tarvita
- [Ollama](https://ollama.com): paikallinen kielimalli tekoälychatille
- Docker ja Docker Compose
- Git ja GitHub

## Projektin rakenne

```text
opiskelijahelpdesk/
├── database/
│   └── init.sql              # Taulut, oletuskategoriat ja testikäyttäjät
├── docker/
│   └── php/
│       └── Dockerfile        # PHP 8.4 + Apache + pdo_mysql
├── public/                   # Selaimelle näkyvät tiedostot
│   ├── css/style.css
│   ├── js/
│   │   ├── app.js            # Mobiilivalikko ja tiketin esitäyttö chatista
│   │   ├── chat.js           # Etusivun tekoälychat
│   │   ├── admin.js          # Hallintapaneelin kaaviot ja käsittelijädialogi
│   │   └── vendor/           # Chart.js
│   ├── index.php             # Etusivu ja tekoälychat
│   ├── chat-api.php          # Chatin JSON-rajapinta (PHP → Ollama)
│   ├── my-tickets.php        # Opiskelijan tiketit
│   ├── create-ticket.php
│   ├── ticket.php            # Tiketti ja keskustelu
│   ├── support-dashboard.php
│   ├── update-ticket.php
│   ├── assign-ticket.php
│   ├── admin.php             # Ylläpitäjän hallintapaneeli
│   └── login.php, register.php, logout.php
├── src/
│   └── config/
│       ├── auth.php          # Kirjautumis- ja roolitarkistukset
│       ├── csrf.php          # CSRF-suojaus
│       ├── database.php      # PDO-yhteys
│       └── ollama.php        # Ollaman asetukset ja kutsut
├── docker-compose.yaml
└── README.md
```

## Tietokanta

Tietokannassa on neljä päätaulua:

```text
users
  │
  ├──< tickets ──── categories
  │       │
  │       └──< comments
  │
  └──< comments
```

| Taulu | Kentät |
|---|---|
| `users` | `id`, `name`, `email`, `password`, `role`, `created_at` |
| `categories` | `id`, `name` |
| `tickets` | `id`, `user_id`, `category_id`, `assigned_to`, `title`, `description`, `priority`, `status`, `created_at` |
| `comments` | `id`, `ticket_id`, `user_id`, `comment`, `created_at` |

Tiketin tilat ovat `new`, `in_progress`, `waiting_student`, `resolved` ja `closed`. Prioriteetit ovat `low`, `normal`, `high` ja `urgent`.

Taulujen väliset suhteet toteutetaan primary key- ja foreign key -rajoitteilla. Indeksit ovat muun muassa käyttäjän, kategorian, käsittelijän, tilan, prioriteetin ja luontiajan kentillä. Tekoälychatin keskusteluja ei tallenneta tietokantaan.

## Asennus ja käynnistys

### Vaatimukset

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) tai Docker Engine ja Compose
- Git
- Tekoälychattia varten [Ollama](#1-asenna-ollama) ja vähintään yksi malli. Muu sovellus toimii ilman Ollamaa.

### 1. Kloonaa projekti

```bash
git clone https://github.com/santtu-kumpulainen/opiskelijahelpdesk.git
cd opiskelijahelpdesk
```

### 2. Käynnistä kontit

```bash
docker compose up -d --build
docker compose ps
```

| Palvelu | Osoite |
|---|---|
| PHP-sovellus | http://localhost:8000 |
| phpMyAdmin | http://localhost:8082 |
| MariaDB (isäntäkoneelta) | `localhost:3307` |

PHP-kontti yhdistää MariaDB:hen Docker-verkossa osoitteella `mysql:3306`.

### 3. Kirjaudu sisään

`database/init.sql` luo testikäyttäjät `testi@gmail.com` (student), `support@gmail.com` (support) ja `admin@gmail.com` (admin). Salasanat on tallennettu tiivisteinä, joten ne eivät näy tiedostosta.

Voit myös rekisteröityä osoitteessa http://localhost:8000/register.php. Uusi käyttäjä saa opiskelijan roolin. Ensimmäiselle omalle ylläpitäjälle roolin voi vaihtaa phpMyAdminissa (`users.role` → `admin`), minkä jälkeen muita rooleja voi hallita sovelluksen hallintapaneelissa.

### Tietokannan alustaminen

Tietokanta alustetaan tiedostosta `database/init.sql`, kun kontit käynnistetään ensimmäisen kerran. Tiedot tallentuvat Docker-volumelle `helpdesk_mysql_data`.

Kehitysympäristössä tietokannan voi alustaa kokonaan uudelleen:

```bash
docker compose down -v
docker compose up -d --build
```

> `docker compose down -v` poistaa tietokantavolumen ja kaikki siihen tallennetut tiedot. Käytä komentoa vain, kun tietokannan nollaaminen on tarkoituksellista.

### Muutosten päivittäminen konttiin

Dockerfile kopioi `public/`- ja `src/`-kansiot imageen, joten koodimuutokset tulevat voimaan vasta, kun PHP-kontti rakennetaan uudelleen:

```bash
docker compose up -d --build php
```

## Tekoälychat ja Ollama

Kirjautunut käyttäjä voi kysyä etusivun chatissa neuvoa IT- ja ohjelmointiongelmiin. Jos ongelma ei ratkea, opiskelija voi siirtyä tukipyynnön luontiin, ja otsikko ja kuvaus esitäytetään keskustelusta. Tiketti luodaan vasta, kun käyttäjä tarkistaa tiedot ja lähettää lomakkeen.

Arkkitehtuuri:

```text
Selain ──> public/chat-api.php (PHP) ──> Ollama :11434
```

Selain ei ota yhteyttä Ollamaan suoraan. Keskusteluhistoria säilyy selaimen muistissa sivun latauksen ajan, ja PHP lisää jokaiseen pyyntöön suomenkielisen järjestelmäkehotteen.

### 1. Asenna Ollama

| Käyttöjärjestelmä | Asennus |
|---|---|
| Windows | Lataa ja aja asennusohjelma: https://ollama.com/download/windows |
| macOS | Lataa sovellus: https://ollama.com/download/mac |
| Linux | `curl -fsSL https://ollama.com/install.sh \| sh` |

Windowsissa ja macOS:ssä Ollama käynnistyy taustalle asennuksen jälkeen. Linuxissa se asentuu `systemd`-palveluksi. Tarvittaessa palvelun voi käynnistää käsin:

```bash
ollama serve
```

### 2. Lataa kielimalli

Oletusmalli on suomenkielinen [OpenEuroLLM-Finnish](https://ollama.com/jobautomation/OpenEuroLLM-Finnish):

```bash
ollama pull jobautomation/OpenEuroLLM-Finnish:latest
```

Malli vie noin 8 Gt levytilaa, ja sen ajaminen vaatii reilusti muistia. Hitaammalla koneella ensimmäinen vastaus voi kestää kymmeniä sekunteja. Chatissa voi valita minkä tahansa Ollamaan asennetun mallin, joten kevyemmänkin mallin voi asentaa rinnalle.

### 3. Tarkista, että Ollama vastaa

```bash
ollama list
curl http://localhost:11434/api/tags
```

Komentojen tulosteessa pitäisi näkyä ladattu malli.

### 4. Määritä yhteys

Asetukset luetaan ympäristömuuttujista tiedostossa `src/config/ollama.php`:

| Muuttuja | Oletus | Kuvaus |
|---|---|---|
| `OLLAMA_URL` | `http://127.0.0.1:11434` | Ollaman osoite |
| `OLLAMA_MODEL` | `jobautomation/OpenEuroLLM-Finnish:latest` | Oletusmalli, jos se on asennettu |
| `OLLAMA_TIMEOUT` | `180` | Vastauksen enimmäisodotus sekunteina (5–600) |

**Docker.** PHP-kontin sisällä `127.0.0.1` tarkoittaa konttia itseään, ei isäntäkonetta. Siksi `docker-compose.yaml` asettaa oletukseksi `OLLAMA_URL=http://host.docker.internal:11434`. Docker Desktopissa (Windows ja macOS) tämä toimii ilman lisäasetuksia.

Arvot voi ohittaa projektin juuressa olevassa `.env`-tiedostossa, jota ei tallenneta repositoryyn:

```env
OLLAMA_URL=http://host.docker.internal:11434
OLLAMA_MODEL=jobautomation/OpenEuroLLM-Finnish:latest
OLLAMA_TIMEOUT=180
```

Muutosten jälkeen:

```bash
docker compose up -d --build php
```

**Linux.** `docker-compose.yaml` lisää `host.docker.internal`-nimen (`host-gateway`). Ollama kuuntelee oletuksena vain osoitetta `127.0.0.1`, joten salli yhteydet Docker-verkosta:

```bash
sudo systemctl edit ollama.service
# Lisää:
# [Service]
# Environment="OLLAMA_HOST=0.0.0.0"
sudo systemctl daemon-reload
sudo systemctl restart ollama
```

Rajaa tällöin portti `11434` palomuurilla, jotta se ei näy koneen ulkopuolelle.

**Ilman Dockeria.** Kun PHP ajetaan suoraan isäntäkoneella, oletusosoite `http://127.0.0.1:11434` toimii sellaisenaan. PHP tarvitsee laajennukset `pdo_mysql` ja `curl`.

### 5. Käytä chattia

1. Kirjaudu sisään ja avaa etusivu (http://localhost:8000/index.php). Opiskelija pääsee chattiin myös Omat tiketit -sivun "Kysy tekoälyltä" -painikkeesta.
2. Odota, että mallin tila on "Malli valmis". Malli ladataan muistiin sivun avautuessa.
3. Kirjoita kysymys. Enter lähettää, ja Shift + Enter tekee rivinvaihdon.
4. Jos ongelma ei ratkea, valitse "Luo tukipyyntö". Tarkista esitäytetyt tiedot, valitse kategoria ja lähetä.

Sanelu ja ääneen luku käyttävät selaimen omia rajapintoja (esim. Chrome ja Edge). Ääneen luku on oletuksena pois päältä. Chat toimii myös ilman puheominaisuuksia. Huomaa, että selaimen puheentunnistus voi käsitellä ääntä selaimen valmistajan palvelussa.

## Vianmääritys

| Ilmoitus tai oire | Todennäköinen syy ja korjaus |
|---|---|
| "Kielimallipalveluun ei saatu yhteyttä" | Ollama ei ole käynnissä tai `OLLAMA_URL` on väärä. Tarkista `ollama list`. Dockerissa osoitteen on oltava `host.docker.internal`, ei `127.0.0.1`. Linuxissa tarkista `OLLAMA_HOST`. |
| "Kielimallipalvelussa ei ole asennettuja malleja" | Lataa malli: `ollama pull jobautomation/OpenEuroLLM-Finnish:latest`. |
| "Kielimalli ei vastannut ajoissa" | Malli on liian raskas koneelle tai latautuu vielä. Yritä uudelleen, kasvata `OLLAMA_TIMEOUT`-arvoa tai valitse kevyempi malli. |
| "Keskustelu on liian pitkä" | Historiaa on enintään 40 viestiä ja 24 000 merkkiä. Valitse "Aloita uusi keskustelu". |
| "Istuntosi on vanhentunut" | Päivitä sivu ja kirjaudu uudelleen. |
| Koodimuutos ei näy | Rakenna PHP-kontti uudelleen: `docker compose up -d --build php`. |

Tekniset virheiden yksityiskohdat kirjataan PHP:n lokiin, ei käyttäjälle:

```bash
docker compose logs php
```

## Tietoturva

- PDO ja prepared statements kaikissa SQL-kyselyissä
- Salasanat tallennetaan `password_hash()`-menetelmällä
- Istuntopohjainen kirjautuminen ja roolipohjaiset käyttöoikeudet jokaisella sivulla
- CSRF-token kaikissa POST-lomakkeissa ja chat-rajapinnan POST-pyynnöissä
- Palvelinpuolen syötteiden validointi ja tulosteiden escapointi (`htmlspecialchars`)
- Tietokanta- ja Ollama-virheiden yksityiskohtia ei näytetä käyttäjälle
- Chat-rajapinta:
  - vain kirjautuneille käyttäjille
  - viestien määrä ja koko on rajattu
  - vain Ollamaan asennetut mallit hyväksytään
  - järjestelmäkehotteen lisää palvelin
- Tunnuksia ja salaisuuksia ei tallenneta repositoryyn (`.env` on `.gitignore`ssa)

## Käyttöliittymä

- Responsiivinen asettelu tietokoneella, tabletilla ja puhelimella
- Yhtenäiset värit, typografia ja tilamerkit, joissa tilan värit ovat samat kaikilla sivuilla
- Taulukot muuttuvat pienellä näytöllä korteiksi
- Kaavioissa on otsikot, selitteet ja taulukkomuotoinen vaihtoehto
- Näkyvät kenttien nimikkeet ja kohdistusmerkit näppäimistökäytössä
- Virheilmoitukset ja tilaviestit välittyvät myös ruudunlukijalle

## Testaus

Projektia testattiin toiminnallisesti käyttäjärooleittain ja virheellisillä syötteillä. Testattuja asioita ovat muun muassa:

- rekisteröinti, kirjautuminen ja uloskirjautuminen
- tiketin luonti, tarkastelu, haku ja suodatus
- kommentointi ja supportin vastaukset
- tilan, prioriteetin ja käsittelijän muuttaminen
- admin-toiminnot ja analytiikka
- roolipohjaiset käyttöoikeudet ja CSRF-suojaus
- tekoälychat: vastaukset, historia, mallin valinta, virhetilanteet ja tiketin esitäyttö
- responsiivinen käyttöliittymä

## Git-työskentely

Projektia kehitetään GitHub Issues -tikettien avulla. Jokainen ominaisuus toteutetaan omassa branchissaan ja yhdistetään `main`-haaraan Pull Requestin kautta.

```bash
git switch main
git pull origin main
git switch -c feature/ominaisuuden-nimi
```

Muutosten jälkeen:

```bash
git add .
git commit -m "Kuvaava commit-viesti"
git push -u origin feature/ominaisuuden-nimi
```

Pull Request yhdistetään `main`-haaraan GitHubissa.

## Projektin tila ja jatkokehitys

Perusominaisuudet, admin-analytiikka ja tekoälychat on toteutettu.

Jatkokehitysideoita:

- Chat-rajapinnalle käyttäjäkohtainen pyyntöraja
- Chat-vastausten striimaus, jolloin teksti näkyy sitä mukaa kuin malli kirjoittaa
- Ratkaisuajankohdan tallentaminen (esim. `resolved_at`), jotta ratkaisuajat ja ratkaisut ajan mukaan voidaan analysoida
- Uloskirjautuminen POST-pyynnöllä CSRF-suojattuna
