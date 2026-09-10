-- Migration: lägg till rooms-tabell och koppla bookings till den via room_id.
-- Körs EN gång mot en databas som redan har schema.sql:s ursprungliga
-- tabeller. Kör INTE om denna fil - CREATE TABLE rooms misslyckas om den
-- redan finns. Endast testdata finns i bookings.room i nuläget, så den
-- gamla kolumnen tas bort utan att migrera dess innehåll.

CREATE TABLE rooms (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rooms_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO rooms (name) VALUES
    ('Höga Kusten'),
    ('Nyadal'),
    ('Hornö Veda'),
    ('Lilla Matsalen');

ALTER TABLE bookings ADD COLUMN room_id INT UNSIGNED NULL AFTER company_logo;

ALTER TABLE bookings
    ADD CONSTRAINT fk_bookings_room_id FOREIGN KEY (room_id) REFERENCES rooms(id);

ALTER TABLE bookings DROP COLUMN room;
