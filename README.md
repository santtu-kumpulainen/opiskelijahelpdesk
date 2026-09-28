# OpiskelijaHelpdesk

OpiskelijaHelpdesk on PHP-, JavaScript- ja MySQL-pohjainen tikettijärjestelmä opiskelijoiden teknisten tukipyyntöjen hallintaan.

Projektin tavoitteena on harjoitella web-sovelluksen kehittämistä, Git-versionhallintaa, GitHub Issues -työskentelyä, brancheja ja Pull Requesteja.

## Teknologiat

- PHP 8+
- JavaScript
- HTML5
- CSS3
- MySQL / MariaDB
- PDO
- Docker
- Git
- GitHub

## Projektin rakenne

```text
opiskelijahelpdesk/
├── database/       # Tietokannan SQL-tiedostot
├── docker/         # Docker-konfiguraatiot
│   ├── mysql/
│   └── php/
├── public/         # Sovelluksen julkiset tiedostot
├── src/            # Sovelluksen lähdekoodi
│   └── config/
├── .gitignore
└── README.md
```

## Asennus

Kloonaa repository:

```bash
git clone https://github.com/santtu-kumpulainen/opiskelijahelpdesk.git
```

Siirry projektikansioon:

```bash
cd opiskelijahelpdesk
```

Docker-ympäristö otetaan käyttöön projektin myöhemmässä vaiheessa.

## Paikallinen käynnistys

Projektin nykyinen versio voidaan käynnistää PHP:n sisäänrakennetulla kehityspalvelimella:

```bash
php -S localhost:8000 -t public
```

Avaa selaimessa:

```text
http://localhost:8000
```

## Kehitys

Projektia kehitetään GitHub Issues -tikettien avulla. Jokainen tiketti toteutetaan omassa branchissaan ja yhdistetään `main`-haaraan Pull Requestin kautta.

Esimerkiksi:

```bash
git switch main
git pull origin main
git switch -c feature/ticket-1
```

## Docker-kehitysympäristö

Projekti käyttää Dockeria PHP-, MariaDB- ja phpMyAdmin-ympäristön suorittamiseen.

### Käynnistys

Projektin juuressa suorita:

```bash
docker compose up -d --build
```

Containerien tilan voi tarkistaa:

```bash
docker compose ps
```

### Palvelut

| Palvelu | Osoite |
|---|---|
| PHP-sovellus | http://localhost:8000 |
| phpMyAdmin | http://localhost:8082 |
| MariaDB | localhost:3307 |

PHP-sovellus käyttää Docker-verkon kautta MariaDB-palvelua osoitteella:

```text
mysql:3306
```

Host-koneelta MariaDB on saatavilla portissa `3307`.

### phpMyAdmin

phpMyAdminiin kirjaudutaan osoitteessa:

http://localhost:8082

Kirjautumistiedot:

```text
Server: mysql
Username: <username>
Password: <password>
```

### Tietokannan alustaminen

Tietokanta alustetaan automaattisesti Dockerin ensimmäisen käynnistyksen yhteydessä tiedostosta:

```text
database/init.sql
```

Tietokannan data tallennetaan Docker-volumelle:

```text
helpdesk_mysql_data
```

Jos tietokanta halutaan alustaa kokonaan uudelleen kehityksen aikana:

```bash
docker compose down -v
docker compose up -d --build
```

Huomio: `docker compose down -v` poistaa tietokannan Docker-volumen ja samalla kaikki siihen tallennetut tiedot.

## Status

Projekti on kehitysvaiheessa.