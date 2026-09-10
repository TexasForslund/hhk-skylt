# hhk-skylt

Digital konferensskylt för Hotell Höga Kusten.

Visar vilka företag som har konferens i vilken lokal just nu, på en stående
skärm i lobbyn. Uppdateras av receptionen via ett enkelt adminläge.

## Struktur

- `/admin` – inloggningsskyddad vy för att lägga till/redigera/ta bort bokningar
- `/display` – öppen, TV-vänlig vy som auto-uppdaterar var 30:e sekund
- `/uploads` – uppladdade företagsloggor (ingår ej i Git)
- `config.php.example` – mall för databasuppgifter, kopiera till `config.php`
  på servern och fyll i riktiga uppgifter (aldrig committa `config.php`)
- `schema.sql` – databasschema (MySQL)

## Stack

- PHP (ingen ramverk)
- MySQL
- PHP-sessions för inloggning (delat användarnamn/lösenord för receptionen)

## Server

`skylt.hotellhoga-kusten.se`, driftad av Broadview.
