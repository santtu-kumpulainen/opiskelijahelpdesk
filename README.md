# OpiskelijaHelpdesk

OpiskelijaHelpdesk on PHP-, JavaScript- ja MariaDB-pohjainen tikettijärjestelmä opiskelijoiden teknisten tukipyyntöjen hallintaan.

Projektin tavoitteena on harjoitella web-sovelluksen kehittämistä, tietokantoja, Git-versionhallintaa, GitHub Issues -työskentelyä, brancheja ja Pull Requesteja.

## Ominaisuudet

- Opiskelijan rekisteröityminen ja kirjautuminen
- Opiskelijan omien tikettien hallinta
- Uuden tukipyynnön luominen
- Tikettien haku ja suodatus
- Tiketin tarkastelu
- Kommentit ja support-henkilön vastaukset
- Tiketin tilan ja prioriteetin muuttaminen
- Tiketin osoittaminen support-henkilölle
- Support-dashboard
- Admin-käyttäjien hallinta
- Käyttäjäroolien hallinta
- Roolipohjaiset käyttöoikeudet
- Tekoälychat (paikallinen Ollama-kielimalli)
- Responsiivinen käyttöliittymä

## Käyttäjäroolit

| Rooli | Toiminnot |
|---|---|
| `student` | Luo tikettejä, tarkastelee omia tikettejä ja lisää kommentteja |
| `support` | Käsittelee tikettejä, vastaa opiskelijoille ja muuttaa tikettien tietoja |
| `admin` | Hallitsee käyttäjiä ja käyttäjärooleja |

## Teknologiat

- PHP 8+
- JavaScript
- HTML5
- CSS3
- MariaDB / MySQL
- PDO
- Docker
- Git
- GitHub

## Projektin rakenne

```text
opiskelijahelpdesk/
├── database/
│   └── init.sql
├── docker/
│   └── php/
├── public/
│   ├── css/
│   ├── js/
│   └── *.php
├── src/
│   └── config/
├── .gitignore
├── compose.yaml
└── README.md
```

`public/` sisältää sovelluksen selainkäyttöön tarkoitetut PHP-, JavaScript- ja CSS-tiedostot.

`src/config/` sisältää esimerkiksi tietokantayhteyden ja autentikoinnin asetukset.

`database/init.sql` sisältää tietokannan taulujen ja alustavien kategorioiden luonnin.

## Tietokanta

Tietokanta sisältää seuraavat päätaulut:

```text
users
  │
  ├──< tickets
  │       │
  │       ├── category
  │       └──< comments
  │
  └──< comments
```

### Päätaulut

**users**

- `id`
- `name`
- `email`
- `password`
- `role`
- `created_at`

**categories**

- `id`
- `name`

**tickets**

- `id`
- `user_id`
- `category_id`
- `assigned_to`
- `title`
- `description`
- `priority`
- `status`
- `created_at`

**comments**

- `id`
- `ticket_id`
- `user_id`
- `comment`
- `created_at`

Taulujen väliset suhteet toteutetaan primary key- ja foreign key -rajoitteilla. Tietokannassa on indeksit muun muassa käyttäjän, kategorian, käsittelijän, tilan, prioriteetin ja luontiajan perusteella.

## Asennus

Kloonaa repository:

```bash
git clone https://github.com/santtu-kumpulainen/opiskelijahelpdesk.git
```

Siirry projektikansioon:

```bash
cd opiskelijahelpdesk
```

### Docker

Käynnistä kehitysympäristö:

```bash
docker compose up -d --build
```

Tarkista containerien tila:

```bash
docker compose ps
```

Palvelut:

| Palvelu | Osoite |
|---|---|
| PHP-sovellus | http://localhost:8000 |
| phpMyAdmin | http://localhost:8082 |
| MariaDB | localhost:3307 |

PHP-container yhdistää MariaDB:hen Docker-verkon kautta:

```text
mysql:3306
```

Host-koneelta MariaDB käyttää porttia `3307`.

### Tietokannan alustaminen

Tietokanta alustetaan Dockerin ensimmäisellä käynnistyksellä tiedostosta:

```text
database/init.sql
```

Tietokannan tiedot tallennetaan Docker-volumelle:

```text
helpdesk_mysql_data
```

Kehitysympäristössä tietokannan voi tarvittaessa alustaa kokonaan uudelleen:

```bash
docker compose down -v
docker compose up -d --build
```

> `docker compose down -v` poistaa tietokantavolumen ja kaikki siihen tallennetut tiedot. Käytä komentoa vain, kun tietokannan nollaaminen on tarkoituksellista.

## Tekoälychat (Ollama)

