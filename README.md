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

## Status

Projekti on kehitysvaiheessa.