Kirjautunut käyttäjä voi kysyä etusivun chatissa neuvoa IT- ja ohjelmointiongelmiin. Vastaukset tuottaa paikallinen [Ollama](https://ollama.com)-kielimalli. Jos ongelma ei ratkea, opiskelija voi siirtyä tukipyynnön luontiin, jolloin otsikko ja kuvaus esitäytetään keskustelusta. Tiketti luodaan vasta, kun käyttäjä lähettää lomakkeen itse.

Selain kutsuu vain `public/chat-api.php`-rajapintaa, ja PHP välittää pyynnöt Ollamalle. Keskusteluhistoria säilyy selaimen muistissa sivun latauksen ajan, eikä sitä tallenneta tietokantaan.

### Asetukset

Asetukset luetaan ympäristömuuttujista (`src/config/ollama.php`):

| Muuttuja | Oletus | Kuvaus |
|---|---|---|
| `OLLAMA_URL` | `http://127.0.0.1:11434` | Ollaman osoite |
| `OLLAMA_MODEL` | `jobautomation/OpenEuroLLM-Finnish:latest` | Oletusmalli, jos se on asennettu |
| `OLLAMA_TIMEOUT` | `180` | Vastauksen enimmäisodotus sekunteina |

Käyttäjä voi valita vain Ollamaan asennettuja malleja.

### Dockerissa

PHP-kontin sisällä `127.0.0.1` tarkoittaa konttia itseään. Siksi `docker-compose.yaml` asettaa oletukseksi `OLLAMA_URL=http://host.docker.internal:11434`, jolloin kontti yhdistää isäntäkoneella ajettavaan Ollamaan. Arvot voi ohittaa projektin `.env`-tiedostossa:

```env
OLLAMA_URL=http://host.docker.internal:11434
OLLAMA_MODEL=jobautomation/OpenEuroLLM-Finnish:latest
OLLAMA_TIMEOUT=180
```

Muutosten jälkeen: `docker compose up -d --build php`.

Vaatimukset:

- Ollama on käynnissä isäntäkoneella ja malli on asennettu: `ollama pull jobautomation/OpenEuroLLM-Finnish:latest`.
- Docker Desktopissa (Windows/macOS) `host.docker.internal` toimii valmiiksi.
- Linuxissa `docker-compose.yaml` lisää `host.docker.internal`-nimen (`host-gateway`). Ollaman täytyy kuunnella myös Docker-verkkoa, esimerkiksi `OLLAMA_HOST=0.0.0.0`.

Puheentunnistus ja ääneen luku käyttävät selaimen omia rajapintoja (esim. Chrome ja Edge). Chat toimii ilman niitä kirjoittamalla.

## Tietoturva

Projektissa käytetään muun muassa seuraavia ratkaisuja:

- PDO-tietokantayhteys
- prepared statements SQL-kyselyissä
- salasanat tallennetaan `password_hash()`-menetelmällä
- kirjautuminen tarkistetaan session avulla
- roolipohjaiset käyttöoikeudet
- palvelinpuolen syötteiden validointi
- virheelliset käyttäjäsyötteet käsitellään hallitusti
- tietokantavirheiden tarkkoja tietoja ei näytetä käyttäjälle

Tietokantatunnuksia, salasanoja tai muita salaisia tietoja ei tallenneta repositoryyn.

## Käyttöliittymä

Käyttöliittymä on responsiivinen ja toimii tietokoneella, tabletilla ja mobiililaitteilla.

- Navigointi mukautuu pienille näytöille
- Lomakkeet ja painikkeet toimivat kosketusnäytöllä
- Pitkät tekstit rivittyvät ilman vaakasuuntaista ylivuotoa
- Lomakkeissa käytetään näkyviä kenttien nimikkeitä
- Pakolliset kentät tarkistetaan selaimessa ja palvelimella
- Virheilmoitukset näytetään käyttäjälle
- Näppäimistökäytössä näkyvät kohdistusmerkit

## Testaus

Projektia testattiin toiminnallisesti käyttäjärooleittain ja erilaisilla virheellisillä syötteillä.

Testattuja toimintoja ovat muun muassa:

- rekisteröinti
- kirjautuminen ja uloskirjautuminen
- tiketin luonti
- omien tikettien tarkastelu
- tiketin haku ja suodatus
- kommentointi
- supportin vastaaminen
- tiketin käsittely
- admin-toiminnot
- roolipohjaiset käyttöoikeudet
- virheellisten syötteiden käsittely
- responsiivinen käyttöliittymä

## Git-työskentely

Projektia kehitettiin GitHub Issues -tikettien avulla.

Jokainen ominaisuus toteutettiin omassa branchissaan ja yhdistettiin `main`-haaraan Pull Requestin kautta.

Esimerkiksi:

```bash
git switch main
git pull origin main
git switch -c feature/ticket-20
```

Muutosten jälkeen:

```bash
git add .
git commit -m "Complete project documentation"
git push -u origin feature/ticket-20
```

Pull Request yhdistetään `main`-haaraan GitHubissa.

## Projektin tila

Projektin suunnitellut ominaisuudet ja Issue-tiketit on toteutettu ja projekti on valmis palautettavaksi.